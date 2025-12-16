<?php
// EmailHelper.php
// Requires: composer require phpmailer/phpmailer
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    private $mailer;

    /**
     * Constructor.
     * @param bool $enableDebug set true to enable PHPMailer debug output to error_log
     */
    public function __construct($enableDebug = false) {
        $this->mailer = new PHPMailer(true);

        // Optional debug
        if ($enableDebug) {
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = 'error_log';
        } else {
            $this->mailer->SMTPDebug = 0;
        }

        $this->setupSMTP();
    }

  // General send email method
    public function sendEmail($to, $subject, $body, $name = '', $useTemplate = false) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->addAddress($to, $name);
            $this->mailer->Subject = $subject;

            if ($useTemplate) {
                // generate HTML template
                $this->mailer->Body = $this->getBulkEmailTemplate($name, $subject, $body);
                $this->mailer->AltBody = strip_tags($body);
            } else {
                $this->mailer->isHTML(true);
                $this->mailer->Body = $body;
                $this->mailer->AltBody = strip_tags($body);
            }

            return (bool)$this->mailer->send();
        } catch (Exception $e) {
            error_log("Email send failed to {$to}: " . $e->getMessage());
            return false;
        }
    }



    /**
     * Basic SMTP setup using constants from config.php
     * Make sure config.php is required before instantiating this class.
     */
    private function setupSMTP() {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host       = SMTP_HOST;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = SMTP_USERNAME;
            $this->mailer->Password   = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = SMTP_PORT;
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
        } catch (Exception $e) {
            error_log("SMTP Setup Error: " . $e->getMessage());
            throw new Exception("Email configuration error");
        }
    }

    /**
     * Send OTP email. Returns true on success, false on failure.
     */
    public function sendOTPEmail($email, $name, $otp, $expiryMinutes = 15) {
        try {
            // Clear previous recipients/attachments (important when reusing same PHPMailer instance)
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = 'Verify Your ' . (defined('SITE_NAME') ? SITE_NAME : 'Account');

            $emailTemplate = $this->getOTPEmailTemplate($name, $otp, $expiryMinutes);
            $this->mailer->Body = $emailTemplate;
            $this->mailer->AltBody = "Your verification code is: {$otp}. This code will expire in {$expiryMinutes} minutes.";

            $sent = $this->mailer->send();

            // Clean up
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            return (bool) $sent;
        } catch (Exception $e) {
            $err = $this->mailer->ErrorInfo ?: $e->getMessage();
            error_log("Email sending error (OTP): " . $err);
            return false;
        }
    }

    /**
     * Send password reset email. Returns true on success, false on failure.
     */
    public function sendPasswordResetEmail($email, $name, $resetLink) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearCCs();
            $this->mailer->clearBCCs();
            $this->mailer->clearAttachments();

            $this->mailer->addAddress($email, $name);
            $this->mailer->Subject = 'Reset Your ' . (defined('SITE_NAME') ? SITE_NAME : 'Password');

            $emailTemplate = $this->getPasswordResetEmailTemplate($name, $resetLink);
            $this->mailer->Body = $emailTemplate;
            $this->mailer->AltBody = "Reset your password with this link: {$resetLink}";

            $sent = $this->mailer->send();

            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            return (bool) $sent;
        } catch (Exception $e) {
            $err = $this->mailer->ErrorInfo ?: $e->getMessage();
            error_log("Password reset email error: " . $err);
            return false;
        }
    }

    /**
     * OTP HTML template
     */
    private function getOTPEmailTemplate($name, $otp, $expiryMinutes) {
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Our Site';
        $nameEsc  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $otpEsc   = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $expiry   = intval($expiryMinutes);
        $year     = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify Your Account</title>
<style>
    body { font-family: Arial, sans-serif; color: #333; }
    .container { max-width:600px;margin:0 auto;padding:20px; }
    .header { background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:8px 8px 0 0;text-align:center;}
    .content { background:#f8f9fa;padding:30px;border-radius:0 0 8px 8px; }
    .otp { font-size:32px;font-weight:700;color:#667eea;letter-spacing:6px;padding:15px;background:#fff;border-radius:6px;border:2px solid #667eea;text-align:center;display:inline-block;margin:20px 0; }
    .footer { color:#666;font-size:12px;margin-top:20px;text-align:center; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🚀 {$siteName}</h1>
    <p>Account verification</p>
  </div>
  <div class="content">
    <h3>Hello {$nameEsc},</h3>
    <p>Thanks for registering. Use the code below to verify your account:</p>
    <div class="otp">{$otpEsc}</div>
    <p>This code expires in <strong>{$expiry} minutes</strong>. Do not share it with anyone.</p>
  </div>
  <div class="footer">
    <p>&copy; {$year} {$siteName}. This is an automated message — please do not reply.</p>
  </div>
</div>
</body>
</html>
HTML;
    }

    /**
     * Password reset HTML template
     */
    private function getPasswordResetEmailTemplate($name, $resetLink) {
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Our Site';
        $nameEsc  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $resetEsc = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
        $year     = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Password Reset</title>
<style>
    body { font-family: Arial, sans-serif; color: #333; }
    .container { max-width:600px;margin:0 auto;padding:20px; }
    .header { background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;padding:20px;border-radius:8px 8px 0 0;text-align:center;}
    .content { background:#f8f9fa;padding:30px;border-radius:0 0 8px 8px; }
    .btn { display:inline-block;padding:12px 20px;border-radius:8px;background:linear-gradient(135deg,#4facfe,#00f2fe);color:#fff;text-decoration:none;font-weight:bold;margin:18px 0; }
    .footer { color:#666;font-size:12px;margin-top:20px;text-align:center; }
    a.link { color:#0b61d6; word-break:break-all; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🚀 {$siteName}</h1>
    <p>Password reset request</p>
  </div>
  <div class="content">
    <h3>Hello {$nameEsc},</h3>
    <p>We received a request to reset your password. Click the button below to reset it:</p>
    <p><a class="btn" href="{$resetEsc}">Reset My Password</a></p>
    <p>If the button doesn't work, paste this link into your browser:</p>
    <p class="link"><a href="{$resetEsc}">{$resetEsc}</a></p>
    <p>If you didn't request this, just ignore this email.</p>
  </div>
  <div class="footer">
    <p>&copy; {$year} {$siteName}. This is an automated message — please do not reply.</p>
  </div>
</div>
</body>
</html>
HTML;
    }

   // Bulk Email template
    private function getBulkEmailTemplate($name, $subject, $message) {
        $siteName = defined('SITE_NAME') ? SITE_NAME : 'Our Site';
        $nameEsc  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $subjectEsc = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $messageEsc = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$subjectEsc}</title>
<style>
    body { font-family: Arial; color:#333; background:#f4f4f4; margin:0; padding:0; }
    .container { max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 0 10px rgba(0,0,0,0.1);}
    .header { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; padding:20px; text-align:center; }
    .content { padding:30px; line-height:1.6; }
    .message { margin:20px 0; padding:15px; background:#f8f9fa; border-left:5px solid #667eea; border-radius:4px; }
    .footer { background:#f1f1f1; color:#666; font-size:12px; text-align:center; padding:15px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>🚀 {$siteName}</h1>
    <p>{$subjectEsc}</p>
  </div>
  <div class="content">
    <h3>Hello {$nameEsc},</h3>
    <p class="message">{$messageEsc}</p>
    <p>Thank you for being part of {$siteName}.</p>
  </div>
  <div class="footer">
    <p>&copy; {$year} {$siteName}. Do not reply.</p>
  </div>
</div>
</body>
</html>
HTML;
    }
}

