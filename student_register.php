<?php
/**
 * student-register.php
 * Public student registration form.
 * On submit:
 *   1. Saves student to kathakali_bridge.students
 *   2. Creates Moodle user account via MoodleSync
 *   3. Enrolls student into selected Moodle course
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/api/MoodleSync.php';

session_name(SESSION_NAME);
session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'path'=>'/','httponly'=>true,'samesite'=>'Lax']);
session_start();

$db      = DB::conn();
$errors  = [];
$success = false;

// Fetch available classes for the dropdown
$classes = $db->query(
    "SELECT class_id, name FROM classes WHERE status != 'cancelled' ORDER BY class_id ASC"
)->fetchAll();

// ── HANDLE POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $class_id = (int)($_POST['class_id'] ?? 0);

    // Validate
    if (!$name)  $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$phone) $errors[] = 'Phone number is required.';
    if (!$class_id) $errors[] = 'Please select a class.';

    // Check email unique in students
    if (empty($errors)) {
        $chk = $db->prepare("SELECT student_id FROM students WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) $errors[] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        try {
            // Build label — Student-N
            $count = (int)$db->query("SELECT COUNT(*) FROM students")->fetchColumn();
            $label = 'Student-' . ($count + 1);

            // Get class info for Moodle course ID
            $cls = $db->prepare("SELECT moodle_course_id FROM classes WHERE class_id = ?");
            $cls->execute([$class_id]);
            $classRow = $cls->fetch();
            $moodleCourseId = (int)($classRow['moodle_course_id'] ?? 0);

            // Insert into kathakali_bridge.students
            $ins = $db->prepare("
                INSERT INTO students
                    (label, email, class_id, status, joined_date,
                     accuracy_pct, attendance_pct, submission_count, moodle_user_id)
                VALUES (?, ?, ?, 'active', CURDATE(), 0, 0, 0, 0)
            ");
            $ins->execute([$label, $email, $class_id]);
            $studentId = (int)$db->lastInsertId();

            // Store phone in a meta way (extend schema if needed)
            // For now store in label field supplement
            $db->prepare("UPDATE students SET label = ? WHERE student_id = ?")
               ->execute([$name . ' (' . $label . ')', $studentId]);

            // Sync to Moodle
            $sync   = new MoodleSync();
            $student = [
                'student_id'    => $studentId,
                'label'         => $label,
                'email'         => $email,
                'moodle_user_id'=> 0,
            ];
            $moodleResult = $sync->createStudent($student, $moodleCourseId);

            $success = true;

        } catch (PDOException $e) {
            $errors[] = 'Registration failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration — Kathakali Bridge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&family=Work+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --blue:#0366B0; --teal:#02B393; --green:#A3CE47;
            --dark:#1a2a1a; --mid:#4a6a5a; --light:#f4faf6;
            --grad:linear-gradient(135deg,#0366B0 0%,#02B393 100%);
            --warm:linear-gradient(135deg,#d4793a,#c4693a);
            --r:16px;
            --font-display:'Cormorant Garamond',serif;
            --font-body:'Work Sans',sans-serif;
        }

        body {
            font-family:var(--font-body);
            min-height:100vh;
            background:#f0f8f4;
            background-image:
                radial-gradient(ellipse at 20% 50%, rgba(2,179,147,0.08) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 20%, rgba(3,102,176,0.06) 0%, transparent 50%),
                radial-gradient(rgba(2,179,147,0.04) 1px, transparent 1px);
            background-size:100% 100%, 100% 100%, 28px 28px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            padding:40px 20px;
        }

        /* Brand */
        .brand {
            display:flex; align-items:center; gap:14px;
            margin-bottom:36px;
        }
        .brand-icon {
            width:52px; height:52px;
            background:var(--grad);
            border-radius:14px;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 8px 24px rgba(2,179,147,0.3);
        }
        .brand-text { display:flex; flex-direction:column; }
        .brand-name {
            font-family:var(--font-display);
            font-size:1.6rem; font-weight:700;
            color:var(--dark); line-height:1;
            letter-spacing:-0.3px;
        }
        .brand-sub {
            font-size:0.78rem; color:var(--mid);
            font-weight:500; margin-top:3px;
            letter-spacing:0.5px; text-transform:uppercase;
        }

        /* Card */
        .card {
            width:100%; max-width:500px;
            background:white;
            border-radius:24px;
            box-shadow:
                0 1px 0 rgba(255,255,255,0.8) inset,
                0 20px 60px rgba(3,102,176,0.10),
                0 4px 16px rgba(3,102,176,0.06);
            overflow:hidden;
        }

        /* Card top band */
        .card-top {
            background:var(--grad);
            padding:32px 36px 28px;
            position:relative;
            overflow:hidden;
        }
        .card-top::before {
            content:'';
            position:absolute;
            top:-40px; right:-40px;
            width:180px; height:180px;
            border-radius:50%;
            background:rgba(255,255,255,0.08);
        }
        .card-top::after {
            content:'';
            position:absolute;
            bottom:-60px; left:-20px;
            width:140px; height:140px;
            border-radius:50%;
            background:rgba(255,255,255,0.05);
        }
        .card-top-title {
            font-family:var(--font-display);
            font-size:1.9rem; font-weight:700;
            color:white; margin-bottom:6px;
            position:relative; z-index:1;
        }
        .card-top-sub {
            font-size:0.85rem;
            color:rgba(255,255,255,0.8);
            line-height:1.5;
            position:relative; z-index:1;
        }

        /* Card body */
        .card-body { padding:32px 36px 28px; }

        /* Alerts */
        .alert {
            padding:13px 16px; border-radius:12px;
            font-size:0.84rem; margin-bottom:22px;
            line-height:1.55;
        }
        .alert-error {
            background:rgba(231,76,60,0.07);
            color:#b03020;
            border:1px solid rgba(231,76,60,0.18);
        }
        .alert-error ul { padding-left:16px; margin-top:4px; }
        .alert-success {
            background:rgba(2,179,147,0.08);
            color:#027a60;
            border:1px solid rgba(2,179,147,0.2);
        }

        /* Fields */
        .field { display:flex; flex-direction:column; gap:7px; margin-bottom:20px; }
        .field:last-of-type { margin-bottom:0; }

        .field label {
            font-size:0.72rem; font-weight:700;
            text-transform:uppercase; letter-spacing:1px;
            color:var(--mid);
            display:flex; align-items:center; gap:6px;
        }
        .field label .req { color:#e05030; }
        .field label svg { opacity:0.5; }

        .field input,
        .field select {
            padding:12px 16px;
            border:1.5px solid rgba(3,102,176,0.15);
            border-radius:12px;
            font-family:var(--font-body);
            font-size:0.95rem;
            color:var(--dark);
            background:#fafffe;
            outline:none;
            transition:border-color 0.2s, box-shadow 0.2s, background 0.2s;
            width:100%;
        }
        .field input:focus,
        .field select:focus {
            border-color:var(--teal);
            box-shadow:0 0 0 3px rgba(2,179,147,0.12);
            background:white;
        }
        .field input::placeholder { color:#b0c0b8; }
        .field select { cursor:pointer; }

        /* Two col grid */
        .field-grid {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:0 20px;
        }
        .field-grid .span2 { grid-column:1/span 2; }
        @media(max-width:480px) {
            .field-grid { grid-template-columns:1fr; }
            .field-grid .span2 { grid-column:1; }
        }

        /* Submit button */
        .btn-submit {
            width:100%;
            padding:14px;
            margin-top:24px;
            background:var(--grad);
            border:none; border-radius:14px;
            font-family:var(--font-display);
            font-weight:700; font-size:1.05rem;
            color:white; letter-spacing:0.3px;
            cursor:pointer;
            transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            display:flex; align-items:center; justify-content:center; gap:10px;
            box-shadow:0 4px 20px rgba(2,179,147,0.3);
        }
        .btn-submit:hover {
            opacity:0.92;
            transform:translateY(-2px);
            box-shadow:0 8px 28px rgba(2,179,147,0.35);
        }
        .btn-submit:active { transform:translateY(0); }

        /* Footer */
        .card-footer {
            text-align:center;
            padding:16px 36px 28px;
            font-size:0.82rem; color:var(--mid);
            border-top:1px solid rgba(3,102,176,0.06);
        }
        .card-footer a {
            color:var(--blue); font-weight:600;
            text-decoration:none;
        }
        .card-footer a:hover { text-decoration:underline; }

        /* Success screen */
        .success-wrap {
            text-align:center;
            padding:16px 0 8px;
        }
        .success-icon {
            width:80px; height:80px;
            background:linear-gradient(135deg,rgba(2,179,147,0.12),rgba(3,102,176,0.08));
            border-radius:50%;
            margin:0 auto 20px;
            display:flex; align-items:center; justify-content:center;
        }
        .success-title {
            font-family:var(--font-display);
            font-size:1.7rem; font-weight:700;
            color:var(--dark); margin-bottom:10px;
        }
        .success-sub {
            font-size:0.9rem; color:var(--mid);
            line-height:1.7; margin-bottom:28px;
            max-width:340px; margin-left:auto; margin-right:auto;
        }
        .btn-login {
            display:inline-flex; align-items:center; gap:8px;
            padding:13px 32px;
            background:var(--grad);
            border-radius:14px;
            font-family:var(--font-display);
            font-weight:700; font-size:1rem;
            color:white; text-decoration:none;
            transition:opacity 0.2s, transform 0.15s;
            box-shadow:0 4px 20px rgba(2,179,147,0.25);
        }
        .btn-login:hover { opacity:0.9; transform:translateY(-1px); }

        /* Moodle badge */
        .moodle-badge {
            display:inline-flex; align-items:center; gap:6px;
            font-size:0.72rem; font-weight:600;
            padding:4px 12px; border-radius:20px;
            background:rgba(2,179,147,0.1);
            color:#027a60;
            border:1px solid rgba(2,179,147,0.2);
            margin-top:12px;
        }
        .moodle-dot {
            width:6px; height:6px; border-radius:50%;
            background:#02B393;
            animation:pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { opacity:1; transform:scale(1); }
            50%      { opacity:0.5; transform:scale(0.8); }
        }
    </style>
</head>
<body>

<!-- Brand -->
<div class="brand">
    <div class="brand-icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
            <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
            <path d="M8 11c-2-2-4-2-6-1 4 1 6 3 6 7 0-4-2-6-6-7 2-1 4-1 6 1z"/>
            <path d="M16 11c2-2 4-2 6-1-4 1-6 3-6 7 0-4 2-6 6-7-2-1-4-1-6 1z"/>
        </svg>
    </div>
    <div class="brand-text">
        <div class="brand-name">Kathakali Bridge</div>
        <div class="brand-sub">Student Portal</div>
    </div>
</div>

<div class="card">

    <div class="card-top">
        <div class="card-top-title">Join a Class</div>
        <div class="card-top-sub">
            Register as a student and get instant access<br>to your Moodle learning account.
        </div>
    </div>

    <div class="card-body">

        <?php if ($success): ?>
        <!-- ══════════ SUCCESS ══════════ -->
        <div class="success-wrap">
            <div class="success-icon">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#02B393" stroke-width="2.5">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div class="success-title">You're registered!</div>
            <div class="success-sub">
                Your student account has been created and you've been enrolled in your class.
                Check your email for your Moodle login credentials.
            </div>
            <div class="moodle-badge">
                <div class="moodle-dot"></div>
                Moodle account created &amp; enrolled
            </div>
        </div>

        <?php else: ?>
        <!-- ══════════ FORM ══════════ -->

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>Please fix the following:</strong>
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="field-grid">

                <div class="field span2">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        Full Name <span class="req">*</span>
                    </label>
                    <input type="text" name="name"
                           placeholder="Your full name"
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                           autofocus required>
                </div>

                <div class="field span2">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Email Address <span class="req">*</span>
                    </label>
                    <input type="email" name="email"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required>
                </div>

                <div class="field span2">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.08 6.08l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        Phone Number <span class="req">*</span>
                    </label>
                    <input type="tel" name="phone"
                           placeholder="+91 98765 43210"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           required>
                </div>

                <div class="field span2">
                    <label>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        Select Class <span class="req">*</span>
                    </label>
                    <select name="class_id" required>
                        <option value="">— Choose your class —</option>
                        <?php foreach ($classes as $cls): ?>
                            <option value="<?php echo $cls['class_id']; ?>"
                                <?php echo ((int)($_POST['class_id'] ?? 0) === (int)$cls['class_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls['name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($classes)): ?>
                            <option disabled>No classes available yet</option>
                        <?php endif; ?>
                    </select>
                </div>

            </div><!-- /field-grid -->

            <button type="submit" class="btn-submit">
                Register &amp; Join Class
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </button>
        </form>
        <?php endif; ?>

    </div><!-- /card-body -->

    <div class="card-footer">
        Already have an account? <a href="login.php">Sign in here</a>
        &nbsp;·&nbsp;
        Are you a teacher? <a href="register.php">Teacher registration</a>
    </div>

</div><!-- /card -->

</body>
</html>