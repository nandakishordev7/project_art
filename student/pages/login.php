<?php
/**
 * Digital Art School - Login Page
 * Production-grade with form persistence & improved UX
 */

require_once '../includes/config.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: student-dashboard.php');
    exit;
}

$error_message = '';
$prefill_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = sanitizeInput($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    $prefill_email = $email; // Always re-fill email on error

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        $conn = getDatabaseConnection();
        if ($conn) {
            $stmt = $conn->prepare("SELECT student_id, full_name, email, password_hash FROM students WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $student = $result->fetch_assoc();
                if (verifyPassword($password, $student['password_hash'])) {
                    $_SESSION['student_id'] = $student['student_id'];
                    $_SESSION['email']      = $student['email'];
                    $_SESSION['full_name']  = $student['full_name'];

                    $update_stmt = $conn->prepare("UPDATE students SET last_login = CURRENT_TIMESTAMP WHERE student_id = ?");
                    $update_stmt->bind_param("i", $student['student_id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    $ip         = $_SERVER['REMOTE_ADDR']     ?? '';
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $log_stmt = $conn->prepare("INSERT INTO session_logs (student_id, ip_address, user_agent) VALUES (?, ?, ?)");
                    $log_stmt->bind_param("iss", $student['student_id'], $ip, $user_agent);
                    $log_stmt->execute();
                    $log_stmt->close();

                    $stmt->close();
                    closeDatabaseConnection($conn);
                    header('Location: student-dashboard.php');
                    exit;
                } else {
                    $error_message = 'Incorrect password. Please try again.';
                }
            } else {
                $error_message = 'No account found with that email address.';
            }
            $stmt->close();
            closeDatabaseConnection($conn);
        } else {
            $error_message = 'Database connection error. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — Digital Art School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
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
      --grad-r: linear-gradient(135deg, #02B393 0%, #0366B0 100%);
      --radius: 16px;
      --font-display: 'Playfair Display', Georgia, serif;
      --font-body:    'DM Sans', sans-serif;
      --error:  #c0392b;
      --error-bg: rgba(192,57,43,0.07);
      --success: #02B393;
    }

    body {
      font-family: var(--font-body);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background:
        linear-gradient(rgba(255,255,255,0.38), rgba(255,255,255,0.38)),
        url('../assets/images/community_portal_bg-52339207.png') center/cover no-repeat fixed;
    }

    /* ── CARD ── */
    .auth-wrap {
      position: relative; z-index: 1;
      display: grid;
      grid-template-columns: 1fr 1fr;
      width: min(920px, 96vw);
      min-height: 580px;
      border-radius: 28px;
      overflow: hidden;
      box-shadow: 0 24px 80px rgba(3,102,176,0.18), 0 2px 0 rgba(2,179,147,0.3);
      animation: slideUp 0.48s cubic-bezier(0.22,1,0.36,1) both;
    }
    @keyframes slideUp {
      from { opacity:0; transform:translateY(28px) scale(0.98); }
      to   { opacity:1; transform:translateY(0)     scale(1);    }
    }

    /* ── LEFT PANEL ── */
    .panel-left {
      background: linear-gradient(145deg, rgba(3,102,176,0.88), rgba(2,179,147,0.78));
      padding: 52px 44px;
      display: flex; flex-direction: column; justify-content: space-between;
      position: relative; overflow: hidden;
      color: white;
    }
    .panel-left::before {
      content:''; position:absolute;
      width:320px; height:320px; border-radius:50%;
      border:1.5px solid rgba(255,255,255,0.10);
      bottom:-100px; right:-100px;
    }
    .panel-left::after {
      content:''; position:absolute;
      width:180px; height:180px; border-radius:50%;
      border:1.5px solid rgba(255,255,255,0.08);
      top:-60px; left:-60px;
    }

    .brand-logos {
      display: flex; align-items: center; gap: 20px; position: relative; z-index:1;
    }
    .brand-logos img { height: 48px; width: auto; object-fit: contain; filter: brightness(0) invert(1); }
    .brand-divider {
      width: 1px; height: 36px;
      background: rgba(255,255,255,0.28);
    }

    .panel-headline {
      font-family: var(--font-display);
      font-weight: 800; font-size: 2rem; line-height: 1.18;
      position: relative; z-index:1;
    }
    .panel-sub {
      font-size: 0.86rem; color: rgba(255,255,255,0.78); line-height: 1.65;
      margin-top: 12px; position: relative; z-index:1;
    }

    .panel-badges { display:flex; flex-direction:column; gap:9px; margin-top:28px; position:relative; z-index:1; }
    .panel-badge {
      display:flex; align-items:center; gap:11px;
      background: rgba(255,255,255,0.14); border-radius:12px; padding:10px 14px;
      font-size:0.81rem; color:rgba(255,255,255,0.90);
      backdrop-filter: blur(4px);
      transition: background 0.2s;
    }
    .panel-badge:hover { background: rgba(255,255,255,0.22); }
    .panel-badge svg { flex-shrink:0; }

    .panel-footer { font-size:0.68rem; color:rgba(255,255,255,0.42); position:relative; z-index:1; }

    /* ── RIGHT PANEL ── */
    .panel-right {
      background: rgba(255,255,255,0.94);
      backdrop-filter: blur(16px);
      padding: 48px 48px;
      display: flex; flex-direction: column; justify-content: center;
    }

    .form-title {
      font-family: var(--font-display);
      font-weight: 800; font-size: 1.9rem; color: var(--dark); margin-bottom: 5px;
    }
    .form-sub {
      font-size: 0.86rem; color: var(--mid); margin-bottom: 28px; line-height: 1.5;
    }
    .form-sub a { color:var(--blue); font-weight:600; text-decoration:none; }
    .form-sub a:hover { text-decoration:underline; }

    /* ── ALERT ── */
    .alert {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 13px 16px; border-radius: 12px;
      font-size: 0.84rem; margin-bottom: 20px;
      animation: alertPop 0.3s ease;
    }
    @keyframes alertPop { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .alert-error   { background:var(--error-bg); color:var(--error); border:1px solid rgba(192,57,43,0.18); }
    .alert-success { background:rgba(2,179,147,0.07); color:var(--teal); border:1px solid rgba(2,179,147,0.2); }
    .alert svg { flex-shrink:0; margin-top:1px; }

    /* ── FIELD ── */
    .field { margin-bottom: 18px; }
    .field-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:7px; }
    .field label {
      display:block; font-size:0.73rem; font-weight:700;
      text-transform:uppercase; letter-spacing:0.9px; color:var(--mid);
    }
    .field-hint { font-size:0.72rem; color:var(--mid); }
    .field-hint a { color:var(--blue); text-decoration:none; font-weight:500; }
    .field-hint a:hover { text-decoration:underline; }

    .input-wrap {
      position: relative;
    }
    .input-wrap input {
      width: 100%;
      padding: 13px 16px;
      padding-right: 46px; /* room for toggle icon */
      border: 1.5px solid rgba(3,102,176,0.18);
      border-radius: var(--radius);
      font-family: var(--font-body); font-size: 0.94rem;
      color: var(--dark); background: var(--light);
      outline: none;
      transition: border-color 0.22s, box-shadow 0.22s, background 0.22s;
    }
    .input-wrap input:focus {
      border-color: var(--teal);
      box-shadow: 0 0 0 4px rgba(2,179,147,0.11);
      background: white;
    }
    .input-wrap input.input-error { border-color:var(--error); background: rgba(192,57,43,0.03); }
    .input-wrap input.input-error:focus { box-shadow:0 0 0 4px rgba(192,57,43,0.08); }
    .input-wrap input.input-ok { border-color:var(--teal); }
    .input-wrap input::placeholder { color:#b5cad6; }

    /* Show/hide password */
    .pw-toggle {
      position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      color: var(--mid); display: flex; padding: 4px;
      transition: color 0.2s;
    }
    .pw-toggle:hover { color: var(--blue); }

    /* Input icon (left) */
    .input-icon {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: var(--mid); pointer-events: none;
    }
    .input-wrap.has-icon input { padding-left: 40px; }

    /* Field inline error */
    .field-error {
      font-size: 0.73rem; color: var(--error); margin-top: 5px;
      display: flex; align-items: center; gap: 4px;
      animation: alertPop 0.2s ease;
    }

    /* ── CHECKBOX ROW ── */
    .check-row {
      display: flex; align-items: center; gap: 9px;
      margin-bottom: 22px;
    }
    .check-row input[type="checkbox"] {
      width: 16px; height: 16px; accent-color: var(--teal); cursor: pointer; flex-shrink: 0;
    }
    .check-row label { font-size: 0.84rem; color: var(--mid); cursor: pointer; }

    /* ── BUTTON ── */
    .btn-primary {
      width: 100%; padding: 14px;
      background: var(--grad); border: none; border-radius: var(--radius);
      font-family: var(--font-display); font-weight: 700; font-size: 1rem;
      color: white; cursor: pointer; letter-spacing: 0.3px;
      transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
      box-shadow: 0 5px 18px rgba(3,102,176,0.28);
      position: relative; overflow: hidden;
    }
    .btn-primary::after {
      content: ''; position:absolute; inset:0;
      background: linear-gradient(rgba(255,255,255,0),rgba(255,255,255,0.08));
      opacity:0; transition:opacity 0.2s;
    }
    .btn-primary:hover { opacity:0.92; transform:translateY(-2px); box-shadow:0 8px 26px rgba(3,102,176,0.32); }
    .btn-primary:hover::after { opacity:1; }
    .btn-primary:active { transform:translateY(0); }
    .btn-primary.loading { pointer-events:none; }
    .btn-primary .btn-spinner {
      display: none; width:18px; height:18px; border:2.5px solid rgba(255,255,255,0.35);
      border-top-color:white; border-radius:50%; animation:spin 0.7s linear infinite;
    }
    .btn-primary.loading .btn-text { display:none; }
    .btn-primary.loading .btn-spinner { display:inline-block; }
    @keyframes spin { to{transform:rotate(360deg)} }
    .btn-inner { display:flex; align-items:center; justify-content:center; gap:8px; }

    /* ── DIVIDER ── */
    .or-row {
      display:flex; align-items:center; gap:12px; margin:18px 0;
    }
    .or-line { flex:1; height:1px; background:rgba(3,102,176,0.1); }
    .or-text  { font-size:0.74rem; color:#b0bec5; font-weight:500; }

    /* ── RESPONSIVE ── */
    @media (max-width: 660px) {
      .auth-wrap { grid-template-columns:1fr; }
      .panel-left { display:none; }
      .panel-right { padding:38px 26px; }
    }
  </style>
</head>
<body>

<div class="auth-wrap">

  <!-- LEFT BRAND PANEL -->
  <div class="panel-left">
    <div>
      <div class="brand-logos">
        <img src="../assets/images/DUK Logo White 3 650.png" alt="DUK Logo"
             onerror="this.style.display='none'">
        <div class="brand-divider"></div>
        <img src="../assets/images/cdtc_logo.png" alt="CDTC Logo"
             onerror="this.style.display='none'">
      </div>
      <div style="margin-top:44px;">
        <div class="panel-headline">Welcome<br>back, Artist.</div>
        <div class="panel-sub">Sign in to continue your creative journey. Your courses, progress, and community are waiting.</div>
      </div>
      <div class="panel-badges">
        <div class="panel-badge">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>
          Access all your enrolled courses
        </div>
        <div class="panel-badge">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Track your learning milestones
        </div>
        <div class="panel-badge">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Connect with your instructors
        </div>
      </div>
    </div>
    <div class="panel-footer">© Digital Art School — Preserving tradition through technology</div>
  </div>

  <!-- RIGHT FORM PANEL -->
  <div class="panel-right">
    <div class="form-title">Sign in</div>
    <div class="form-sub">New student? <a href="register.php">Create an account</a></div>

    <?php if ($error_message): ?>
    <div class="alert alert-error" id="serverAlert">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" autocomplete="off" novalidate>

      <!-- Email -->
      <div class="field" id="field-email">
        <div class="field-header">
          <label for="email">Email Address</label>
        </div>
        <div class="input-wrap has-icon">
          <span class="input-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <input type="email" id="email" name="email"
                 placeholder="your.email@example.com"
                 value="<?php echo htmlspecialchars($prefill_email); ?>"
                 autocomplete="email" required>
        </div>
        <div class="field-error" id="err-email" style="display:none">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span></span>
        </div>
      </div>

      <!-- Password -->
      <div class="field" id="field-password">
        <div class="field-header">
          <label for="password">Password</label>
          <span class="field-hint"><a href="forgot-password.php">Forgot password?</a></span>
        </div>
        <div class="input-wrap has-icon">
          <span class="input-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" id="password" name="password"
                 placeholder="Enter your password"
                 autocomplete="current-password" required>
          <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">
            <svg id="eyeIcon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="field-error" id="err-password" style="display:none">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span></span>
        </div>
      </div>

      <!-- Remember me -->
      <div class="check-row">
        <input type="checkbox" id="rememberMe" name="remember_me">
        <label for="rememberMe">Remember my email on this device</label>
      </div>

      <button type="submit" name="login" class="btn-primary" id="submitBtn">
        <div class="btn-inner">
          <span class="btn-text">Sign In</span>
          <div class="btn-spinner"></div>
        </div>
      </button>

    </form>

    <div class="or-row"><div class="or-line"></div><span class="or-text">OR</span><div class="or-line"></div></div>
    <div style="text-align:center;font-size:0.84rem;color:var(--mid);">
      Don't have an account? <a href="register.php" style="color:var(--blue);font-weight:600;text-decoration:none;">Register here →</a>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const STORAGE_KEY = 'das_login_email';
  const emailInput  = document.getElementById('email');
  const pwInput     = document.getElementById('password');
  const rememberChk = document.getElementById('rememberMe');
  const form        = document.getElementById('loginForm');
  const submitBtn   = document.getElementById('submitBtn');

  /* ── RESTORE remembered email ──────────────────────────────── */
  const savedEmail = localStorage.getItem(STORAGE_KEY) || sessionStorage.getItem(STORAGE_KEY + '_session');
  // Only pre-fill if PHP didn't already (i.e. no server-side error with typed email)
  const serverFilledEmail = '<?php echo addslashes(htmlspecialchars($prefill_email)); ?>';
  if (!serverFilledEmail && savedEmail) {
    emailInput.value = savedEmail;
    rememberChk.checked = !!localStorage.getItem(STORAGE_KEY);
  } else if (serverFilledEmail) {
    // On error, restore from PHP-echoed value AND mark remember if it was on
    rememberChk.checked = !!localStorage.getItem(STORAGE_KEY);
  }

  /* ── LIVE SAVE to sessionStorage as user types ─────────────── */
  emailInput.addEventListener('input', function () {
    sessionStorage.setItem(STORAGE_KEY + '_session', this.value);
    clearError('email');
  });
  pwInput.addEventListener('input', function () { clearError('password'); });

  /* ── SHOW / HIDE PASSWORD ───────────────────────────────────── */
  const pwToggle = document.getElementById('pwToggle');
  const eyeIcon  = document.getElementById('eyeIcon');
  const eyeOff   = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;
  const eyeOn    = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
  let pwVisible  = false;

  pwToggle.addEventListener('click', function () {
    pwVisible = !pwVisible;
    pwInput.type = pwVisible ? 'text' : 'password';
    eyeIcon.innerHTML = pwVisible ? eyeOff : eyeOn;
    pwToggle.setAttribute('aria-label', pwVisible ? 'Hide password' : 'Show password');
  });

  /* ── CLIENT-SIDE VALIDATION ─────────────────────────────────── */
  function showError(field, msg) {
    const el = document.getElementById('err-' + field);
    const input = document.getElementById(field);
    if (!el || !input) return;
    el.querySelector('span').textContent = msg;
    el.style.display = 'flex';
    input.classList.add('input-error');
    input.classList.remove('input-ok');
  }
  function clearError(field) {
    const el = document.getElementById('err-' + field);
    const input = document.getElementById(field);
    if (!el || !input) return;
    el.style.display = 'none';
    input.classList.remove('input-error');
  }
  function markOk(field) {
    const input = document.getElementById(field);
    if (input) { input.classList.add('input-ok'); input.classList.remove('input-error'); }
  }

  function validateEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim());
  }

  emailInput.addEventListener('blur', function () {
    const v = this.value.trim();
    if (!v) showError('email', 'Email is required.');
    else if (!validateEmail(v)) showError('email', 'Enter a valid email address.');
    else { clearError('email'); markOk('email'); }
  });

  pwInput.addEventListener('blur', function () {
    if (!this.value) showError('password', 'Password is required.');
    else { clearError('password'); markOk('password'); }
  });

  /* ── FORM SUBMIT ────────────────────────────────────────────── */
  form.addEventListener('submit', function (e) {
    let valid = true;
    const email = emailInput.value.trim();
    const pw    = pwInput.value;

    if (!email) { showError('email', 'Email is required.'); valid = false; }
    else if (!validateEmail(email)) { showError('email', 'Enter a valid email address.'); valid = false; }

    if (!pw) { showError('password', 'Password is required.'); valid = false; }

    if (!valid) { e.preventDefault(); return; }

    /* Remember-me logic */
    if (rememberChk.checked) {
      localStorage.setItem(STORAGE_KEY, email);
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
    sessionStorage.removeItem(STORAGE_KEY + '_session');

    /* Loading state */
    submitBtn.classList.add('loading');
  });

  /* ── AUTO-DISMISS server alert after 6s ─────────────────────── */
  const serverAlert = document.getElementById('serverAlert');
  if (serverAlert) {
    setTimeout(function () {
      serverAlert.style.transition = 'opacity 0.5s';
      serverAlert.style.opacity = '0';
      setTimeout(function () { serverAlert.remove(); }, 500);
    }, 6000);
  }

})();
</script>
</body>
</html>