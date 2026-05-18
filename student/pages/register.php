<?php
/**
 * Digital Art School - Student Registration Page
 * Production-grade: form state persistence, real-time validation, password strength
 */

require_once '../includes/config.php';
require_once '../includes/moodle_api_helper.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error_message   = '';
$success_message = '';

// Preserve all POST values on error
$post = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Capture everything for re-fill (except passwords)
    $fields = ['full_name','email','age','location','art_category','art_discipline',
               'secondary_interest','skill_level','years_of_experience','previous_training',
               'learning_purpose','time_commitment','preferred_schedule','specific_goals',
               'physical_constraints','learning_accommodations'];
    foreach ($fields as $f) {
        $post[$f] = sanitizeInput($_POST[$f] ?? '');
    }

    $password         = $_POST['password']         ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($post['full_name']) || empty($post['email']) || empty($password)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!validateEmail($post['email'])) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
        $error_message = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $conn = getDatabaseConnection();
        if ($conn) {
            $check_stmt = $conn->prepare("SELECT student_id FROM students WHERE email = ?");
            $check_stmt->bind_param("s", $post['email']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $error_message = 'This email is already registered. <a href="login.php" style="color:inherit;font-weight:700;">Sign in instead?</a>';
            } else {
                $showcase_file = '';
                if (isset($_FILES['showcase_file']) && $_FILES['showcase_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp  = $_FILES['showcase_file']['tmp_name'];
                    $file_name = $_FILES['showcase_file']['name'];
                    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (in_array($file_ext, ALLOWED_FILE_TYPES) && $_FILES['showcase_file']['size'] <= MAX_FILE_SIZE) {
                        $new_filename = uniqid('showcase_') . '.' . $file_ext;
                        $upload_path  = UPLOAD_DIR . $new_filename;
                        if (!file_exists(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                        if (move_uploaded_file($file_tmp, $upload_path)) $showcase_file = $new_filename;
                    }
                }

                $password_hash = hashPassword($password);
                $stmt = $conn->prepare("
                    INSERT INTO students (
                        full_name, email, password_hash, age, location,
                        art_category, art_discipline, secondary_interest,
                        skill_level, years_of_experience, previous_training, showcase_file,
                        learning_purpose, time_commitment, preferred_schedule, specific_goals,
                        physical_constraints, learning_accommodations
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssississdssssssss",
                    $post['full_name'], $post['email'], $password_hash,
                    $post['age'], $post['location'],
                    $post['art_category'], $post['art_discipline'], $post['secondary_interest'],
                    $post['skill_level'], $post['years_of_experience'], $post['previous_training'],
                    $showcase_file,
                    $post['learning_purpose'], $post['time_commitment'], $post['preferred_schedule'],
                    $post['specific_goals'], $post['physical_constraints'], $post['learning_accommodations']
                );

                if ($stmt->execute()) {
                    $student_id  = $stmt->insert_id;
                    $studentData = array_merge(['student_id' => $student_id, 'password_hash' => $password_hash], $post);
                    $moodleResult = syncStudentToMoodle($studentData);
                    if ($moodleResult['success']) {
                        $u = $conn->prepare("UPDATE students SET moodle_synced_at = CURRENT_TIMESTAMP WHERE student_id = ?");
                        $u->bind_param("i", $student_id); $u->execute(); $u->close();
                    }
                    $_SESSION['student_id'] = $student_id;
                    $_SESSION['email']      = $post['email'];
                    $_SESSION['full_name']  = $post['full_name'];
                    $stmt->close(); closeDatabaseConnection($conn);
                    header('Location: student-dashboard.php');
                    exit;
                } else {
                    $error_message = 'Registration failed. Please try again.';
                }
                $stmt->close();
            }
            $check_stmt->close();
            closeDatabaseConnection($conn);
        } else {
            $error_message = 'Database connection error. Please try again later.';
        }
    }
}

// Helper: re-fill value
function rv($key) {
    global $post;
    return htmlspecialchars($post[$key] ?? '');
}
function sel($key, $val) {
    global $post;
    return (isset($post[$key]) && $post[$key] === $val) ? 'selected' : '';
}

$stepLabels = ['Account','Art & Interests','Experience','Goals'];
$totalSteps = count($stepLabels);

// Which step had the error? (for JS to jump to)
$errorStep = 1;
if ($error_message && !empty($post)) {
    if (empty($post['art_category'])) $errorStep = 2;
    elseif (empty($post['skill_level'])) $errorStep = 3;
    elseif (empty($post['learning_purpose'])) $errorStep = 4;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Digital Art School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --blue:   #0366B0;
      --teal:   #02B393;
      --green:  #A3CE47;
      --gold:   #F3C73B;
      --dark:   #0f1e2d;
      --mid:    #4d6b85;
      --light:  #f0faf8;
      --white:  #ffffff;
      --grad:   linear-gradient(135deg, #0366B0 0%, #02B393 100%);
      --grad-g: linear-gradient(135deg, #02B393 0%, #005bea 0%, 100%);
      --radius: 16px;
      --font-display: 'Playfair Display', Georgia, serif;
      --font-body:    'DM Sans', sans-serif;
      --error:   #c0392b;
      --error-bg: rgba(192,57,43,0.06);
      --pw-weak:   #e74c3c;
      --pw-fair:   #f39c12;
      --pw-good:   #2ecc71;
      --pw-strong: #02B393;
    }

    body {
      font-family: var(--font-body);
      background: var(--light);
      min-height: 100vh;
      background-image: url('../assets/images/community_portal_bg-52339207.png');
      background-size: cover; background-position: center; background-attachment: fixed;
    }
    body::before {
      content:''; position:fixed; inset:0;
      background: rgba(240,250,248,0.48); z-index:0;
    }

    /* ── HEADER ── */
    .reg-header {
      position: sticky; top:0; z-index:100;
      background: rgba(255,255,255,0.94); backdrop-filter: blur(14px);
      border-bottom: 3px solid transparent;
      border-image: linear-gradient(90deg,#005bea,#02B393,#0366B0) 1;
      padding: 12px 40px;
      display: flex; align-items:center; justify-content:space-between;
      box-shadow: 0 2px 20px rgba(3,102,176,0.07);
    }
    .brand-logos { display:flex; align-items:center; gap:16px; }
    .brand-logos img { height:54px; width:auto; object-fit:contain; }
    .brand-div { width:1px; height:36px; background:rgba(3,102,176,0.15); }
    .login-link { font-size:0.84rem; color:var(--mid); }
    .login-link a { color:var(--blue); font-weight:600; text-decoration:none; }
    .login-link a:hover { text-decoration:underline; }

    /* ── LAYOUT ── */
    .page-body {
      position:relative; z-index:1;
      display: grid; grid-template-columns: 268px 1fr;
      gap: 0; max-width: 1040px;
      margin: 32px auto 60px; padding: 0 24px;
      align-items: start;
    }

    /* ── SIDEBAR ── */
    .reg-sidebar {
      background: var(--grad); border-radius:20px; padding:34px 26px;
      position: sticky; top: 96px; color:white; overflow:hidden;
    }
    .reg-sidebar::after {
      content:''; position:absolute; width:200px; height:200px; border-radius:50%;
      border:1.5px solid rgba(255,255,255,0.10); bottom:-60px; right:-60px;
    }
    .sidebar-title {
      font-family: var(--font-display); font-weight:700; font-size:1.2rem; margin-bottom:4px;
    }
    .sidebar-sub { font-size:0.76rem; color:rgba(255,255,255,0.68); margin-bottom:28px; line-height:1.5; }

    .step-list { list-style:none; }
    .step-item {
      display:flex; align-items:center; gap:11px; padding:9px 0; position:relative;
    }
    .step-item:not(:last-child)::after {
      content:''; position:absolute; left:14px; top:38px;
      width:2px; height:calc(100% - 10px); background:rgba(255,255,255,0.18);
    }
    .step-num {
      width:30px; height:30px; flex-shrink:0; border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-weight:700; font-size:0.8rem; transition:all 0.35s; z-index:1;
    }
    .step-num.future { background:rgba(255,255,255,0.14); color:rgba(255,255,255,0.55); }
    .step-num.active { background:white; color:var(--blue); box-shadow:0 0 0 5px rgba(255,255,255,0.22); }
    .step-num.done   { background:rgba(255,255,255,0.92); color:var(--teal); }
    .step-name { font-size:0.82rem; font-weight:500; color:rgba(255,255,255,0.85); transition:all 0.2s; }
    .step-name.active { font-weight:700; color:white; }
    .step-name.future { opacity:0.52; }

    /* Progress ring */
    .sidebar-progress {
      margin-top:24px; padding-top:18px;
      border-top:1px solid rgba(255,255,255,0.15);
    }
    .progress-bar-track {
      height:5px; background:rgba(255,255,255,0.15); border-radius:10px; overflow:hidden;
    }
    .progress-bar-fill {
      height:100%; background:white; border-radius:10px;
      transition: width 0.45s cubic-bezier(0.4,0,0.2,1);
    }
    .progress-label {
      font-size:0.68rem; color:rgba(255,255,255,0.6);
      display:flex; justify-content:space-between; margin-bottom:6px;
    }
    .sidebar-footer { font-size:0.66rem; color:rgba(255,255,255,0.38); margin-top:24px; }

    /* ── FORM CARD ── */
    .reg-card {
      background:white; border-radius:20px; padding:42px 46px; margin-left:22px;
      box-shadow: 0 8px 40px rgba(3,102,176,0.09);
    }

    .step-title {
      font-family: var(--font-display); font-weight:800; font-size:1.45rem;
      color:var(--dark); margin-bottom:4px;
    }
    .step-sub { font-size:0.86rem; color:var(--mid); margin-bottom:24px; line-height:1.55; }

    /* ── ALERT ── */
    .alert {
      display:flex; align-items:flex-start; gap:10px; padding:13px 16px;
      border-radius:12px; font-size:0.84rem; margin-bottom:22px;
      animation: alertPop 0.3s ease;
    }
    @keyframes alertPop { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .alert-error { background:var(--error-bg); color:var(--error); border:1px solid rgba(192,57,43,0.18); }
    .alert svg { flex-shrink:0; margin-top:1px; }

    /* ── GRID ── */
    .field-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px 20px; }
    .span2 { grid-column:1/span 2; }
    @media (max-width:540px) { .field-grid{grid-template-columns:1fr} .span2{grid-column:1} }

    /* ── FIELD ── */
    .field { display:flex; flex-direction:column; gap:5px; }
    .field label {
      font-size:0.72rem; font-weight:700; text-transform:uppercase;
      letter-spacing:0.9px; color:var(--mid);
    }
    .req { color:#e74c3c; margin-left:2px; }
    .opt { text-transform:none; letter-spacing:0; font-weight:400; color:#b0c4d0; margin-left:4px; font-size:0.7rem; }

    .input-wrap { position:relative; }
    .input-wrap input,
    .field select,
    .field textarea {
      width:100%; padding:11px 14px;
      border:1.5px solid rgba(3,102,176,0.17); border-radius:var(--radius);
      font-family:var(--font-body); font-size:0.91rem;
      color:var(--dark); background:var(--light); outline:none;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .input-wrap input { padding-right:44px; }
    .input-wrap.no-icon input { padding-right:14px; }
    .input-wrap input:focus,
    .field select:focus, .field textarea:focus {
      border-color:var(--teal); box-shadow:0 0 0 4px rgba(2,179,147,0.10); background:white;
    }
    .input-wrap input.input-error, .field select.input-error, .field textarea.input-error {
      border-color:var(--error); background:rgba(192,57,43,0.03);
    }
    .input-wrap input.input-ok, .field select.input-ok { border-color:var(--teal); }
    .input-wrap input::placeholder, .field textarea::placeholder { color:#b5cad6; }
    .field textarea { resize:vertical; min-height:82px; }

    /* Show/hide password toggle */
    .pw-toggle {
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      background:none; border:none; cursor:pointer; color:var(--mid);
      display:flex; padding:3px; transition:color 0.2s;
    }
    .pw-toggle:hover { color:var(--blue); }

    /* Check icon in input */
    .input-check {
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      color:var(--teal); display:none; pointer-events:none;
    }
    .input-wrap input.input-ok ~ .input-check { display:block; }

    /* ── PASSWORD STRENGTH ── */
    .pw-strength {
      display:none; margin-top:6px;
    }
    .pw-strength.visible { display:block; }
    .pw-bars {
      display:flex; gap:4px; margin-bottom:4px;
    }
    .pw-bar {
      flex:1; height:4px; border-radius:4px;
      background:rgba(3,102,176,0.10); transition: background 0.3s;
    }
    .pw-label { font-size:0.69rem; font-weight:600; }

    /* ── FIELD INLINE ERROR ── */
    .field-error {
      font-size:0.72rem; color:var(--error); display:none;
      align-items:center; gap:4px; animation: alertPop 0.2s ease;
    }
    .field-error.show { display:flex; }

    /* ── DETECT LOCATION BTN ── */
    .btn-detect {
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 11px; margin-top:5px;
      background:rgba(3,102,176,0.06); border:1.5px solid rgba(3,102,176,0.16);
      border-radius:8px; font-family:var(--font-body); font-size:0.76rem;
      font-weight:600; color:var(--blue); cursor:pointer; transition:all 0.2s;
    }
    .btn-detect:hover { background:rgba(3,102,176,0.11); }
    .btn-detect:disabled { opacity:0.55; cursor:not-allowed; }

    /* ── UPLOAD ZONE ── */
    .upload-zone {
      border:2px dashed rgba(3,102,176,0.18); border-radius:var(--radius);
      padding:20px; text-align:center; cursor:pointer;
      background:var(--light); transition:all 0.2s;
    }
    .upload-zone:hover { border-color:var(--teal); background:rgba(2,179,147,0.03); }
    .upload-zone.has-file { border-color:var(--teal); border-style:solid; background:rgba(2,179,147,0.04); }
    .upload-zone input[type=file] { display:none; }
    .upload-zone label { cursor:pointer; display:block; }
    .upload-zone-text { font-size:0.83rem; color:var(--mid); margin-top:7px; }
    .upload-zone-text strong { color:var(--dark); }

    /* ── SEP ── */
    .sep { height:1px; background:rgba(3,102,176,0.08); margin:20px 0; }

    /* ── FORM NAV ── */
    .form-nav {
      display:flex; align-items:center; justify-content:space-between;
      margin-top:28px; gap:12px;
    }
    .btn-back {
      padding:11px 22px; background:none; border:1.5px solid rgba(3,102,176,0.18);
      border-radius:var(--radius); font-family:var(--font-body); font-weight:600;
      font-size:0.87rem; color:var(--mid); cursor:pointer; transition:all 0.2s;
      display:flex; align-items:center; gap:6px;
    }
    .btn-back:hover { border-color:var(--blue); color:var(--dark); }
    .btn-next {
      padding:12px 26px; background:var(--grad); border:none; border-radius:var(--radius);
      font-family:var(--font-display); font-weight:700; font-size:0.9rem; color:white;
      cursor:pointer; display:flex; align-items:center; gap:8px;
      transition:opacity 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow:0 4px 14px rgba(3,102,176,0.22);
    }
    .btn-next:hover { opacity:0.92; transform:translateY(-2px); box-shadow:0 6px 20px rgba(3,102,176,0.3); }
    .btn-next:active { transform:translateY(0); }
    .btn-submit {
      padding:12px 30px; background:var(--grad-g); border:none; border-radius:var(--radius);
      font-family:var(--font-display); font-weight:700; font-size:0.9rem; color:white;
      cursor:pointer; display:flex; align-items:center; gap:8px;
      transition:opacity 0.2s, transform 0.15s;
      box-shadow:0 4px 14px rgba(2,179,147,0.28);
      position:relative; overflow:hidden;
    }
    .btn-submit:hover { opacity:0.92; transform:translateY(-2px); }
    .btn-submit.loading { pointer-events:none; }
    .btn-submit .btn-spinner {
      display:none; width:16px; height:16px;
      border:2px solid rgba(255,255,255,0.35); border-top-color:white;
      border-radius:50%; animation:spin 0.7s linear infinite;
    }
    .btn-submit.loading .btn-text { display:none; }
    .btn-submit.loading .btn-spinner { display:inline-block; }
    @keyframes spin { to{transform:rotate(360deg)} }

    /* ── FORM STEP ── */
    .form-step { display:none; }
    .form-step.active {
      display:block;
      animation: stepIn 0.38s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes stepIn { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

    /* ── CHECKLIST hints in sidebar ── */
    .sidebar-hint {
      margin-top:14px; font-size:0.72rem; color:rgba(255,255,255,0.55);
      line-height:1.6;
    }
    .sidebar-hint strong { color:rgba(255,255,255,0.85); }

    /* ── RESPONSIVE ── */
    @media (max-width:768px) {
      .page-body { grid-template-columns:1fr; padding:0 16px; }
      .reg-sidebar { position:static; margin-bottom:0; }
      .reg-card { margin-left:0; margin-top:14px; padding:26px 20px; }
      .reg-header { padding:12px 20px; }
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header class="reg-header">
  <div class="brand-logos">
    <img src="../assets/images/DUK Logo.png" alt="DUK Logo" onerror="this.style.display='none'">
    <div class="brand-div"></div>
    <img src="../assets/images/cdtc_logo.png" alt="CDTC Logo" onerror="this.style.display='none'">
  </div>
  <div class="login-link">Already a student? <a href="login.php">Sign in</a></div>
</header>

<!-- BODY -->
<div class="page-body">

  <!-- SIDEBAR -->
  <aside class="reg-sidebar">
    <div class="sidebar-title">Create Account</div>
    <div class="sidebar-sub">Join thousands of students learning traditional arts online.</div>

    <ul class="step-list" id="sidebarSteps">
      <?php foreach ($stepLabels as $i => $label): $n = $i + 1; ?>
      <li class="step-item">
        <div class="step-num future" id="snum-<?php echo $n; ?>"><?php echo $n; ?></div>
        <span class="step-name future" id="slbl-<?php echo $n; ?>"><?php echo $label; ?></span>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="sidebar-progress">
      <div class="progress-label">
        <span>Progress</span>
        <span id="progressPct">0%</span>
      </div>
      <div class="progress-bar-track">
        <div class="progress-bar-fill" id="progressFill" style="width:0%"></div>
      </div>
    </div>

    <div class="sidebar-hint" id="sidebarHint">
      <strong>Step 1:</strong> Your account details — used to log in and identify you on the platform.
    </div>

    <div class="sidebar-footer">© Digital Art School — Preserving tradition through technology</div>
  </aside>

  <!-- FORM CARD -->
  <main class="reg-card">

    <?php if ($error_message): ?>
    <div class="alert alert-error" id="serverAlert">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span><?php echo $error_message; ?></span>
    </div>
    <?php endif; ?>

    <form id="regForm" method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>

      <!-- ═══ STEP 1: Account ═══ -->
      <div class="form-step" data-step="1">
        <div class="step-title">Basic Information</div>
        <div class="step-sub">Let's set up your account — these details are used to log in.</div>

        <div class="field-grid">

          <div class="field span2">
            <label>Full Name <span class="req">*</span></label>
            <div class="input-wrap no-icon">
              <input type="text" name="full_name" id="full_name" placeholder="Your full name"
                     value="<?php echo rv('full_name'); ?>" required autocomplete="name">
              <span class="input-check">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              </span>
            </div>
            <div class="field-error" id="err-full_name"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field span2">
            <label>Email Address <span class="req">*</span></label>
            <div class="input-wrap no-icon">
              <input type="email" name="email" id="reg_email" placeholder="your.email@example.com"
                     value="<?php echo rv('email'); ?>" required autocomplete="email">
              <span class="input-check">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
              </span>
            </div>
            <div class="field-error" id="err-reg_email"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field">
            <label>Password <span class="req">*</span></label>
            <div class="input-wrap">
              <input type="password" name="password" id="reg_password" placeholder="Min. 8 characters" required autocomplete="new-password">
              <button type="button" class="pw-toggle" data-target="reg_password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <!-- Password strength meter -->
            <div class="pw-strength" id="pwStrength">
              <div class="pw-bars">
                <div class="pw-bar" id="pb1"></div>
                <div class="pw-bar" id="pb2"></div>
                <div class="pw-bar" id="pb3"></div>
                <div class="pw-bar" id="pb4"></div>
              </div>
              <span class="pw-label" id="pwLabel" style="color:var(--mid)"></span>
            </div>
            <div class="field-error" id="err-reg_password"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field">
            <label>Confirm Password <span class="req">*</span></label>
            <div class="input-wrap">
              <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required autocomplete="new-password">
              <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Show password">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="field-error" id="err-confirm_password"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field">
            <label>Age <span class="opt">(optional)</span></label>
            <div class="input-wrap no-icon">
              <input type="number" name="age" id="age" min="5" max="100" placeholder="Your age" value="<?php echo rv('age'); ?>">
            </div>
          </div>

          <div class="field">
            <label>Location <span class="opt">(optional)</span></label>
            <div class="input-wrap no-icon">
              <input type="text" id="location" name="location" placeholder="City, Country" value="<?php echo rv('location'); ?>">
            </div>
            <button type="button" class="btn-detect" id="detectBtn" onclick="detectLocation()">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
              Detect my location
            </button>
          </div>

        </div><!-- /field-grid -->

        <div class="form-nav">
          <div></div>
          <button type="button" class="btn-next" data-step="1">
            Next: Art &amp; Interests
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </div><!-- /step 1 -->

      <!-- ═══ STEP 2: Art & Interests ═══ -->
      <div class="form-step" data-step="2">
        <div class="step-title">Art &amp; Interests</div>
        <div class="step-sub">Tell us what draws you to the arts — this helps match you with the right teacher.</div>

        <div class="field-grid">
          <div class="field">
            <label>Art Category <span class="req">*</span></label>
            <select name="art_category" id="art_category" required onchange="updateDisciplines(this.value)">
              <option value="">Select category…</option>
              <option value="Classical Dance"     <?php echo sel('art_category','Classical Dance'); ?>>Classical Dance</option>
              <option value="Vocal Music"         <?php echo sel('art_category','Vocal Music'); ?>>Vocal Music</option>
              <option value="Instrumental Music"  <?php echo sel('art_category','Instrumental Music'); ?>>Instrumental Music</option>
              <option value="Visual Arts"         <?php echo sel('art_category','Visual Arts'); ?>>Visual Arts</option>
              <option value="Theatre"             <?php echo sel('art_category','Theatre'); ?>>Theatre</option>
              <option value="Martial Arts"        <?php echo sel('art_category','Martial Arts'); ?>>Martial Arts</option>
            </select>
            <div class="field-error" id="err-art_category"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field">
            <label>Art Discipline <span class="req">*</span></label>
            <select name="art_discipline" id="art_discipline" required>
              <option value="">Select category first…</option>
            </select>
            <div class="field-error" id="err-art_discipline"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field span2">
            <label>Secondary Interest <span class="opt">(optional)</span></label>
            <div class="input-wrap no-icon">
              <input type="text" name="secondary_interest" placeholder="Any other art forms you're curious about"
                     value="<?php echo rv('secondary_interest'); ?>">
            </div>
          </div>
        </div>

        <div class="form-nav">
          <button type="button" class="btn-back" data-step-back="2">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back
          </button>
          <button type="button" class="btn-next" data-step="2">
            Next: Experience
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </div><!-- /step 2 -->

      <!-- ═══ STEP 3: Experience ═══ -->
      <div class="form-step" data-step="3">
        <div class="step-title">Experience &amp; Background</div>
        <div class="step-sub">Help your instructor tailor lessons to your level.</div>

        <div class="field-grid">
          <div class="field">
            <label>Skill Level <span class="req">*</span></label>
            <select name="skill_level" id="skill_level" required>
              <option value="">Select level…</option>
              <option value="complete_beginner" <?php echo sel('skill_level','complete_beginner'); ?>>Complete Beginner</option>
              <option value="basic"             <?php echo sel('skill_level','basic'); ?>>Basic</option>
              <option value="intermediate"      <?php echo sel('skill_level','intermediate'); ?>>Intermediate</option>
              <option value="advanced"          <?php echo sel('skill_level','advanced'); ?>>Advanced</option>
            </select>
            <div class="field-error" id="err-skill_level"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field">
            <label>Years of Experience <span class="opt">(optional)</span></label>
            <div class="input-wrap no-icon">
              <input type="number" name="years_of_experience" min="0" max="50" step="0.5" placeholder="0"
                     value="<?php echo rv('years_of_experience'); ?>">
            </div>
          </div>

          <div class="field span2">
            <label>Previous Training <span class="opt">(optional)</span></label>
            <textarea name="previous_training" placeholder="Describe any previous training or teachers you've studied with…"><?php echo rv('previous_training'); ?></textarea>
          </div>

          <div class="field span2">
            <label>Showcase File <span class="opt">(optional — image / video, max 10 MB)</span></label>
            <div class="upload-zone" id="uploadZone">
              <label for="showcase_file">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#02B393" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div class="upload-zone-text"><strong>Click to upload</strong> or drag &amp; drop</div>
                <div style="font-size:0.71rem;color:#b0c4d0;margin-top:3px;">JPG, PNG, PDF, MP4, MOV — max 10 MB</div>
              </label>
              <input type="file" id="showcase_file" name="showcase_file"
                     accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.avi">
            </div>
          </div>
        </div>

        <div class="form-nav">
          <button type="button" class="btn-back" data-step-back="3">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back
          </button>
          <button type="button" class="btn-next" data-step="3">
            Next: Goals
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </button>
        </div>
      </div><!-- /step 3 -->

      <!-- ═══ STEP 4: Goals ═══ -->
      <div class="form-step" data-step="4">
        <div class="step-title">Learning Goals &amp; Schedule</div>
        <div class="step-sub">Almost there — tell us how you'd like to learn.</div>

        <div class="field-grid">
          <div class="field">
            <label>Learning Purpose <span class="req">*</span></label>
            <select name="learning_purpose" id="learning_purpose" required>
              <option value="">Select purpose…</option>
              <option value="hobby"           <?php echo sel('learning_purpose','hobby'); ?>>Hobby / Personal Enrichment</option>
              <option value="professional"    <?php echo sel('learning_purpose','professional'); ?>>Professional Development</option>
              <option value="performance"     <?php echo sel('learning_purpose','performance'); ?>>Performance</option>
              <option value="certification"   <?php echo sel('learning_purpose','certification'); ?>>Certification</option>
              <option value="therapy"         <?php echo sel('learning_purpose','therapy'); ?>>Therapy / Wellness</option>
            </select>
            <div class="field-error" id="err-learning_purpose"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field">
            <label>Time Commitment <span class="opt">(hrs / week)</span></label>
            <div class="input-wrap no-icon">
              <input type="text" name="time_commitment" placeholder="e.g. 5–7 hours" value="<?php echo rv('time_commitment'); ?>">
            </div>
          </div>

          <div class="field span2">
            <label>Preferred Schedule <span class="req">*</span></label>
            <select name="preferred_schedule" id="preferred_schedule" required>
              <option value="">Select schedule…</option>
              <option value="weekday_morning"  <?php echo sel('preferred_schedule','weekday_morning'); ?>>Weekday Morning</option>
              <option value="weekday_evening"  <?php echo sel('preferred_schedule','weekday_evening'); ?>>Weekday Evening</option>
              <option value="weekend_morning"  <?php echo sel('preferred_schedule','weekend_morning'); ?>>Weekend Morning</option>
              <option value="weekend_evening"  <?php echo sel('preferred_schedule','weekend_evening'); ?>>Weekend Evening</option>
              <option value="weekday_all_day"  <?php echo sel('preferred_schedule','weekday_all_day'); ?>>Weekday (Flexible)</option>
              <option value="weekend_all_day"  <?php echo sel('preferred_schedule','weekend_all_day'); ?>>Weekend (Flexible)</option>
            </select>
            <div class="field-error" id="err-preferred_schedule"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><span></span></div>
          </div>

          <div class="field span2">
            <label>Specific Goals <span class="opt">(optional)</span></label>
            <textarea name="specific_goals" placeholder="What do you hope to achieve? Any milestones in mind?"><?php echo rv('specific_goals'); ?></textarea>
          </div>

          <div class="field span2">
            <label>Physical Constraints <span class="opt">(optional)</span></label>
            <textarea name="physical_constraints" placeholder="Any physical limitations your instructor should know about…"><?php echo rv('physical_constraints'); ?></textarea>
          </div>

          <div class="field span2">
            <label>Learning Accommodations <span class="opt">(optional)</span></label>
            <textarea name="learning_accommodations" placeholder="Any specific learning needs or preferences…"><?php echo rv('learning_accommodations'); ?></textarea>
          </div>
        </div>

        <div class="form-nav">
          <button type="button" class="btn-back" data-step-back="4">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            Back
          </button>
          <button type="submit" name="register" class="btn-submit" id="submitBtn">
            <svg class="btn-text" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span class="btn-text">Complete Registration</span>
            <div class="btn-spinner"></div>
          </button>
        </div>
      </div><!-- /step 4 -->

    </form>
  </main>
</div><!-- /page-body -->

<script>
(function () {
'use strict';

/* ══════════════════════════════════════════════════════════
   CONSTANTS
══════════════════════════════════════════════════════════ */
var STORAGE_KEY  = 'das_reg_draft';
var TOTAL_STEPS  = <?php echo $totalSteps; ?>;
var currentStep  = 1;
var SERVER_ERROR = <?php echo $error_message ? 'true' : 'false'; ?>;
var ERROR_STEP   = <?php echo $errorStep; ?>;

var stepHints = [
  '',
  '<strong>Step 1:</strong> Your account details — used to log in and identify you on the platform.',
  '<strong>Step 2:</strong> Your art interests — helps us match you with the right teachers.',
  '<strong>Step 3:</strong> Your experience level — so your instructor can tailor lessons.',
  '<strong>Step 4:</strong> Your learning goals and availability — shapes your study plan.'
];

/* ══════════════════════════════════════════════════════════
   ART DISCIPLINES
══════════════════════════════════════════════════════════ */
var DISCIPLINES = {
  'Classical Dance':    ['Bharatanatyam','Kathakali','Odissi','Kuchipudi','Mohiniyattam','Manipuri','Kathak'],
  'Vocal Music':        ['Carnatic Vocal','Hindustani Classical','Kathakali Sangeetham','Light Music','Devotional','Dhrupad'],
  'Instrumental Music': ['Tabla','Mridangam','Veena','Sitar','Violin','Flute','Harmonium'],
  'Visual Arts':        ['Mural Painting','Traditional Painting','Sculpture','Pottery','Printmaking','Textile Arts'],
  'Theatre':            ['Classical Theatre','Contemporary Theatre','Mime & Movement','Puppetry'],
  'Martial Arts':       ['Kalaripayattu','Silambam','Mardani Khel']
};

window.updateDisciplines = function (cat) {
  var sel = document.getElementById('art_discipline');
  var saved = loadDraft()['art_discipline'] || '';
  sel.innerHTML = '<option value="">Select discipline…</option>';
  (DISCIPLINES[cat] || []).forEach(function (d) {
    var o = document.createElement('option');
    o.value = d; o.textContent = d;
    if (d === saved) o.selected = true;
    sel.appendChild(o);
  });
};

/* ══════════════════════════════════════════════════════════
   SESSION STORAGE DRAFT  (survives page reload / server error)
══════════════════════════════════════════════════════════ */
function saveDraft() {
  var data = {};
  var fields = ['full_name','reg_email','age','location','art_category','art_discipline',
                'secondary_interest','skill_level','years_of_experience','previous_training',
                'learning_purpose','time_commitment','preferred_schedule','specific_goals',
                'physical_constraints','learning_accommodations'];
  fields.forEach(function (id) {
    var el = document.getElementById(id);
    if (el) data[id === 'reg_email' ? 'email' : id] = el.value;
  });
  data['_step'] = currentStep;
  try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch(e) {}
}

function loadDraft() {
  try {
    var raw = sessionStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : {};
  } catch(e) { return {}; }
}

function restoreDraft() {
  var data = loadDraft();
  // Map draft keys → element IDs
  var map = {
    email: 'reg_email', full_name:'full_name', age:'age', location:'location',
    art_category:'art_category', secondary_interest:'secondary_interest',
    skill_level:'skill_level', years_of_experience:'years_of_experience',
    previous_training:'previous_training', learning_purpose:'learning_purpose',
    time_commitment:'time_commitment', preferred_schedule:'preferred_schedule',
    specific_goals:'specific_goals', physical_constraints:'physical_constraints',
    learning_accommodations:'learning_accommodations'
  };
  Object.keys(map).forEach(function (dkey) {
    var el = document.getElementById(map[dkey]);
    if (el && data[dkey] !== undefined) {
      // PHP server-side value takes priority for text inputs on error
      if (!el.value || el.tagName === 'SELECT') el.value = data[dkey];
    }
  });
  // discipline needs category first
  if (data.art_category) {
    updateDisciplines(data.art_category);
    var discEl = document.getElementById('art_discipline');
    if (discEl && data.art_discipline) discEl.value = data.art_discipline;
  }
}

/* Auto-save on any input/change */
document.getElementById('regForm').addEventListener('input',  saveDraft);
document.getElementById('regForm').addEventListener('change', saveDraft);

/* ══════════════════════════════════════════════════════════
   STEP MACHINE
══════════════════════════════════════════════════════════ */
function gotoStep(n, animate) {
  if (n < 1 || n > TOTAL_STEPS) return;
  currentStep = n;

  document.querySelectorAll('.form-step').forEach(function (s) {
    s.classList.remove('active');
    if (parseInt(s.dataset.step) === n) s.classList.add('active');
  });

  // Sidebar indicators
  for (var i = 1; i <= TOTAL_STEPS; i++) {
    var circle = document.getElementById('snum-' + i);
    var label  = document.getElementById('slbl-' + i);
    if (!circle || !label) continue;
    circle.className = 'step-num ' + (i < n ? 'done' : i === n ? 'active' : 'future');
    label.className  = 'step-name ' + (i < n ? 'done' : i === n ? 'active' : 'future');
    if (i < n) {
      circle.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
    } else {
      circle.textContent = i;
    }
  }

  // Progress bar
  var pct = Math.round(((n - 1) / TOTAL_STEPS) * 100);
  var fill = document.getElementById('progressFill');
  var pctEl = document.getElementById('progressPct');
  if (fill) fill.style.width = pct + '%';
  if (pctEl) pctEl.textContent = pct + '%';

  // Sidebar hint
  var hint = document.getElementById('sidebarHint');
  if (hint) hint.innerHTML = stepHints[n] || '';

  saveDraft();
  document.querySelector('.reg-card').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* NEXT buttons */
document.querySelectorAll('.btn-next').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var step = parseInt(this.dataset.step);
    if (validateStep(step)) gotoStep(step + 1);
  });
});
/* BACK buttons */
document.querySelectorAll('.btn-back').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var step = parseInt(this.dataset.stepBack);
    gotoStep(step - 1);
  });
});

/* ══════════════════════════════════════════════════════════
   VALIDATION
══════════════════════════════════════════════════════════ */
function showErr(id, msg) {
  var el = document.getElementById('err-' + id);
  var input = document.getElementById(id) ||
              document.querySelector('[name="' + id + '"]');
  if (el) { el.querySelector('span').textContent = msg; el.classList.add('show'); }
  if (input) { input.classList.add('input-error'); input.classList.remove('input-ok'); }
}
function clearErr(id) {
  var el = document.getElementById('err-' + id);
  var input = document.getElementById(id) ||
              document.querySelector('[name="' + id + '"]');
  if (el) el.classList.remove('show');
  if (input) input.classList.remove('input-error');
}
function markOk(id) {
  var input = document.getElementById(id) ||
              document.querySelector('[name="' + id + '"]');
  if (input) { input.classList.add('input-ok'); input.classList.remove('input-error'); }
}
function validEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }

function validateStep(n) {
  var ok = true;
  if (n === 1) {
    var name = document.getElementById('full_name');
    var email = document.getElementById('reg_email');
    var pw    = document.getElementById('reg_password');
    var cpw   = document.getElementById('confirm_password');
    if (!name.value.trim())         { showErr('full_name', 'Your name is required.'); ok = false; }
    else                            { clearErr('full_name'); markOk('full_name'); }
    if (!email.value.trim())        { showErr('reg_email', 'Email is required.'); ok = false; }
    else if (!validEmail(email.value)) { showErr('reg_email', 'Enter a valid email address.'); ok = false; }
    else                            { clearErr('reg_email'); markOk('reg_email'); }
    if (!pw.value)                  { showErr('reg_password', 'Password is required.'); ok = false; }
    else if (pw.value.length < 8)   { showErr('reg_password', 'Password must be at least 8 characters.'); ok = false; }
    else                            { clearErr('reg_password'); }
    if (!cpw.value)                 { showErr('confirm_password', 'Please confirm your password.'); ok = false; }
    else if (cpw.value !== pw.value){ showErr('confirm_password', 'Passwords do not match.'); ok = false; }
    else                            { clearErr('confirm_password'); markOk('confirm_password'); }
  }
  if (n === 2) {
    var cat  = document.getElementById('art_category');
    var disc = document.getElementById('art_discipline');
    if (!cat.value)  { showErr('art_category',  'Please select an art category.'); ok = false; }
    else             { clearErr('art_category'); }
    if (!disc.value) { showErr('art_discipline','Please select a discipline.'); ok = false; }
    else             { clearErr('art_discipline'); }
  }
  if (n === 3) {
    var sl = document.getElementById('skill_level');
    if (!sl.value) { showErr('skill_level', 'Please select your skill level.'); ok = false; }
    else           { clearErr('skill_level'); }
  }
  if (n === 4) {
    var lp = document.getElementById('learning_purpose');
    var ps = document.getElementById('preferred_schedule');
    if (!lp.value) { showErr('learning_purpose', 'Please select a learning purpose.'); ok = false; }
    else           { clearErr('learning_purpose'); }
    if (!ps.value) { showErr('preferred_schedule','Please select a preferred schedule.'); ok = false; }
    else           { clearErr('preferred_schedule'); }
  }
  return ok;
}

/* Live clear on input */
['full_name','reg_email','reg_password','confirm_password','art_category','art_discipline',
 'skill_level','learning_purpose','preferred_schedule'].forEach(function(id) {
  var el = document.getElementById(id);
  if (el) el.addEventListener('input', function() { clearErr(id); });
  if (el) el.addEventListener('change', function() { clearErr(id); });
});

/* ══════════════════════════════════════════════════════════
   PASSWORD STRENGTH METER
══════════════════════════════════════════════════════════ */
var pwInput   = document.getElementById('reg_password');
var pwStrength= document.getElementById('pwStrength');
var pwLabel   = document.getElementById('pwLabel');
var bars      = [document.getElementById('pb1'),document.getElementById('pb2'),
                 document.getElementById('pb3'),document.getElementById('pb4')];

function scorePassword(pw) {
  var score = 0;
  if (!pw) return 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  return Math.min(score, 4);
}

var scoreData = [
  { label:'Too short',  color:'var(--pw-weak)',   bars:1 },
  { label:'Weak',       color:'var(--pw-weak)',   bars:1 },
  { label:'Fair',       color:'var(--pw-fair)',   bars:2 },
  { label:'Good',       color:'var(--pw-good)',   bars:3 },
  { label:'Strong',     color:'var(--pw-strong)', bars:4 },
];

pwInput.addEventListener('input', function() {
  var v = this.value;
  if (!v) { pwStrength.classList.remove('visible'); return; }
  pwStrength.classList.add('visible');
  var s = scorePassword(v);
  var d = scoreData[v.length < 8 ? 0 : s];
  bars.forEach(function(b,i) {
    b.style.background = i < d.bars ? d.color : 'rgba(3,102,176,0.10)';
  });
  pwLabel.textContent = d.label;
  pwLabel.style.color = d.color;
});

/* ══════════════════════════════════════════════════════════
   SHOW/HIDE PASSWORD TOGGLES
══════════════════════════════════════════════════════════ */
var eyeOnSVG  = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
var eyeOffSVG = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';

document.querySelectorAll('.pw-toggle').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var targetId = this.dataset.target;
    var inp = document.getElementById(targetId);
    if (!inp) return;
    var visible = inp.type === 'text';
    inp.type = visible ? 'password' : 'text';
    this.querySelector('svg').innerHTML = visible ? eyeOnSVG : eyeOffSVG;
    this.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
  });
});

/* ══════════════════════════════════════════════════════════
   GEOLOCATION
══════════════════════════════════════════════════════════ */
window.detectLocation = function() {
  if (!navigator.geolocation) return;
  var btn = document.getElementById('detectBtn');
  var inp = document.getElementById('location');
  btn.disabled = true; btn.textContent = 'Detecting…';
  navigator.geolocation.getCurrentPosition(function(pos) {
    fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + pos.coords.latitude + '&lon=' + pos.coords.longitude)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var city    = d.address.city || d.address.town || d.address.village || '';
        var state   = d.address.state || '';
        var country = d.address.country || '';
        inp.value = [city, state, country].filter(Boolean).join(', ');
        saveDraft();
        btn.disabled = false; btn.textContent = '✓ Location detected';
      })
      .catch(function() { btn.disabled = false; btn.textContent = 'Detect my location'; });
  }, function() {
    btn.disabled = false; btn.textContent = 'Detect my location';
    inp.placeholder = 'Enter manually';
  });
};

/* ══════════════════════════════════════════════════════════
   FILE UPLOAD ZONE
══════════════════════════════════════════════════════════ */
var fileInput = document.getElementById('showcase_file');
var uploadZone= document.getElementById('uploadZone');
if (fileInput) {
  fileInput.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      var name = this.files[0].name;
      var size = (this.files[0].size / 1024 / 1024).toFixed(1);
      uploadZone.classList.add('has-file');
      uploadZone.querySelector('.upload-zone-text').innerHTML =
        '<strong>📎 ' + name + '</strong> <span style="color:var(--mid)">(' + size + ' MB)</span>';
    }
  });
  // Drag & drop
  uploadZone.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor='var(--teal)'; });
  uploadZone.addEventListener('dragleave', function() { this.style.borderColor=''; });
  uploadZone.addEventListener('drop', function(e) {
    e.preventDefault(); this.style.borderColor='';
    if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; fileInput.dispatchEvent(new Event('change')); }
  });
}

/* ══════════════════════════════════════════════════════════
   FORM SUBMIT — loading state
══════════════════════════════════════════════════════════ */
document.getElementById('regForm').addEventListener('submit', function(e) {
  if (!validateStep(4)) { e.preventDefault(); return; }
  var btn = document.getElementById('submitBtn');
  if (btn) btn.classList.add('loading');
  // Clear draft on successful submit
  try { sessionStorage.removeItem(STORAGE_KEY); } catch(ex) {}
});

/* ══════════════════════════════════════════════════════════
   SERVER ALERT AUTO-DISMISS
══════════════════════════════════════════════════════════ */
var serverAlert = document.getElementById('serverAlert');
if (serverAlert) {
  setTimeout(function() {
    serverAlert.style.transition = 'opacity 0.5s';
    serverAlert.style.opacity = '0';
    setTimeout(function() { serverAlert.remove(); }, 500);
  }, 7000);
}

/* ══════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════ */
(function init() {
  // 1. Restore draft (fills fields that PHP didn't already fill)
  restoreDraft();

  // 2. If returning from server error, jump to correct step
  var startStep = SERVER_ERROR ? ERROR_STEP : 1;
  gotoStep(startStep);

  // 3. If art_category already selected (restored), populate disciplines
  var catEl = document.getElementById('art_category');
  if (catEl && catEl.value) updateDisciplines(catEl.value);
})();

})();
</script>
</body>
</html>