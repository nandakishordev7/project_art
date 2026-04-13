<?php
/**
 * login.php
 * Step 1 — teacher enters email → OTP is generated + emailed
 * Step 2 — teacher enters 4-digit OTP → verified → redirect to dashboard
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mailer.php';

session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path'     => '/',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Already logged in
if (isset($_SESSION['teacher_id'])) {
    header('Location: teacher-dashboard.php');
    exit;
}

$step   = $_SESSION['login_step'] ?? 1; // 1 = email, 2 = otp
$error  = '';
$notice = '';

// ── HANDLE POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── STEP 1: Submit email, generate + send OTP ─────────────
    if ($action === 'send_otp') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Look up teacher in DB
            $stmt = DB::conn()->prepare(
                "SELECT teacher_id, name, is_approved FROM teachers WHERE email = ?"
            );
            $stmt->execute([$email]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                $error = 'No account found with that email. <a href="register.php">Register here.</a>';
            } elseif (!(int)$teacher['is_approved']) {
                $error = 'Your account is pending admin approval. You will receive an email once approved.';
            } else {
                // Generate 4-digit OTP
                $otp     = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MIN . ' minutes'));

                // Save OTP to DB
                DB::conn()->prepare(
                    "UPDATE teachers SET otp_code = ?, otp_expires = ? WHERE teacher_id = ?"
                )->execute([$otp, $expires, $teacher['teacher_id']]);

                // Send OTP email
                $result = sendOtpEmail($email, $teacher['name'], $otp);

                if (!$result['ok']) {
                    $error = 'Could not send email: ' . htmlspecialchars($result['error'] ?? 'Unknown error');
                } else {
                    // Move to step 2
                    $_SESSION['login_email'] = $email;
                    $_SESSION['login_step']  = 2;
                    $step   = 2;
                    $notice = 'A 4-digit code has been sent to <strong>' . htmlspecialchars($email) . '</strong>.';
                }
            }
        }
    }

    // ── STEP 2: Verify OTP ────────────────────────────────────
    elseif ($action === 'verify_otp') {
        $enteredOtp = trim($_POST['otp'] ?? '');
        $email      = $_SESSION['login_email'] ?? '';

        if (strlen($enteredOtp) !== 4 || !ctype_digit($enteredOtp)) {
            $error = 'Please enter the 4-digit code.';
            $step  = 2;
        } elseif (!$email) {
            $error = 'Session expired. Please start again.';
            $_SESSION['login_step'] = 1;
            $step = 1;
        } else {
            $stmt = DB::conn()->prepare(
                "SELECT teacher_id, name, otp_code, otp_expires
                 FROM teachers
                 WHERE email = ? AND is_approved = 1"
            );
            $stmt->execute([$email]);
            $teacher = $stmt->fetch();

            if (!$teacher) {
                $error = 'Account not found.';
                $step  = 1;
            } elseif ($teacher['otp_code'] !== $enteredOtp) {
                $error = 'Incorrect code. Please try again.';
                $step  = 2;
            } elseif (strtotime($teacher['otp_expires']) < time()) {
                $error = 'This code has expired. <a href="login.php">Request a new one.</a>';
                // Clear expired OTP
                DB::conn()->prepare(
                    "UPDATE teachers SET otp_code = NULL, otp_expires = NULL WHERE teacher_id = ?"
                )->execute([$teacher['teacher_id']]);
                unset($_SESSION['login_step'], $_SESSION['login_email']);
                $step = 1;
            } else {
                // ✅ OTP verified — log in
                DB::conn()->prepare(
                    "UPDATE teachers SET otp_code = NULL, otp_expires = NULL WHERE teacher_id = ?"
                )->execute([$teacher['teacher_id']]);

                unset($_SESSION['login_step'], $_SESSION['login_email']);
                $_SESSION['teacher_id']   = $teacher['teacher_id'];
                $_SESSION['teacher_name'] = $teacher['name'];

                header('Location: teacher-dashboard.php');
                exit;
            }
        }
    }

    // ── Go back to email step ─────────────────────────────────
    elseif ($action === 'back') {
        unset($_SESSION['login_step'], $_SESSION['login_email']);
        $step = 1;
    }

    // ── Resend OTP ────────────────────────────────────────────
    elseif ($action === 'resend') {
        $email = $_SESSION['login_email'] ?? '';
        if ($email) {
            $stmt = DB::conn()->prepare(
                "SELECT teacher_id, name FROM teachers WHERE email = ? AND is_approved = 1"
            );
            $stmt->execute([$email]);
            $teacher = $stmt->fetch();

            if ($teacher) {
                $otp     = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MIN . ' minutes'));
                DB::conn()->prepare(
                    "UPDATE teachers SET otp_code = ?, otp_expires = ? WHERE teacher_id = ?"
                )->execute([$otp, $expires, $teacher['teacher_id']]);

                $result = sendOtpEmail($email, $teacher['name'], $otp);
                $notice = $result['ok']
                    ? 'A new code has been sent to <strong>' . htmlspecialchars($email) . '</strong>.'
                    : 'Could not resend: ' . htmlspecialchars($result['error'] ?? '');
                $step = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Digital Arts School</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;600;700;800;900&family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --blue:#0366B0; --teal:#02B393; --green:#A3CE47;
            --dark:#1e2d3d; --mid:#5a7a9a; --light:#f5fdfc;
            --grad:linear-gradient(135deg,#0366B0,#02B393);
            --r:14px; --font-h:'Raleway',sans-serif; --font-b:'Work Sans',sans-serif;
        }
        body {
            font-family:var(--font-b);
            min-height:100vh;
            background:#f0f9f7;
            background-image:radial-gradient(rgba(2,179,147,0.07) 1px, transparent 1px);
            background-size:24px 24px;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            padding:24px;
        }

        /* Brand */
        .brand {
            display:flex; align-items:center; gap:12px;
            margin-bottom:32px;
        }
        .brand-icon {
            width:44px; height:44px;
            background:var(--grad);
            border-radius:12px;
            display:flex; align-items:center; justify-content:center;
        }
        .brand-name {
            font-family:var(--font-h);
            font-weight:800; font-size:1.2rem;
            color:var(--dark);
        }
        .brand-sub {
            font-size:0.8rem; color:var(--mid);
            font-family:var(--font-h); font-weight:400;
        }

        /* Card */
        .login-card {
            width:100%; max-width:420px;
            background:white;
            border-radius:20px;
            box-shadow:0 8px 40px rgba(3,102,176,0.10);
            overflow:hidden;
        }
        .card-top {
            background:var(--grad);
            padding:28px 32px 24px;
            text-align:center;
        }
        .card-top-title {
            font-family:var(--font-h);
            font-weight:800; font-size:1.3rem;
            color:white; margin-bottom:4px;
        }
        .card-top-sub {
            font-size:0.85rem;
            color:rgba(255,255,255,0.8);
        }
        .card-body { padding:32px; }

        /* Step indicator */
        .step-indicator {
            display:flex; align-items:center; justify-content:center;
            gap:8px; margin-bottom:24px;
        }
        .step-dot {
            width:8px; height:8px; border-radius:50%;
            background:rgba(3,102,176,0.15);
            transition:all 0.3s;
        }
        .step-dot.active {
            background:var(--teal);
            width:24px; border-radius:4px;
        }

        /* Alert */
        .alert {
            padding:12px 14px;
            border-radius:10px;
            font-size:0.84rem;
            margin-bottom:20px;
            line-height:1.5;
        }
        .alert-error {
            background:rgba(231,76,60,0.08);
            color:#c0392b;
            border:1px solid rgba(231,76,60,0.2);
        }
        .alert-notice {
            background:rgba(2,179,147,0.08);
            color:#027a60;
            border:1px solid rgba(2,179,147,0.2);
        }
        .alert a { color:inherit; font-weight:600; }

        /* Field */
        .field { display:flex; flex-direction:column; gap:7px; margin-bottom:20px; }
        .field label {
            font-size:0.75rem; font-weight:700;
            text-transform:uppercase; letter-spacing:0.8px;
            color:var(--mid);
        }
        .field input {
            padding:12px 16px;
            border:1.5px solid rgba(3,102,176,0.18);
            border-radius:var(--r);
            font-family:var(--font-b); font-size:1rem;
            color:var(--dark); background:var(--light);
            outline:none;
            transition:border-color 0.2s, box-shadow 0.2s;
            width:100%;
        }
        .field input:focus {
            border-color:var(--teal);
            box-shadow:0 0 0 3px rgba(2,179,147,0.12);
        }

        /* OTP input row */
        .otp-row {
            display:flex; gap:10px;
            justify-content:center;
            margin-bottom:24px;
        }
        .otp-digit {
            width:60px; height:68px;
            border:2px solid rgba(3,102,176,0.2);
            border-radius:12px;
            font-family:'Courier New',monospace;
            font-size:1.8rem; font-weight:800;
            color:var(--dark);
            text-align:center;
            background:var(--light);
            outline:none;
            transition:border-color 0.2s, box-shadow 0.2s;
            -moz-appearance:textfield;
        }
        .otp-digit::-webkit-outer-spin-button,
        .otp-digit::-webkit-inner-spin-button { -webkit-appearance:none; }
        .otp-digit:focus {
            border-color:var(--teal);
            box-shadow:0 0 0 3px rgba(2,179,147,0.15);
        }
        .otp-digit.filled {
            border-color:var(--blue);
            background:rgba(3,102,176,0.04);
        }

        /* Hidden OTP input for form submit */
        #otpHidden { display:none; }

        /* Buttons */
        .btn-primary {
            width:100%;
            padding:13px;
            background:var(--grad);
            border:none; border-radius:var(--r);
            font-family:var(--font-h); font-weight:700;
            font-size:0.95rem; color:white;
            cursor:pointer;
            transition:opacity 0.2s, transform 0.15s;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .btn-primary:hover { opacity:0.92; transform:translateY(-1px); }
        .btn-primary:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

        .btn-ghost {
            width:100%; padding:10px;
            background:none; border:1.5px solid rgba(3,102,176,0.18);
            border-radius:var(--r);
            font-family:var(--font-h); font-weight:600;
            font-size:0.88rem; color:var(--mid);
            cursor:pointer; margin-top:10px;
            transition:all 0.2s;
        }
        .btn-ghost:hover { border-color:var(--blue); color:var(--dark); }

        /* Footer links */
        .card-footer {
            text-align:center;
            padding:16px 32px 24px;
            font-size:0.83rem; color:var(--mid);
        }
        .card-footer a { color:var(--blue); font-weight:600; text-decoration:none; }
        .card-footer a:hover { text-decoration:underline; }

        /* Email hint on step 2 */
        .email-hint {
            text-align:center;
            font-size:0.83rem; color:var(--mid);
            margin-bottom:20px;
            line-height:1.5;
        }
        .email-hint strong { color:var(--dark); }

        /* Resend row */
        .resend-row {
            text-align:center;
            margin-top:14px;
            font-size:0.82rem; color:var(--mid);
        }
        .resend-row button {
            background:none; border:none;
            color:var(--blue); font-weight:600;
            cursor:pointer; font-size:0.82rem;
            text-decoration:underline;
        }

        @media(max-width:480px) {
            .otp-digit { width:52px; height:60px; font-size:1.5rem; }
        }
    </style>
</head>
<body>

<!-- Brand -->
<div class="brand">
    <div class="brand-icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
            <path d="M12 3c0 4-2 6-4 8 2 0 4 2 4 6 0-4 2-6 4-8-2 0-4-2-4-6z"/>
            <path d="M8 11c-2-2-4-2-6-1 4 1 6 3 6 7 0-4-2-6-6-7 2-1 4-1 6 1z"/>
            <path d="M16 11c2-2 4-2 6-1-4 1-6 3-6 7 0-4 2-6 6-7-2-1-4-1-6 1z"/>
        </svg>
    </div>
    <div>
        <div class="brand-name">Digital Arts School</div>
        <div class="brand-sub">Teacher Portal</div>
    </div>
</div>

<div class="login-card">

    <!-- Top band -->
    <div class="card-top">
        <div class="card-top-title">
            <?php echo $step === 1 ? 'Welcome back' : 'Enter your code'; ?>
        </div>
        <div class="card-top-sub">
            <?php echo $step === 1
                ? 'Sign in to your teacher account'
                : 'Check your email for a 4-digit code'; ?>
        </div>
    </div>

    <div class="card-body">

        <!-- Step dots -->
        <div class="step-indicator">
            <div class="step-dot <?php echo $step === 1 ? 'active' : ''; ?>"></div>
            <div class="step-dot <?php echo $step === 2 ? 'active' : ''; ?>"></div>
        </div>

        <!-- Error / notice -->
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="alert alert-notice"><?php echo $notice; ?></div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════
             STEP 1: Email input
        ══════════════════════════════════════════ -->
        <?php if ($step === 1): ?>
        <form method="POST">
            <input type="hidden" name="action" value="send_otp">
            <div class="field">
                <label>Email Address</label>
                <input type="email" name="email"
                       placeholder="you@example.com"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                       autofocus required>
            </div>
            <button type="submit" class="btn-primary">
                Send Login Code
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </form>

        <!-- ══════════════════════════════════════════
             STEP 2: OTP verification
        ══════════════════════════════════════════ -->
        <?php else: ?>
        <div class="email-hint">
            Code sent to<br>
            <strong><?php echo htmlspecialchars($_SESSION['login_email'] ?? ''); ?></strong>
        </div>

        <form method="POST" id="otpForm">
            <input type="hidden" name="action" value="verify_otp">
            <input type="hidden" name="otp" id="otpHidden">

            <!-- 4 individual digit boxes — UX friendly -->
            <div class="otp-row">
                <input type="text" class="otp-digit" id="d1" maxlength="1" inputmode="numeric" pattern="[0-9]" autofocus>
                <input type="text" class="otp-digit" id="d2" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" id="d3" maxlength="1" inputmode="numeric" pattern="[0-9]">
                <input type="text" class="otp-digit" id="d4" maxlength="1" inputmode="numeric" pattern="[0-9]">
            </div>

            <button type="submit" class="btn-primary" id="verifyBtn" disabled>
                Verify &amp; Sign In
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </button>
        </form>

        <!-- Resend -->
        <div class="resend-row">
            Didn't get it?
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="resend">
                <button type="submit">Resend code</button>
            </form>
        </div>

        <!-- Back -->
        <form method="POST">
            <input type="hidden" name="action" value="back">
            <button type="submit" class="btn-ghost">← Use a different email</button>
        </form>
        <?php endif; ?>

    </div><!-- /card-body -->

    <div class="card-footer">
        Don't have an account? <a href="register.php">Register here</a>
    </div>

</div><!-- /login-card -->

<script>
// ── OTP digit box UX ─────────────────────────────────────────
(function () {
    var digits  = [
        document.getElementById('d1'),
        document.getElementById('d2'),
        document.getElementById('d3'),
        document.getElementById('d4'),
    ];
    var hidden  = document.getElementById('otpHidden');
    var btn     = document.getElementById('verifyBtn');
    var form    = document.getElementById('otpForm');

    if (!digits[0]) return; // Not on OTP step

    function getValue() {
        return digits.map(function(d) { return d ? d.value : ''; }).join('');
    }

    function update() {
        var val = getValue();
        if (hidden) hidden.value = val;
        if (btn)    btn.disabled = val.length < 4;
        digits.forEach(function(d) {
            if (d) d.classList.toggle('filled', d.value !== '');
        });
    }

    digits.forEach(function(box, i) {
        if (!box) return;

        box.addEventListener('input', function () {
            // Allow only digits
            this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
            update();
            // Auto-advance
            if (this.value && i < digits.length - 1) {
                digits[i + 1].focus();
            }
            // Auto-submit when all filled
            if (getValue().length === 4 && form) {
                setTimeout(function() { form.submit(); }, 120);
            }
        });

        box.addEventListener('keydown', function (e) {
            // Backspace: clear current and go back
            if (e.key === 'Backspace' && !this.value && i > 0) {
                digits[i - 1].value = '';
                digits[i - 1].focus();
                update();
            }
            // Allow paste on first box
        });

        box.addEventListener('paste', function (e) {
            e.preventDefault();
            var pasted = (e.clipboardData || window.clipboardData)
                            .getData('text').replace(/[^0-9]/g, '').slice(0, 4);
            pasted.split('').forEach(function(ch, j) {
                if (digits[j]) digits[j].value = ch;
            });
            update();
            var next = Math.min(pasted.length, digits.length - 1);
            digits[next].focus();
            if (pasted.length === 4 && form) {
                setTimeout(function() { form.submit(); }, 120);
            }
        });
    });

    update();
})();
</script>
</body>
</html>