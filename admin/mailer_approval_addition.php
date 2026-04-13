<?php
/**
 * ADD THIS FUNCTION to your existing config/mailer.php
 * just below the sendOtpEmail() function.
 *
 * Sends an approval notification email to the teacher
 * with a direct link to login.php
 */
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