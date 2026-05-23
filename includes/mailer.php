<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

function mailerAutoload(): void
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Run: composer install (PHPMailer required).');
    }
    require_once $autoload;
    $ok = true;
}

function cybeorchIsLocalDev(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $host === 'localhost'
        || $host === '127.0.0.1'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1:');
}

function smtpIsConfigured(): bool
{
    $user = trim((string) (defined('SMTP_USER') ? SMTP_USER : ''));
    $pass = trim((string) (defined('SMTP_PASS') ? SMTP_PASS : ''));
    $bad  = ['your_email@gmail.com', 'yourgmail@gmail.com', 'your_app_password', 'your_16_char_app_password', 'your_smtp_password', ''];
    if ($user === '' || $pass === '' || in_array($user, $bad, true) || in_array($pass, $bad, true)) {
        return false;
    }
    if (str_contains($user, 'your_email') || str_contains($user, 'yourgmail')) {
        return false;
    }
    $pass = preg_replace('/\s+/', '', $pass) ?? '';
    $host = strtolower((string) (defined('SMTP_HOST') ? SMTP_HOST : ''));
    $gmailSmtp = str_contains($host, 'gmail');
    if ($gmailSmtp || str_ends_with(strtolower($user), '@gmail.com')) {
        return strlen($pass) === 16 && preg_match('/^[a-zA-Z]{16}$/', $pass);
    }
    return strlen($pass) >= 8;
}

function smtpEncryptionMode(): string
{
    $mode = strtolower((string) (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));
    return $mode === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
}

/** @return array{ok: bool, error?: string} */
function sendSmtpTestEmail(string $toEmail, ?string $subject = null): array
{
    mailerAutoload();

    if (!smtpIsConfigured()) {
        return ['ok' => false, 'error' => 'SMTP not configured.'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = (int) SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = smtpEncryptionMode();
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, 'Test');
        $mail->Subject = $subject ?? 'CYBEORCH — SMTP test';
        $mail->Body    = 'SMTP working successfully. OTP emails are ready.';
        $mail->AltBody = 'SMTP working successfully.';
        $mail->send();
        return ['ok' => true];
    } catch (MailerException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** @return array{ok: bool, error?: string} */
function sendRegistrationOtpEmail(string $toEmail, string $toName, string $otpCode): array
{
    mailerAutoload();

    if (!smtpIsConfigured()) {
        return ['ok' => false, 'error' => 'SMTP not configured. Create .env (EMAIL_USER, EMAIL_PASS) or open ' . url('setup-smtp.php')];
    }

    $minutes = (int) ceil((defined('OTP_EXPIRE_SECONDS') ? OTP_EXPIRE_SECONDS : 300) / 60);
    $subject = 'CYBEORCH — Your verification code';
    $html    = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;">'
        . '<div style="background:#1e4a8a;color:#fff;padding:14px 18px;border-radius:6px 6px 0 0;"><strong>CYBEORCH</strong> Email Verification</div>'
        . '<div style="background:#fff;padding:22px;border:1px solid #d0d7e2;">'
        . '<p>Hello ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Your OTP code is:</p>'
        . '<p style="font-size:28px;font-weight:bold;letter-spacing:6px;color:#1e4a8a;">' . htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p style="color:#555;font-size:14px;">Valid for <strong>' . $minutes . ' minutes</strong>. Do not share this code.</p>'
        . '</div></div>';
    $text = "CYBEORCH OTP: {$otpCode}\nExpires in {$minutes} minutes.\n";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = (int) SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = smtpEncryptionMode();
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return ['ok' => true];
    } catch (MailerException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
