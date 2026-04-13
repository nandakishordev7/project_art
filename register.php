<?php
// register.php — Multi-step teacher registration

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/api/moodleSync.php';

session_name(SESSION_NAME);
session_set_cookie_params(['lifetime'=>SESSION_LIFETIME,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
session_start();

if (isset($_SESSION['teacher_id'])) { header('Location: teacher-dashboard.php'); exit; }

$errors  = [];
$success = false;
$step    = (int)($_SESSION['reg_step'] ?? 1);
$data    = $_SESSION['reg_data'] ?? [];

// ── UPLOAD HELPER ────────────────────────────────────────────
function handleUpload($field, $allowed = ['image/jpeg','image/png','image/webp'], $maxMB = 4) {
    if (empty($_FILES[$field]['tmp_name'])) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) return null;
    if ($f['size'] > $maxMB * 1024 * 1024) return null;
    $mime = mime_content_type($f['tmp_name']);
    if (!in_array($mime, $allowed)) return null;
    $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
    $name = uniqid('upload_') . '.' . strtolower($ext);
    $dir  = __DIR__ . '/uploads/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    move_uploaded_file($f['tmp_name'], $dir . $name);
    return 'uploads/' . $name;
}

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle back BEFORE any validation
    if ($action === 'back') {
        $step = max(1, $step - 1);
        $_SESSION['reg_step'] = $step;
        header('Location: register.php'); exit;
    }

    // Merge posted text fields into session data
    $posted = array_map('trim', array_filter($_POST, fn($k) => $k !== 'action', ARRAY_FILTER_USE_KEY));
    $data   = array_merge($data, $posted);
    $_SESSION['reg_data'] = $data;

    // ── STEP VALIDATION ──────────────────────────────────────
    if ($step === 1) {
        if (empty($data['name']))  $errors[] = 'Full name is required.';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
            $errors[] = 'A valid email address is required.';
        if (empty($data['phone'])) $errors[] = 'Phone number is required.';
        if (empty($data['dob']))   $errors[] = 'Date of birth is required.';
        if (empty($data['gender']))$errors[] = 'Please select a gender.';
        if (empty($errors)) {
            try {
                $chk = DB::conn()->prepare('SELECT teacher_id FROM teachers WHERE email=?');
                $chk->execute([$data['email']]);
                if ($chk->fetch()) $errors[] = 'An account with this email already exists. <a href="login.php">Sign in instead.</a>';
            } catch (PDOException $e) { $errors[] = 'Database error. Please try again.'; }
        }
        $av = handleUpload('avatar', ['image/jpeg','image/png','image/webp','image/gif']);
        if ($av) $data['avatar_path'] = $av;
    }
    if ($step === 2) {
        if (empty($data['art_category']))     $errors[] = 'Please select a primary art category.';
        if (empty($data['art_form']))         $errors[] = 'Please enter your specific art form.';
        if (empty($data['years_experience'])) $errors[] = 'Please select years of experience.';
    }
    if ($step === 3) {
        // All optional
    }
    if ($step === 4) {
        // All optional
    }
    if ($step === 5) {
        if (empty($errors)) {
            $portfolioFiles = [];
            for ($i = 1; $i <= 3; $i++) {
                $p = handleUpload("portfolio_$i");
                if ($p) $portfolioFiles[] = $p;
            }
            $certPath = handleUpload('cert_file', ['image/jpeg','image/png','application/pdf']);
            if ($certPath) $data['cert_path'] = $certPath;

            $studentLevels = implode(',', (array)($_POST['student_levels'] ?? []));

            $parts    = explode(' ', trim($data['name'] ?? ''));
            $initials = '';
            foreach ($parts as $p) { if ($p) $initials .= strtoupper($p[0]); }
            $initials = substr($initials, 0, 2) ?: 'T';

            try {
                $ins = DB::conn()->prepare("
                    INSERT INTO teachers
                        (name, email, phone, dob, gender, location, timezone,
                         avatar_path, art_category, art_form, years_experience, awards,
                         qualification, institution, cert_path,
                         student_levels, languages, age_group_pref,
                         instagram, linkedin, youtube, portfolio_url, bio, equipment_needed,
                         specialty, initials, is_approved)
                    VALUES
                        (:name,:email,:phone,:dob,:gender,:location,:timezone,
                         :avatar_path,:art_category,:art_form,:years_experience,:awards,
                         :qualification,:institution,:cert_path,
                         :student_levels,:languages,:age_group_pref,
                         :instagram,:linkedin,:youtube,:portfolio_url,:bio,:equipment_needed,
                         :specialty,:initials, 0)
                ");
                $ins->execute([
                    ':name'             => $data['name'],
                    ':email'            => $data['email'],
                    ':phone'            => $data['phone']            ?? null,
                    ':dob'              => $data['dob']              ?? null,
                    ':gender'           => $data['gender']           ?? null,
                    ':location'         => $data['location']         ?? null,
                    ':timezone'         => $data['timezone']         ?? 'Asia/Kolkata',
                    ':avatar_path'      => $data['avatar_path']      ?? null,
                    ':art_category'     => $data['art_category']     ?? null,
                    ':art_form'         => $data['art_form']         ?? null,
                    ':years_experience' => $data['years_experience'] ?? null,
                    ':awards'           => $data['awards']           ?? null,
                    ':qualification'    => $data['qualification']    ?? null,
                    ':institution'      => $data['institution']      ?? null,
                    ':cert_path'        => $data['cert_path']        ?? null,
                    ':student_levels'   => $studentLevels            ?: null,
                    ':languages'        => $data['languages']        ?? null,
                    ':age_group_pref'   => $data['age_group_pref']   ?? null,
                    ':instagram'        => $data['instagram']        ?? null,
                    ':linkedin'         => $data['linkedin']         ?? null,
                    ':youtube'          => $data['youtube']          ?? null,
                    ':portfolio_url'    => $data['portfolio_url']    ?? null,
                    ':bio'              => $data['bio']              ?? null,
                    ':equipment_needed' => $data['equipment_needed'] ?? null,
                    ':specialty'        => $data['art_form'] ?? ($data['art_category'] ?? 'Instructor'),
                    ':initials'         => $initials,
                ]);
                $newId = DB::conn()->lastInsertId();

                foreach ($portfolioFiles as $pf) {
                    DB::conn()->prepare('INSERT INTO portfolio_images (teacher_id,file_path) VALUES (?,?)')->execute([$newId,$pf]);
                }

                unset($_SESSION['reg_step'], $_SESSION['reg_data']);
                $success = true;
                $step    = 6;

            } catch (PDOException $e) {
                $errors[] = 'Registration failed: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    if (empty($errors) && $step < 5 && !$success) {
        $step++;
        $_SESSION['reg_step'] = $step;
    }
    $_SESSION['reg_data'] = $data;
}

$totalSteps = 5;
$stepLabels = ['Personal', 'Expertise', 'Qualifications', 'Teaching', 'Portfolio'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Digital Arts School</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800;900&family=Work+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        :root{
            --blue:#0366B0;--teal:#02B393;--green:#A3CE47;--yellow:#F3C73B;
            --dark:#1e2d3d;--mid:#5a7a9a;--light:#f5fdfc;--white:#fff;
            --grad:linear-gradient(135deg,#0366B0 0%,#02B393 100%);
            --r:14px;--font-h:'Raleway',sans-serif;--font-b:'Work Sans',sans-serif;
        }
        body{font-family:var(--font-b);background:var(--light);min-height:100vh;
             background-image:radial-gradient(rgba(2,179,147,0.06) 1px,transparent 1px);
             background-size:24px 24px;}

        /* Header */
        .reg-header{
            background:var(--white);border-bottom:3px solid transparent;
            border-image:linear-gradient(90deg,#A3CE47,#02B393) 1;
            padding:16px 40px;display:flex;align-items:center;
            justify-content:space-between;position:sticky;top:0;z-index:100;
            box-shadow:0 2px 16px rgba(3,102,176,0.07);
        }
        .reg-brand{display:flex;align-items:center;gap:10px;}
        .reg-brand-icon{width:36px;height:36px;background:var(--grad);border-radius:9px;
                        display:flex;align-items:center;justify-content:center;}
        .reg-brand-name{font-family:var(--font-h);font-weight:800;font-size:1rem;color:var(--dark);}
        .reg-brand-name span{font-weight:400;color:var(--mid);font-size:0.78rem;margin-left:4px;}
        .reg-login-link{font-size:0.85rem;color:var(--mid);}
        .reg-login-link a{color:var(--blue);font-weight:600;text-decoration:none;}
        .reg-login-link a:hover{text-decoration:underline;}

        /* Progress */
        .reg-progress{max-width:720px;margin:28px auto 0;padding:0 24px;}
        .steps-row{display:flex;align-items:center;gap:0;}
        .step-item{display:flex;align-items:center;flex:1;}
        .step-item:last-child{flex:0;}
        .step-circle{
            width:32px;height:32px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;
            font-family:var(--font-h);font-weight:700;font-size:0.8rem;
            flex-shrink:0;transition:all 0.3s;
        }
        .step-circle.done  {background:var(--teal);color:white;}
        .step-circle.active{background:var(--grad);color:white;box-shadow:0 0 0 4px rgba(2,179,147,0.2);}
        .step-circle.future{background:rgba(3,102,176,0.08);color:var(--mid);}
        .step-label{font-size:0.72rem;color:var(--mid);margin-top:4px;text-align:center;}
        .step-label.active{color:var(--dark);font-weight:700;}
        .step-connector{flex:1;height:2px;background:rgba(3,102,176,0.12);margin:0 4px;}
        .step-connector.done{background:var(--teal);}
        .step-wrap{display:flex;flex-direction:column;align-items:center;}

        /* Card */
        .reg-card{
            max-width:720px;margin:24px auto 60px;padding:40px 44px;
            background:white;border-radius:20px;
            box-shadow:0 8px 40px rgba(3,102,176,0.09);
        }
        .reg-section-title{
            font-family:var(--font-h);font-weight:800;font-size:1.45rem;
            color:var(--dark);margin-bottom:4px;
        }
        .reg-section-sub{font-size:0.88rem;color:var(--mid);margin-bottom:28px;line-height:1.6;}

        /* Grid */
        .field-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px 24px;margin-bottom:0;}
        .field-grid .span2{grid-column:1/span 2;}
        @media(max-width:560px){.field-grid{grid-template-columns:1fr;}.field-grid .span2{grid-column:1;}}

        /* Field */
        .field{display:flex;flex-direction:column;gap:6px;margin-bottom:0;}
        .field label{font-size:0.75rem;font-weight:700;text-transform:uppercase;
                     letter-spacing:0.8px;color:var(--mid);}
        .field label .req{color:#e74c3c;margin-left:2px;}
        .field label .opt{color:#aac;font-weight:400;text-transform:none;letter-spacing:0;margin-left:4px;font-size:0.72rem;}
        .field input,.field select,.field textarea{
            padding:11px 14px;border:1.5px solid rgba(3,102,176,0.18);
            border-radius:var(--r);font-family:var(--font-b);font-size:0.92rem;
            color:var(--dark);background:var(--light);outline:none;
            transition:border-color 0.2s,box-shadow 0.2s;width:100%;
        }
        .field input:focus,.field select:focus,.field textarea:focus{
            border-color:var(--teal);box-shadow:0 0 0 3px rgba(2,179,147,0.12);
        }
        .field textarea{resize:vertical;min-height:88px;}
        .field select{cursor:pointer;}
        .field input::placeholder,.field textarea::placeholder{color:#aac;}

        /* Checkbox group */
        .check-group{display:flex;flex-wrap:wrap;gap:8px;}
        .check-chip{position:relative;}
        .check-chip input[type=checkbox]{position:absolute;opacity:0;width:0;height:0;}
        .check-chip label{
            display:inline-flex;align-items:center;gap:6px;
            padding:8px 14px;border-radius:20px;font-size:0.82rem;font-weight:600;
            border:1.5px solid rgba(3,102,176,0.18);color:var(--mid);cursor:pointer;
            background:var(--light);transition:all 0.2s;user-select:none;
        }
        .check-chip input:checked + label{
            background:rgba(2,179,147,0.1);border-color:var(--teal);color:#027a60;
        }

        /* File upload area */
        .upload-area{
            border:2px dashed rgba(3,102,176,0.2);border-radius:var(--r);
            padding:20px;text-align:center;cursor:pointer;
            transition:all 0.2s;background:var(--light);
        }
        .upload-area:hover{border-color:var(--teal);background:rgba(2,179,147,0.04);}
        .upload-area input[type=file]{display:none;}
        .upload-label{display:block;cursor:pointer;}
        .upload-icon{color:var(--teal);margin-bottom:6px;}
        .upload-text{font-size:0.85rem;color:var(--mid);}
        .upload-text strong{color:var(--dark);}

        /* Avatar preview */
        .avatar-row{display:flex;align-items:center;gap:20px;margin-bottom:0;}
        .avatar-preview{
            width:72px;height:72px;border-radius:14px;background:var(--grad);
            display:flex;align-items:center;justify-content:center;
            font-family:var(--font-h);font-weight:800;font-size:1.2rem;color:white;
            overflow:hidden;flex-shrink:0;
        }
        .avatar-preview img{width:100%;height:100%;object-fit:cover;}
        .avatar-upload-btn{
            padding:9px 18px;background:rgba(3,102,176,0.06);
            border:1.5px solid rgba(3,102,176,0.2);border-radius:var(--r);
            font-family:var(--font-b);font-size:0.85rem;font-weight:600;
            color:var(--dark);cursor:pointer;transition:all 0.2s;
        }
        .avatar-upload-btn:hover{background:rgba(2,179,147,0.08);border-color:var(--teal);}

        /* Section separator */
        .reg-sep{height:1px;background:rgba(3,102,176,0.08);margin:28px 0;}

        /* Nav buttons */
        .reg-nav{display:flex;align-items:center;justify-content:space-between;margin-top:32px;gap:12px;}
        .btn-back{
            padding:12px 24px;background:none;border:1.5px solid rgba(3,102,176,0.18);
            border-radius:var(--r);font-family:var(--font-h);font-weight:600;
            font-size:0.9rem;color:var(--mid);cursor:pointer;transition:all 0.2s;
        }
        .btn-back:hover{border-color:var(--blue);color:var(--dark);}
        .btn-next{
            padding:12px 32px;background:var(--grad);border:none;border-radius:var(--r);
            font-family:var(--font-h);font-weight:700;font-size:0.9rem;color:white;
            cursor:pointer;transition:opacity 0.2s,transform 0.15s;display:flex;
            align-items:center;gap:8px;
        }
        .btn-next:hover{opacity:0.92;transform:translateY(-1px);}

        /* Errors */
        .alert-error{
            padding:12px 16px;border-radius:10px;font-size:0.85rem;
            margin-bottom:20px;background:rgba(231,76,60,0.08);
            color:#c0392b;border:1px solid rgba(231,76,60,0.2);
        }
        .alert-error ul{padding-left:16px;margin-top:4px;}

        /* Success */
        .success-wrap{text-align:center;padding:20px 0;}
        .success-icon{
            width:72px;height:72px;background:rgba(2,179,147,0.1);
            border-radius:50%;margin:0 auto 20px;display:flex;
            align-items:center;justify-content:center;
        }
        .success-title{font-family:var(--font-h);font-weight:800;font-size:1.6rem;color:var(--dark);margin-bottom:10px;}
        .success-sub{font-size:0.92rem;color:var(--mid);line-height:1.6;max-width:420px;margin:0 auto 28px;}
        .btn-login{
            display:inline-flex;align-items:center;gap:8px;
            padding:13px 32px;background:var(--grad);border-radius:var(--r);
            font-family:var(--font-h);font-weight:700;font-size:0.9rem;
            color:white;text-decoration:none;transition:opacity 0.2s;
        }
        .btn-login:hover{opacity:0.9;}

        @media(max-width:600px){
            .reg-card{padding:28px 20px;}
            .reg-header{padding:12px 16px;}
        }
    </style>
</head>
<body>

<header class="reg-header">
    <div class="reg-brand">
        <div class="reg-brand-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
                <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
                <path d="M8 11c-2-2-4-2-6-1 4 1 6 3 6 7 0-4-2-6-6-7 2-1 4-1 6 1z"/>
                <path d="M16 11c2-2 4-2 6-1-4 1-6 3-6 7 0-4 2-6 6-7-2-1-4-1-6 1z"/>
            </svg>
        </div>
        <div class="reg-brand-name">Digital Arts School<span>Teacher Registration</span></div>
    </div>
    <div class="reg-login-link">Already registered? <a href="login.php">Sign in</a></div>
</header>

<?php if ($step < 6): ?>
<div class="reg-progress">
    <div class="steps-row">
        <?php foreach ($stepLabels as $i => $label):
            $n = $i + 1;
            $state = $n < $step ? 'done' : ($n === $step ? 'active' : 'future');
        ?>
        <div class="step-wrap">
            <div class="step-circle <?php echo $state; ?>">
                <?php if ($state === 'done'): ?>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                <?php else: echo $n; endif; ?>
            </div>
            <div class="step-label <?php echo $state === 'active' ? 'active' : ''; ?>"><?php echo $label; ?></div>
        </div>
        <?php if ($i < count($stepLabels) - 1): ?>
        <div class="step-connector <?php echo $n < $step ? 'done' : ''; ?>"></div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="reg-card">

<?php if (!empty($errors)): ?>
<div class="alert-error">
    <strong>Please fix the following:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($step === 6): ?>
<!-- ═══════════════════════════════════════════════════
     SUCCESS SCREEN
════════════════════════════════════════════════════ -->
<div class="success-wrap">
    <div class="success-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#02B393" stroke-width="2.5">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
            <polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
    </div>
    <div class="success-title">Registration submitted!</div>
    <div class="success-sub">
        Your application has been received. An administrator will review your profile and send an approval notification to
        <strong><?php echo htmlspecialchars($data['email'] ?? ''); ?></strong>.
        <br><br>Once approved, you can sign in using your email and a 4-digit code.
    </div>
    <a href="login.php" class="btn-login">
        Go to Sign In
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
    </a>
</div>

<?php elseif ($step === 1): ?>
<!-- ═══════════════════════════════════════════════════
     STEP 1: Personal Information
════════════════════════════════════════════════════ -->
<div class="reg-section-title">Personal Information</div>
<div class="reg-section-sub">The basics — let students and the platform know who you are.</div>

<form method="POST" enctype="multipart/form-data">
<div class="field-grid">

    <div class="field span2">
        <label>Profile Picture <span class="opt">(optional — can be added later in settings)</span></label>
        <div class="avatar-row">
            <div class="avatar-preview" id="avatarPreview">
                <?php if (!empty($data['avatar_path'])): ?>
                    <img src="<?php echo htmlspecialchars($data['avatar_path']); ?>" id="avatarImg">
                <?php else: ?>
                    <span id="avatarInitials"><?php echo strtoupper(substr($data['name'] ?? 'T', 0, 1)); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <label class="avatar-upload-btn" for="avatarFile">Upload Photo</label>
                <input type="file" id="avatarFile" name="avatar" accept="image/*">
                <div style="font-size:0.75rem;color:#aac;margin-top:6px;">JPG, PNG or WebP · max 4MB</div>
            </div>
        </div>
    </div>

    <div class="field">
        <label>Full Name <span class="req">*</span></label>
        <input type="text" name="name" placeholder="Your full name"
               value="<?php echo htmlspecialchars($data['name'] ?? ''); ?>" required>
    </div>
    <div class="field">
        <label>Email Address <span class="req">*</span></label>
        <input type="email" name="email" placeholder="you@example.com"
               value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>" required>
    </div>
    <div class="field">
        <label>Phone Number <span class="req">*</span> <span class="opt">include country code</span></label>
        <input type="tel" name="phone" placeholder="+91 98765 43210"
               value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>" required>
    </div>
    <div class="field">
        <label>Date of Birth <span class="req">*</span></label>
        <input type="date" name="dob" value="<?php echo htmlspecialchars($data['dob'] ?? ''); ?>" required>
    </div>
    <div class="field">
        <label>Gender <span class="req">*</span></label>
        <select name="gender" required>
            <option value="">Select gender</option>
            <option value="male"       <?php echo ($data['gender'] ?? '') === 'male'       ? 'selected' : ''; ?>>Male</option>
            <option value="female"     <?php echo ($data['gender'] ?? '') === 'female'     ? 'selected' : ''; ?>>Female</option>
            <option value="non-binary" <?php echo ($data['gender'] ?? '') === 'non-binary' ? 'selected' : ''; ?>>Non-binary</option>
            <option value="prefer_not" <?php echo ($data['gender'] ?? '') === 'prefer_not' ? 'selected' : ''; ?>>Prefer not to say</option>
        </select>
    </div>
    <div class="field">
        <label>Location <span class="opt">(optional)</span></label>
        <input type="text" name="location" placeholder="City, State / Country"
               value="<?php echo htmlspecialchars($data['location'] ?? ''); ?>">
    </div>
    <div class="field">
        <label>Your Timezone <span class="opt">(optional)</span></label>
        <select name="timezone">
            <?php
            $tz_options = [
                'Asia/Kolkata'        => 'IST — India Standard Time (UTC+5:30)',
                'Asia/Colombo'        => 'Sri Lanka (UTC+5:30)',
                'Asia/Dubai'          => 'GST — Gulf Standard Time (UTC+4)',
                'Europe/London'       => 'GMT — Greenwich Mean Time (UTC+0)',
                'America/New_York'    => 'EST — Eastern US (UTC-5)',
                'America/Los_Angeles' => 'PST — Pacific US (UTC-8)',
                'Asia/Singapore'      => 'SGT — Singapore (UTC+8)',
                'Australia/Sydney'    => 'AEST — Sydney (UTC+10)',
                'Europe/Berlin'       => 'CET — Central Europe (UTC+1)',
            ];
            foreach ($tz_options as $val => $label):
                $sel = ($data['timezone'] ?? 'Asia/Kolkata') === $val ? 'selected' : '';
                echo "<option value=\"$val\" $sel>" . htmlspecialchars($label) . "</option>";
            endforeach;
            ?>
        </select>
    </div>
</div>

<!-- ✅ STEP 1: no Back button needed, single submit -->
<div class="reg-nav">
    <div></div>
    <button type="submit" name="action" value="next" class="btn-next">
        Next: Art &amp; Expertise
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
    </button>
</div>
</form>

<?php elseif ($step === 2): ?>
<!-- ═══════════════════════════════════════════════════
     STEP 2: Art & Expertise
════════════════════════════════════════════════════ -->
<div class="reg-section-title">Art &amp; Expertise</div>
<div class="reg-section-sub">Tell us what you teach and how long you have been doing it.</div>

<form method="POST">
<div class="field-grid">
    <div class="field">
        <label>Primary Art Category <span class="req">*</span></label>
        <select name="art_category" required>
            <option value="">Select a category</option>
            <?php
            $cats = ['Classical Dance','Folk Dance','Music — Vocal','Music — Instrumental',
                     'Classical Theatre','Contemporary Theatre','Visual Arts','Martial Arts',
                     'Yoga & Meditation','Craft & Handicraft','Film & Acting','Other'];
            foreach ($cats as $c):
                $sel = ($data['art_category'] ?? '') === $c ? 'selected' : '';
                echo "<option value=\"$c\" $sel>$c</option>";
            endforeach;
            ?>
        </select>
    </div>
    <div class="field">
        <label>Specific Art Form <span class="req">*</span></label>
        <input type="text" name="art_form" placeholder="e.g. Kathakali, Hindustani Vocal, Bharatanatyam"
               value="<?php echo htmlspecialchars($data['art_form'] ?? ''); ?>" required>
    </div>
    <div class="field">
        <label>Years of Experience <span class="req">*</span></label>
        <select name="years_experience" required>
            <option value="">Select range</option>
            <?php
            $yrs = ['0–2 years','3–5 years','6–10 years','11–20 years','20+ years'];
            foreach ($yrs as $y):
                $sel = ($data['years_experience'] ?? '') === $y ? 'selected' : '';
                echo "<option value=\"$y\" $sel>$y</option>";
            endforeach;
            ?>
        </select>
    </div>
    <div class="field">
        <label>Teaching Since <span class="opt">(optional)</span></label>
        <input type="number" name="teaching_since" placeholder="e.g. 2010" min="1950" max="2025"
               value="<?php echo htmlspecialchars($data['teaching_since'] ?? ''); ?>">
    </div>
    <div class="field span2">
        <label>Notable Awards &amp; Recognitions <span class="opt">(optional)</span></label>
        <textarea name="awards" placeholder="Any awards, media features, or notable performances..."><?php echo htmlspecialchars($data['awards'] ?? ''); ?></textarea>
    </div>
</div>

<!-- ✅ STEP 2: both buttons inside ONE form, differentiated by name+value -->
<div class="reg-nav">
    <button type="submit" name="action" value="back" class="btn-back">Back</button>
    <button type="submit" name="action" value="next" class="btn-next">
        Next: Qualifications
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
    </button>
</div>
</form>

<?php elseif ($step === 3): ?>
<!-- ═══════════════════════════════════════════════════
     STEP 3: Qualifications
════════════════════════════════════════════════════ -->
<div class="reg-section-title">Qualifications</div>
<div class="reg-section-sub">All optional — but sharing your training background builds student trust.</div>

<form method="POST" enctype="multipart/form-data">
<div class="field-grid">
    <div class="field">
        <label>Highest Art Qualification <span class="opt">(optional)</span></label>
        <select name="qualification">
            <option value="">Select qualification</option>
            <?php
            $quals = ['Self-Taught','Gurukul / Traditional Training','Certificate','Diploma',
                      "Bachelor's Degree","Master's Degree",'PhD','Other'];
            foreach ($quals as $q):
                $sel = ($data['qualification'] ?? '') === $q ? 'selected' : '';
                echo "<option value=\"$q\" $sel>$q</option>";
            endforeach;
            ?>
        </select>
    </div>
    <div class="field">
        <label>Name of Institution / Art School <span class="opt">(optional)</span></label>
        <input type="text" name="institution" placeholder="e.g. Kerala Kalamandalam"
               value="<?php echo htmlspecialchars($data['institution'] ?? ''); ?>">
    </div>
    <div class="field span2">
        <label>Upload Certificate or Proof of Training <span class="opt">(optional — PDF or image, max 4MB)</span></label>
        <div class="upload-area">
            <label class="upload-label" for="certFile">
                <div class="upload-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#02B393" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>
                <div class="upload-text"><strong>Click to upload</strong> or drag and drop</div>
                <div style="font-size:0.75rem;color:#aac;margin-top:4px;">PDF, JPG, PNG — max 4MB</div>
            </label>
            <input type="file" id="certFile" name="cert_file" accept=".pdf,image/*">
        </div>
    </div>
</div>

<!-- ✅ STEP 3: both buttons inside ONE form -->
<div class="reg-nav">
    <button type="submit" name="action" value="back" class="btn-back">Back</button>
    <button type="submit" name="action" value="next" class="btn-next">
        Next: Teaching Preferences
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
    </button>
</div>
</form>

<?php elseif ($step === 4): ?>
<!-- ═══════════════════════════════════════════════════
     STEP 4: Teaching Preferences
════════════════════════════════════════════════════ -->
<div class="reg-section-title">Teaching Preferences</div>
<div class="reg-section-sub">Help us match you with the right students.</div>

<form method="POST">
<div class="field-grid">
    <div class="field span2">
        <label>Student Level <span class="opt">(optional — select all that apply)</span></label>
        <div class="check-group">
            <?php foreach (['Beginner','Intermediate','Advanced','Professional'] as $lvl):
                $checked = in_array($lvl, explode(',', $data['student_levels'] ?? '')) ? 'checked' : '';
            ?>
            <div class="check-chip">
                <input type="checkbox" name="student_levels[]" id="lvl_<?php echo $lvl; ?>"
                       value="<?php echo $lvl; ?>" <?php echo $checked; ?>>
                <label for="lvl_<?php echo $lvl; ?>"><?php echo $lvl; ?></label>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="field">
        <label>Age Group Preference <span class="opt">(optional)</span></label>
        <select name="age_group_pref">
            <option value="">No preference</option>
            <?php foreach (['Kids (5–12)','Teens (13–17)','Adults (18+)','Seniors (60+)','Anyone'] as $ag):
                $sel = ($data['age_group_pref'] ?? '') === $ag ? 'selected' : '';
                echo "<option value=\"$ag\" $sel>$ag</option>";
            endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label>Languages You Can Teach In <span class="opt">(optional)</span></label>
        <input type="text" name="languages" placeholder="e.g. Malayalam, English, Hindi"
               value="<?php echo htmlspecialchars($data['languages'] ?? ''); ?>">
    </div>
    <div class="field span2">
        <label>Equipment Students Need <span class="opt">(optional)</span></label>
        <input type="text" name="equipment_needed" placeholder="e.g. Yoga mat, Tabla, Watercolours, iPad"
               value="<?php echo htmlspecialchars($data['equipment_needed'] ?? ''); ?>">
    </div>
</div>

<!-- ✅ STEP 4: both buttons inside ONE form -->
<div class="reg-nav">
    <button type="submit" name="action" value="back" class="btn-back">Back</button>
    <button type="submit" name="action" value="next" class="btn-next">
        Next: Portfolio
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
        </svg>
    </button>
</div>
</form>

<?php elseif ($step === 5): ?>
<!-- ═══════════════════════════════════════════════════
     STEP 5: Portfolio & Presence
════════════════════════════════════════════════════ -->
<div class="reg-section-title">Portfolio &amp; Presence</div>
<div class="reg-section-sub">Show your work. All optional — but a strong portfolio gets more students.</div>

<form method="POST" enctype="multipart/form-data">
<div class="field-grid">
    <div class="field span2">
        <label>Short Bio <span class="opt">(optional — 2–3 sentences about your teaching style)</span></label>
        <textarea name="bio" placeholder="I have been teaching Kathakali for 15 years. My classes focus on..."><?php echo htmlspecialchars($data['bio'] ?? ''); ?></textarea>
    </div>
    <div class="field">
        <label>Instagram Handle <span class="opt">(optional)</span></label>
        <input type="text" name="instagram" placeholder="@yourhandle"
               value="<?php echo htmlspecialchars($data['instagram'] ?? ''); ?>">
    </div>
    <div class="field">
        <label>YouTube Channel <span class="opt">(optional)</span></label>
        <input type="url" name="youtube" placeholder="https://youtube.com/..."
               value="<?php echo htmlspecialchars($data['youtube'] ?? ''); ?>">
    </div>
    <div class="field">
        <label>LinkedIn Profile <span class="opt">(optional)</span></label>
        <input type="url" name="linkedin" placeholder="https://linkedin.com/in/..."
               value="<?php echo htmlspecialchars($data['linkedin'] ?? ''); ?>">
    </div>
    <div class="field">
        <label>Portfolio Website <span class="opt">(optional)</span></label>
        <input type="url" name="portfolio_url" placeholder="https://..."
               value="<?php echo htmlspecialchars($data['portfolio_url'] ?? ''); ?>">
    </div>
    <div class="field span2">
        <label>Portfolio Images <span class="opt">(optional — upload up to 3 images of your work)</span></label>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="upload-area" style="padding:16px">
                <label class="upload-label" for="portfolio_<?php echo $i; ?>">
                    <div class="upload-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#02B393" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <div style="font-size:0.75rem;color:#aac;margin-top:4px;">Image <?php echo $i; ?></div>
                </label>
                <input type="file" id="portfolio_<?php echo $i; ?>" name="portfolio_<?php echo $i; ?>" accept="image/*">
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- ✅ STEP 5: both buttons inside ONE form -->
<div class="reg-nav">
    <button type="submit" name="action" value="back" class="btn-back">Back</button>
    <button type="submit" name="action" value="next" class="btn-next">
        Submit Application
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
    </button>
</div>
</form>

<?php endif; ?>

</div><!-- /reg-card -->

<script>
// Avatar preview on file select
var avatarFile = document.getElementById('avatarFile');
if (avatarFile) {
    avatarFile.addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            var preview = document.getElementById('avatarPreview');
            if (preview) {
                preview.innerHTML = '<img src="' + e.target.result + '" style="width:100%;height:100%;object-fit:cover">';
            }
        };
        reader.readAsDataURL(file);
    });
}

// Show filename label after selecting cert / portfolio files
document.querySelectorAll('.upload-area input[type=file]').forEach(function (input) {
    input.addEventListener('change', function () {
        var textEl = this.closest('.upload-area').querySelector('.upload-text, div[style]');
        var name   = this.files[0] ? this.files[0].name : '';
        if (name && textEl) textEl.innerHTML = '<strong>' + name + '</strong>';
    });
});
</script>
</body>
</html>