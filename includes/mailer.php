<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

function mailerAutoload(): bool
{
    static $ok = false;
    if ($ok) {
        return true;
    }
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return false;
    }
    require_once $autoload;
    $ok = true;
    return true;
}

function cybeorchIsLocalDev(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? '';

    // Treat loopback addresses as local dev (no hardcoded loopback host literals).
    // 127.0.0.0/8 in integer form is 0x7F000000/0xFF000000.
    $isLoopbackV4 = static function (string $ip): bool {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        $long = ip2long($ip);
        return $long !== false && (($long & 0xFF000000) === 0x7F000000);
    };

    if ($host === '') {
        return false;
    }

    // If host is already an IP, validate directly.
    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $isLoopbackV4($host);
    }

    // Otherwise resolve hostname and check any returned A records.
    $resolved = gethostbynamel($host);
    if (!$resolved) {
        return false;
    }
    foreach ($resolved as $ip) {
        if ($isLoopbackV4((string) $ip)) {
            return true;
        }
    }
    return false;
}

function mailLogPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log';
}

function mailLog(string $level, string $context, string $message, array $extra = []): void
{
    $dir = dirname(mailLogPath());
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . $context . ' — ' . $message;
    if ($extra !== []) {
        unset($extra['password'], $extra['EMAIL_PASS'], $extra['SMTP_PASS']);
        $line .= ' ' . json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents(mailLogPath(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function smtpIsConfigured(): bool
{
    return smtpConfigurationIssue() === null;
}

/** @return string|null Human-readable reason when SMTP is not ready. */
function smtpConfigurationIssue(): ?string
{
    $user = trim((string) (defined('SMTP_USER') ? SMTP_USER : ''));
    $pass = trim((string) (defined('SMTP_PASS') ? SMTP_PASS : ''));
    $bad  = ['your_email@gmail.com', 'yourgmail@gmail.com', 'your_app_password', 'your_16_char_app_password', 'your_smtp_password', ''];
    if ($user === '' || in_array($user, $bad, true) || str_contains($user, 'your_email') || str_contains($user, 'yourgmail')) {
        return 'SMTP username (EMAIL_USER) is missing or still a placeholder. Set it in .env or use setup-smtp.php.';
    }
    if ($pass === '' || in_array($pass, $bad, true)) {
        return 'SMTP password (EMAIL_PASS) is missing or still a placeholder. Set it in .env or use setup-smtp.php.';
    }
    $pass = preg_replace('/\s+/', '', $pass) ?? '';
    $host = strtolower((string) (defined('SMTP_HOST') ? SMTP_HOST : ''));
    $gmailSmtp = str_contains($host, 'gmail');
    if ($gmailSmtp || str_ends_with(strtolower($user), '@gmail.com')) {
        if (!(strlen($pass) === 16 && preg_match('/^[a-zA-Z]{16}$/', $pass))) {
            return 'Gmail SMTP requires a 16-character App Password (letters only, no spaces) in EMAIL_PASS.';
        }
    } elseif (strlen($pass) < 8) {
        return 'EMAIL_PASS is too short for SMTP authentication.';
    }

    return null;
}

/** @return array{host: string, port: int, secure: string, user: string, from: string, configured: bool} */
function smtpConfigurationSummary(): array
{
    return [
        'host'       => (string) (defined('SMTP_HOST') ? SMTP_HOST : ''),
        'port'       => (int) (defined('SMTP_PORT') ? SMTP_PORT : 0),
        'secure'     => (string) (defined('SMTP_SECURE') ? SMTP_SECURE : ''),
        'user'       => (string) (defined('SMTP_USER') ? SMTP_USER : ''),
        'from'       => (string) (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : (defined('SMTP_USER') ? SMTP_USER : '')),
        'configured' => smtpIsConfigured(),
    ];
}

function cybeorchSmtpDebugLevel(): int
{
    $level = (int) (function_exists('cybeorchEnv') ? cybeorchEnv('CYBEORCH_SMTP_DEBUG', '0') : 0);

    return max(0, min(4, $level));
}

function smtpEncryptionMode(): string
{
    $mode = strtolower((string) (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));
    return $mode === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
}

/**
 * @param array{host: string, port: int, secure: string, user: string, pass: string, from_email?: string, from_name?: string}|null $profile
 */
function applyPhpMailerSmtpProfile(PHPMailer $mail, ?array $profile, bool $forceDebug = false): void
{
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 30;

    if ($profile !== null) {
        $mail->Host       = $profile['host'];
        $mail->Port       = (int) $profile['port'];
        $mail->Username   = $profile['user'];
        $mail->Password   = $profile['pass'];
        $secure           = strtolower((string) $profile['secure']);
        $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $fromEmail        = (string) ($profile['from_email'] ?? $profile['user']);
        $fromName         = (string) ($profile['from_name'] ?? (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'CYBEORCH LABS'));
    } else {
        $mail->Host       = SMTP_HOST;
        $mail->Port       = (int) SMTP_PORT;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = smtpEncryptionMode();
        $fromEmail        = defined('SMTP_FROM_EMAIL') && (string) SMTP_FROM_EMAIL !== ''
            ? (string) SMTP_FROM_EMAIL
            : (string) SMTP_USER;
        $fromName         = (string) SMTP_FROM_NAME;
    }

    $mail->setFrom($fromEmail, $fromName);

    if (function_exists('cybeorchEnv') && cybeorchEnv('CYBEORCH_SMTP_INSECURE_SSL', '') === '1') {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    $debugLevel = $forceDebug ? 2 : cybeorchSmtpDebugLevel();
    if ($debugLevel > 0) {
        $mail->SMTPDebug = $debugLevel;
        $mail->Debugoutput = static function (string $str, int $level): void {
            $line = trim($str);
            if ($line !== '') {
                mailLog('debug', 'smtp', $line, ['level' => $level]);
            }
        };
    }
}

function configureOutboundPhpMailer(PHPMailer $mail, bool $forceDebug = false): void
{
    applyPhpMailerSmtpProfile($mail, null, $forceDebug);
}

/** @return array{host: string, port: int, secure: string, user: string, pass: string, from_email: string, from_name: string}|null */
function adminResetGmailSmtpProfile(): ?array
{
    if (!function_exists('cybeorchEnv')) {
        return null;
    }

    $user = strtolower(trim(cybeorchEnv('ADMIN_RESET_EMAIL_USER', '')));
    $pass = preg_replace('/\s+/', '', cybeorchEnv('ADMIN_RESET_EMAIL_PASS', '')) ?? '';
    if ($user === '' || $pass === '') {
        return null;
    }

    if (!str_ends_with($user, '@gmail.com') && !str_ends_with($user, '@googlemail.com')) {
        mailLog('warning', 'admin_password_reset', 'ADMIN_RESET_EMAIL_USER is not Gmail; using site SMTP instead', ['user' => $user]);

        return null;
    }

    if (!(strlen($pass) === 16 && preg_match('/^[a-zA-Z]{16}$/', $pass))) {
        mailLog('warning', 'admin_password_reset', 'ADMIN_RESET_EMAIL_PASS must be a 16-character Gmail App Password; using site SMTP instead');

        return null;
    }

    $settings = cybeorchSmtpSettingsForEmail($user, 'smtp.gmail.com', 587, 'tls');

    return [
        'host'       => $settings['host'],
        'port'       => (int) $settings['port'],
        'secure'     => $settings['secure'],
        'user'       => $user,
        'pass'       => $pass,
        'from_email' => $user,
        'from_name'  => defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'CYBEORCH LABS',
    ];
}

/** @return array{ok: bool, error?: string, transport?: string} */
function deliverAdminResetPhpMailer(PHPMailer $mail, bool $forceDebug = false): array
{
    $recipients = $mail->getToAddresses();
    $gmailProfile = adminResetGmailSmtpProfile();

    if ($gmailProfile !== null) {
        try {
            applyPhpMailerSmtpProfile($mail, $gmailProfile, $forceDebug);
            $mail->send();
            mailLog('info', 'smtp', 'Admin reset email accepted by Gmail SMTP', [
                'subject'   => (string) $mail->Subject,
                'to'        => array_keys($mail->getAllRecipientAddresses()),
                'transport' => 'gmail',
            ]);

            return ['ok' => true, 'transport' => 'gmail'];
        } catch (MailerException $e) {
            $gmailError = mailerSendFailure($mail, $e);
            mailLog('warning', 'admin_password_reset', 'Gmail SMTP failed; retrying with site SMTP', [
                'error' => (string) ($gmailError['error'] ?? ''),
            ]);
            $mail->clearAddresses();
            foreach ($recipients as $recipient) {
                $mail->addAddress((string) ($recipient[0] ?? ''), (string) ($recipient[1] ?? ''));
            }
        }
    }

    $result = deliverPhpMailer($mail, $forceDebug);
    if ($result['ok']) {
        $result['transport'] = 'site_smtp';
    }

    return $result;
}

/** @return array{ok: bool, error?: string} */
function mailerSendFailure(PHPMailer $mail, MailerException $e): array
{
    $error = trim($mail->ErrorInfo !== '' ? (string) $mail->ErrorInfo : $e->getMessage());
    if ($error === '') {
        $error = 'SMTP send failed with no additional details.';
    }

    mailLog('error', 'smtp', $error, smtpConfigurationSummary());

    return ['ok' => false, 'error' => $error];
}

/** @return array{ok: bool, error?: string} */
function deliverPhpMailer(PHPMailer $mail, bool $forceDebug = false): array
{
    try {
        configureOutboundPhpMailer($mail, $forceDebug);
        $mail->send();
        mailLog('info', 'smtp', 'Message accepted by SMTP server', [
            'subject' => (string) $mail->Subject,
            'to'      => array_keys($mail->getAllRecipientAddresses()),
        ]);

        return ['ok' => true];
    } catch (MailerException $e) {
        return mailerSendFailure($mail, $e);
    }
}

/** @return array{ok: bool, error?: string} */
function sendSmtpTestEmail(string $toEmail, ?string $subject = null): array
{
    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed. Run: composer install'];
    }

    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue];
    }

    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, 'Test');
    $mail->Subject = $subject ?? 'CYBEORCH — SMTP test';
    $mail->Body    = 'SMTP working successfully. Admin password reset and OTP emails are ready.';
    $mail->AltBody = 'SMTP working successfully.';

    return deliverPhpMailer($mail, cybeorchSmtpDebugLevel() > 0);
}

/** @return array{ok: bool, error?: string} */
function sendRegistrationOtpEmail(string $toEmail, string $toName, string $otpCode): array
{
    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed. Run: composer install'];
    }

    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue . ' Open ' . url('setup-smtp.php') . ' to configure.'];
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
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;

    return deliverPhpMailer($mail);
}

/** @return array{ok: bool, error?: string} */
function sendAdminNotificationEmail(string $subject, string $html, string $text, ?string $toEmail = null): array
{
    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed. Run: composer install'];
    }

    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue];
    }

    $toEmail = $toEmail ?: (defined('ADMIN_EMAIL') ? (string) ADMIN_EMAIL : '');
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'Admin email not configured.'];
    }

    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, 'Admin');
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;

    return deliverPhpMailer($mail);
}

/** @return array{ok: bool, error?: string} */
function sendAdminPasswordResetEmail(string $toEmail, string $toName, string $resetLink, int $validMinutes = 15): array
{
    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed. Run: composer install'];
    }

    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue . ' Open ' . url('setup-smtp.php') . ' to configure.'];
    }

    $subject = 'CYBEORCH LABS - Admin Password Reset';
    $safeName = htmlspecialchars($toName !== '' ? $toName : 'Admin', ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $brand = htmlspecialchars((string) (defined('SITE_NAME') ? SITE_NAME : 'CYBEORCH LABS'), ENT_QUOTES, 'UTF-8');

    $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#eef2f7;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f7;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d0d7e2;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:linear-gradient(135deg,#0a1628 0%,#3d1a0a 100%);padding:22px 28px;text-align:center;">'
        . '<div style="font-family:Rajdhani,Arial,sans-serif;font-size:26px;font-weight:700;color:#ff6b35;letter-spacing:1px;">' . $brand . '</div>'
        . '<div style="font-family:Arial,sans-serif;font-size:13px;color:#f0c4a8;margin-top:6px;">Admin Panel Password Reset</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px;font-family:Arial,sans-serif;color:#1a2332;font-size:15px;line-height:1.6;">'
        . '<p style="margin:0 0 16px;">Hello <strong>' . $safeName . '</strong>,</p>'
        . '<p style="margin:0 0 20px;">We received a request to reset your <strong>admin panel</strong> password. Click the button below to set a new password. This link expires in <strong>' . (int) $validMinutes . ' minutes</strong> and can only be used once.</p>'
        . '<p style="margin:0 0 24px;text-align:center;">'
        . '<a href="' . $safeLink . '" style="display:inline-block;background:#ff6b35;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 28px;border-radius:8px;font-size:16px;">Reset Admin Password</a>'
        . '</p>'
        . '<p style="margin:0 0 12px;color:#5a6578;font-size:13px;">If the button does not work, copy and paste this link into your browser:</p>'
        . '<p style="margin:0 0 20px;word-break:break-all;font-size:12px;color:#0f3460;"><a href="' . $safeLink . '" style="color:#0f3460;">' . $safeLink . '</a></p>'
        . '<p style="margin:0;color:#5a6578;font-size:13px;">If you did not request this reset, ignore this email. Your password will remain unchanged.</p>'
        . '</td></tr>'
        . '<tr><td style="background:#f4f7fb;padding:16px 28px;text-align:center;font-family:Arial,sans-serif;font-size:12px;color:#7a8fa6;">'
        . '&copy; ' . date('Y') . ' ' . $brand . ' &mdash; Admin security notice'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Hello {$toName},\n\n"
        . "Reset your CYBEORCH LABS admin password using this link (expires in {$validMinutes} minutes):\n"
        . "{$resetLink}\n\n"
        . "If you did not request this, ignore this email.\n";

    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;

    $mail->addReplyTo(
        defined('SMTP_FROM_EMAIL') && (string) SMTP_FROM_EMAIL !== '' ? (string) SMTP_FROM_EMAIL : (string) SMTP_USER,
        (string) SMTP_FROM_NAME
    );
    $mail->Priority = 1;

    $forceDebug = cybeorchSmtpDebugLevel() > 0
        || (function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev());

    return deliverAdminResetPhpMailer($mail, $forceDebug);
}

/** @return array{ok: bool, error?: string} */
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink, int $validMinutes = 15): array
{
    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed. Run: composer install'];
    }

    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue . ' Open ' . url('setup-smtp.php') . ' to configure.'];
    }

    $subject = 'CYBEORCH LABS - Password Reset Request';
    $safeName = htmlspecialchars($toName !== '' ? $toName : 'User', ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $brand    = htmlspecialchars((string) (defined('SITE_NAME') ? SITE_NAME : 'CYBEORCH LABS'), ENT_QUOTES, 'UTF-8');

    $html = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#eef2f7;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f7;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #d0d7e2;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:linear-gradient(135deg,#0a1628 0%,#0f3460 100%);padding:22px 28px;text-align:center;">'
        . '<div style="font-family:Rajdhani,Arial,sans-serif;font-size:26px;font-weight:700;color:#00d4ff;letter-spacing:1px;">' . $brand . '</div>'
        . '<div style="font-family:Arial,sans-serif;font-size:13px;color:#a8c4dc;margin-top:6px;">Password Reset Request</div>'
        . '</td></tr>'
        . '<tr><td style="padding:28px;font-family:Arial,sans-serif;color:#1a2332;font-size:15px;line-height:1.6;">'
        . '<p style="margin:0 0 16px;">Hello <strong>' . $safeName . '</strong>,</p>'
        . '<p style="margin:0 0 20px;">We received a request to reset your account password. Click the button below to create a new password. This link expires in <strong>' . (int) $validMinutes . ' minutes</strong> and can only be used once.</p>'
        . '<p style="margin:0 0 24px;text-align:center;">'
        . '<a href="' . $safeLink . '" style="display:inline-block;background:#00d4ff;color:#050b18;text-decoration:none;font-weight:700;padding:14px 28px;border-radius:8px;font-size:16px;">Reset Password</a>'
        . '</p>'
        . '<p style="margin:0 0 12px;color:#5a6578;font-size:13px;">If the button does not work, copy and paste this link into your browser:</p>'
        . '<p style="margin:0 0 20px;word-break:break-all;font-size:12px;color:#0f3460;"><a href="' . $safeLink . '" style="color:#0f3460;">' . $safeLink . '</a></p>'
        . '<p style="margin:0;color:#5a6578;font-size:13px;">If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>'
        . '</td></tr>'
        . '<tr><td style="background:#f4f7fb;padding:16px 28px;text-align:center;font-family:Arial,sans-serif;font-size:12px;color:#7a8fa6;">'
        . '&copy; ' . date('Y') . ' ' . $brand . ' &mdash; Secure account services'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';

    $text = "Hello {$toName},\n\n"
        . "Reset your CYBEORCH LABS password using this link (expires in {$validMinutes} minutes):\n"
        . "{$resetLink}\n\n"
        . "If you did not request this, ignore this email.\n";

    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text;

    return deliverPhpMailer($mail);
}

/** @return array{ok: bool, error?: string} */
function sendWebinarRegistrationConfirmationEmail(string $toEmail, string $toName, array $webinar, string $registrationNo): array
{
    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed.'];
    }
    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue];
    }
    $title = escHtml((string) ($webinar['title'] ?? 'Webinar'));
    $when = !empty($webinar['scheduled_at']) ? date('d M Y, h:i A', strtotime((string) $webinar['scheduled_at'])) : 'To be announced';
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;">'
        . '<p>Hello ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Your registration for <strong>' . $title . '</strong> is confirmed.</p>'
        . '<p><strong>Registration #:</strong> ' . htmlspecialchars($registrationNo, ENT_QUOTES, 'UTF-8')
        . '<br><strong>When:</strong> ' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</p></div>';
    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'CYBEORCH — Webinar registration confirmed';
    $mail->Body = $html;

    return deliverPhpMailer($mail);
}

/** @return array{ok: bool, error?: string} */
function sendCertificateEmail(
    string $toEmail,
    string $toName,
    string $certificateId,
    string $eventName,
    string $eventDate,
    string $issueDate,
    ?string $pdfPath
): array {
    require_once __DIR__ . '/certificate-template.php';

    if (!mailerAutoload()) {
        return ['ok' => false, 'error' => 'PHPMailer not installed.'];
    }
    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        return ['ok' => false, 'error' => $issue];
    }
    if ($pdfPath === null || !is_file($pdfPath)) {
        return ['ok' => false, 'error' => 'Certificate PDF not found.'];
    }

    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeId = htmlspecialchars($certificateId, ENT_QUOTES, 'UTF-8');
    $safeEvent = htmlspecialchars($eventName, ENT_QUOTES, 'UTF-8');
    $safeIssueDate = htmlspecialchars(certificateFormatDate($issueDate), ENT_QUOTES, 'UTF-8');

    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#1a2744;line-height:1.6;">'
        . '<p>Dear ' . $safeName . ',</p>'
        . '<p><strong>Congratulations!</strong></p>'
        . '<p>Your certificate has been successfully generated and is attached to this email.</p>'
        . '<p><strong>Certificate Details:</strong></p>'
        . '<ul style="padding-left:1.2rem;margin:0 0 1rem;">'
        . '<li><strong>Certificate ID:</strong> ' . $safeId . '</li>'
        . '<li><strong>Course/Webinar:</strong> ' . $safeEvent . '</li>'
        . '<li><strong>Issue Date:</strong> ' . $safeIssueDate . '</li>'
        . '</ul>'
        . '<p>Please keep this certificate for your records.</p>'
        . '<p style="margin-top:24px;">Regards,<br><strong>CYBEORCH LABS</strong></p></div>';

    $text = "Dear {$toName},\n\n"
        . "Congratulations!\n\n"
        . "Your certificate has been successfully generated and is attached to this email.\n\n"
        . "Certificate Details:\n"
        . "- Certificate ID: {$certificateId}\n"
        . "- Course/Webinar: {$eventName}\n"
        . "- Issue Date: " . certificateFormatDate($issueDate) . "\n\n"
        . "Please keep this certificate for your records.\n\n"
        . "Regards,\n"
        . "CYBEORCH LABS\n";

    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = 'Your CYBEORCH LABS Certificate is Ready';
    $mail->Body = $html;
    $mail->AltBody = $text;
    $mail->addAttachment($pdfPath, $certificateId . '.pdf');

    return deliverPhpMailer($mail);
}
