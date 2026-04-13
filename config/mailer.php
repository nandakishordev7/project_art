<?php
/**
 * config/mailer.php
 * Sends OTP emails via PHPMailer + Gmail SMTP
 * Usage: require_once 'config/mailer.php'; sendOtpEmail($toEmail, $toName, $otp);
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';

// Auto-detect PHPMailer whether installed via Composer or manual download
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
$manualAutoload   = __DIR__ . '/../phpmailer/src/PHPMailer.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} elseif (file_exists($manualAutoload)) {
    require_once $manualAutoload;
    require_once __DIR__ . '/../phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../phpmailer/src/Exception.php';
} else {
    die(json_encode(['error' => 'PHPMailer not found. Run: composer require phpmailer/phpmailer']));
}

/**
 * Send a 4-digit OTP to a teacher's email address.
 *
 * @param string $toEmail   Recipient email
 * @param string $toName    Recipient display name
 * @param string $otp       4-digit OTP string
 * @return array ['ok' => bool, 'error' => string|null]
 */
function sendOtpEmail(string $toEmail, string $toName, string $otp): array {
    $mail = new PHPMailer(true);

    try {
        // ── Server settings ───────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF; // change to DEBUG_SERVER to see SMTP logs

        // ── Recipients ────────────────────────────────────────
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // ── Content ───────────────────────────────────────────
        $mail->isHTML(true);
        $mail->Subject = 'Your Kathakali Bridge login code: ' . $otp;
        $mail->Body    = buildOtpEmailHtml($toName, $otp);
        $mail->AltBody = "Hello {$toName},\n\nYour Kathakali Bridge login code is: {$otp}\n\nThis code expires in " . OTP_EXPIRY_MIN . " minutes.\n\nDo not share this code with anyone.";

        $mail->send();
        return ['ok' => true];

    } catch (Exception $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

function sendApprovalNotification(string $toEmail, string $toName, string $loginUrl): array {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Kathakali Bridge account has been approved!';
        $mail->Body    = "
<!DOCTYPE html><html><body style='font-family:Work Sans,Arial,sans-serif;background:#f0f9f7;padding:40px 20px;'>
<table width='520' align='center' style='background:white;border-radius:20px;
    box-shadow:0 8px 40px rgba(3,102,176,.10);overflow:hidden;'>
  <tr><td style='background:linear-gradient(135deg,#0366B0,#02B393);padding:32px 40px;text-align:center;'>
    <div style='font-family:Raleway,Arial,sans-serif;font-weight:900;font-size:1.4rem;color:white;'>
        Kathakali Bridge</div>
    <div style='color:rgba(255,255,255,.8);font-size:.85rem;margin-top:4px;'>Teacher Portal</div>
  </td></tr>
  <tr><td style='padding:36px 40px;'>
    <p style='font-size:1rem;color:#1e2d3d;margin:0 0 12px;'>Hello <strong>{$toName}</strong>,</p>
    <p style='font-size:.92rem;color:#5a7a9a;margin:0 0 24px;line-height:1.6;'>
        Great news! Your teacher account on <strong>Kathakali Bridge</strong> has been reviewed
        and <strong style='color:#02B393;'>approved</strong>. You can now sign in and start
        managing your classes.
    </p>
    <div style='text-align:center;margin:28px 0;'>
        <a href='{$loginUrl}'
           style='display:inline-block;padding:14px 36px;
                  background:linear-gradient(135deg,#0366B0,#02B393);
                  border-radius:12px;font-family:Raleway,sans-serif;
                  font-weight:700;font-size:.95rem;color:white;text-decoration:none;'>
            Sign In to Your Account →
        </a>
    </div>
    <p style='font-size:.8rem;color:#aab;line-height:1.6;margin:0;'>
        You will be asked to enter your email address, then receive a
        4-digit login code to verify your identity.
    </p>
  </td></tr>
  <tr><td style='background:#f5fdfc;padding:16px 40px;text-align:center;
                  border-top:1px solid #e0f5f0;'>
    <p style='font-size:.75rem;color:#aab;margin:0;'>
        © Kathakali Bridge · Digital University Kerala
    </p>
  </td></tr>
</table>
</body></html>";

        $mail->AltBody = "Hello {$toName},\n\nYour Kathakali Bridge teacher account has been approved!\n\nSign in here: {$loginUrl}\n\nYou will receive a 4-digit login code by email each time you sign in.";
        $mail->send();
        return ['ok' => true];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Build the HTML email body for the OTP.
 */
function buildOtpEmailHtml(string $name, string $otp): string {
    $expiry   = OTP_EXPIRY_MIN;
    $digits   = str_split($otp);
    $digitHtml = '';
    foreach ($digits as $d) {
        $digitHtml .= "
            <td style=\"padding:0 6px;\">
              <div style=\"
                width:52px;height:62px;
                background:#f5fdfc;
                border:2px solid #02B393;
                border-radius:10px;
                display:inline-block;
                text-align:center;
                line-height:62px;
                font-size:2rem;
                font-weight:800;
                color:#0366B0;
                font-family:'Courier New',monospace;
              \">{$d}</div>
            </td>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f0f9f7;font-family:'Work Sans',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center" style="padding:40px 20px;">
        <table width="520" cellpadding="0" cellspacing="0"
               style="background:white;border-radius:20px;
                      box-shadow:0 8px 40px rgba(3,102,176,0.10);
                      overflow:hidden;">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#0366B0,#02B393);
                        padding:32px 40px;text-align:center;">
              <div style="font-family:Raleway,Arial,sans-serif;font-weight:900;
                           font-size:1.4rem;color:white;letter-spacing:1px;">
                Kathakali Bridge
              </div>
              <div style="color:rgba(255,255,255,0.8);font-size:0.85rem;margin-top:4px;">
                Teacher Login Verification
              </div>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:36px 40px 28px;">
              <p style="font-size:1rem;color:#1e2d3d;margin:0 0 8px;">
                Hello <strong>{$name}</strong>,
              </p>
              <p style="font-size:0.92rem;color:#5a7a9a;margin:0 0 28px;line-height:1.6;">
                Use the code below to sign in to your Kathakali Bridge teacher account.
                This code expires in <strong>{$expiry} minutes</strong>.
              </p>

              <!-- OTP digits -->
              <table align="center" cellpadding="0" cellspacing="0"
                     style="margin:0 auto 28px;">
                <tr>{$digitHtml}</tr>
              </table>

              <p style="font-size:0.8rem;color:#aab;text-align:center;margin:0 0 28px;">
                Do not share this code with anyone. Kathakali Bridge will never ask for it.
              </p>

              <hr style="border:none;border-top:1px solid #e8f5f2;margin:0 0 20px;">

              <p style="font-size:0.78rem;color:#aab;margin:0;line-height:1.6;">
                If you did not request this code, you can safely ignore this email.
                Your account will not be affected.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#f5fdfc;padding:16px 40px;text-align:center;
                        border-top:1px solid #e0f5f0;">
              <p style="font-size:0.75rem;color:#aab;margin:0;">
                © Digital Arts School· Digital University Kerala
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}