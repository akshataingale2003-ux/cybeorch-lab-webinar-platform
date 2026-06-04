<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

const REG_PENDING_KEY      = 'cybeorch_reg_pending';
const REG_OTP_SENT_KEY     = 'cybeorch_reg_otp_sent';
const REG_OTP_EXPIRES_KEY  = 'cybeorch_reg_otp_expires';
const REG_OTP_VERIFIED_KEY = 'cybeorch_otp_verified';

const MSG_OTP_SENT      = 'OTP Sent Successfully';
const MSG_OTP_FAILED    = 'Failed to send OTP email. Please try again.';
const MSG_INVALID_OTP   = 'Invalid OTP';
const MSG_OTP_VERIFIED  = 'OTP Verified Successfully';
const MSG_REG_SUCCESS   = 'Registration Successful';

function otpJsonResponse(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureRegistrationOtpSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensureWebsiteUsersSchema();

    db()->execute('CREATE TABLE IF NOT EXISTS registration_pending_otps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        otp_hash VARCHAR(255) NOT NULL,
        otp_expiry DATETIME NOT NULL,
        verify_attempts INT NOT NULL DEFAULT 0,
        last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_pending_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/** @return array{ok: bool, message: string, full_name?: string, email?: string, mobile?: string} */
function validatePopupRegistrationInput(string $fullName, string $email, string $mobile): array
{
    $fullName = trim($fullName);
    $email    = strtolower(trim($email));
    $mobile   = preg_replace('/\s+/', '', trim($mobile)) ?? '';

    if ($fullName === '' || strlen($fullName) < 2) {
        return ['ok' => false, 'message' => 'Please enter a valid full name (min 2 characters).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }
    $digits = preg_replace('/\D/', '', $mobile) ?? '';
    if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
        $digits = substr($digits, -10);
    }
    if (strlen($digits) !== 10) {
        return ['ok' => false, 'message' => 'Please enter a valid 10-digit contact number.'];
    }

    return ['ok' => true, 'message' => '', 'full_name' => $fullName, 'email' => $email, 'mobile' => $digits];
}

function setRegPending(string $n, string $e, string $m): void
{
    startSession();
    $_SESSION[REG_PENDING_KEY] = ['full_name' => $n, 'email' => $e, 'mobile' => $m];
}

function getRegPending(): ?array
{
    startSession();
    $p = $_SESSION[REG_PENDING_KEY] ?? null;
    return is_array($p) && !empty($p['email']) ? $p : null;
}

function clearRegPending(): void
{
    startSession();
    unset(
        $_SESSION[REG_PENDING_KEY],
        $_SESSION[REG_OTP_SENT_KEY],
        $_SESSION[REG_OTP_EXPIRES_KEY],
        $_SESSION[REG_OTP_VERIFIED_KEY]
    );
}

function isRegOtpVerified(): bool
{
    startSession();
    return !empty($_SESSION[REG_OTP_VERIFIED_KEY]) && getRegPending() !== null;
}

function generateOtpCode(): string
{
    $len = (int) (defined('OTP_LENGTH') ? OTP_LENGTH : 6);
    return str_pad((string) random_int(0, (int) str_pad('9', $len, '9')), $len, '0', STR_PAD_LEFT);
}

/** @return array{success: bool, message: string, expires_at?: int, email_sent?: bool} */
function sendPopupRegistrationOtp(string $n, string $e, string $m, bool $resend = false): array
{
    startSession();
    unset($_SESSION[REG_OTP_VERIFIED_KEY]);

    $v = validatePopupRegistrationInput($n, $e, $m);
    if (!$v['ok']) {
        return ['success' => false, 'message' => $v['message']];
    }

    $n = $v['full_name'];
    $e = $v['email'];
    $m = $v['mobile'];

    try {
        ensureRegistrationOtpSchema();
    } catch (Throwable $ex) {
        return ['success' => false, 'message' => 'Database error. Please ensure MySQL is running.'];
    }

    if (db()->fetchOne('SELECT id FROM website_users WHERE email = ? LIMIT 1', [$e])
        || db()->fetchOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$e])) {
        return ['success' => false, 'message' => 'This email is already registered.'];
    }

    if ($resend && !empty($_SESSION[REG_OTP_SENT_KEY])) {
        $cooldown = (int) (defined('OTP_RESEND_COOLDOWN') ? OTP_RESEND_COOLDOWN : 30);
        $elapsed  = time() - (int) $_SESSION[REG_OTP_SENT_KEY];
        if ($elapsed < $cooldown) {
            return ['success' => false, 'message' => 'Wait ' . ($cooldown - $elapsed) . 's before resending.'];
        }
    }

    $code  = generateOtpCode();
    $expTs = time() + (int) (defined('OTP_EXPIRE_SECONDS') ? OTP_EXPIRE_SECONDS : 300);
    $exp   = date('Y-m-d H:i:s', $expTs);
    $now   = date('Y-m-d H:i:s');

    mailLog('info', 'registration_otp', 'Sending OTP email', ['to' => $e]);

    $mail = sendRegistrationOtpEmail($e, $n, $code);
    if (!$mail['ok']) {
        $technical = (string) ($mail['error'] ?? 'Unknown SMTP error');
        mailLog('error', 'registration_otp', $technical, ['to' => $e]);
        $message = MSG_OTP_FAILED;
        if (function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev()) {
            $message .= ' ' . $technical;
        }
        return ['success' => false, 'message' => $message, 'email_sent' => false];
    }

    $hash = password_hash($code, PASSWORD_DEFAULT);
    if (db()->fetchOne('SELECT id FROM registration_pending_otps WHERE email = ?', [$e])) {
        db()->execute(
            'UPDATE registration_pending_otps SET full_name=?, mobile=?, otp_hash=?, otp_expiry=?, verify_attempts=0, last_sent_at=? WHERE email=?',
            [$n, $m, $hash, $exp, $now, $e]
        );
    } else {
        db()->insert(
            'INSERT INTO registration_pending_otps (full_name, email, mobile, otp_hash, otp_expiry, last_sent_at) VALUES (?,?,?,?,?,?)',
            [$n, $e, $m, $hash, $exp, $now]
        );
    }

    setRegPending($n, $e, $m);
    $_SESSION[REG_OTP_SENT_KEY]    = time();
    $_SESSION[REG_OTP_EXPIRES_KEY] = $expTs;

    mailLog('info', 'registration_otp', 'OTP email delivered and stored', ['to' => $e]);

    return [
        'success'    => true,
        'message'    => MSG_OTP_SENT,
        'expires_at' => $expTs,
        'email_sent' => true,
    ];
}

/** @return array{success: bool, message: string} */
function verifyPopupRegistrationOtp(string $otpInput): array
{
    startSession();

    if (empty($_SESSION[REG_OTP_SENT_KEY]) || !getRegPending()) {
        return ['success' => false, 'message' => 'Please send OTP first.'];
    }

    $pending = getRegPending();
    $e       = $pending['email'];
    $otp     = preg_replace('/\D/', '', trim($otpInput)) ?? '';
    $len     = (int) (defined('OTP_LENGTH') ? OTP_LENGTH : 6);

    if (strlen($otp) !== $len) {
        return ['success' => false, 'message' => MSG_INVALID_OTP];
    }

    ensureRegistrationOtpSchema();
    $row = db()->fetchOne('SELECT * FROM registration_pending_otps WHERE email = ? LIMIT 1', [$e]);

    if (!$row || !password_verify($otp, (string) $row['otp_hash'])) {
        if ($row) {
            db()->execute('UPDATE registration_pending_otps SET verify_attempts = verify_attempts + 1 WHERE email = ?', [$e]);
        }
        return ['success' => false, 'message' => MSG_INVALID_OTP];
    }

    if (strtotime((string) $row['otp_expiry']) < time()) {
        return ['success' => false, 'message' => MSG_INVALID_OTP . ' (expired). Resend OTP.'];
    }

    if ((int) $row['verify_attempts'] >= (int) (defined('OTP_MAX_VERIFY_ATTEMPTS') ? OTP_MAX_VERIFY_ATTEMPTS : 5)) {
        return ['success' => false, 'message' => 'Too many attempts. Resend OTP.'];
    }

    $_SESSION[REG_OTP_VERIFIED_KEY] = true;

    return ['success' => true, 'message' => MSG_OTP_VERIFIED];
}

/** @return array{success: bool, message: string, redirect?: string} */
function completeVerifiedRegistration(): array
{
    startSession();

    if (!isRegOtpVerified()) {
        return ['success' => false, 'message' => 'Please verify OTP before registering.'];
    }

    $pending = getRegPending();
    if (!$pending) {
        return ['success' => false, 'message' => 'Session expired. Please start again.'];
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');
    if ($password === '' || $confirm === '') {
        return ['success' => false, 'message' => 'Please enter and confirm your password.'];
    }
    if ($password !== $confirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    require_once __DIR__ . '/auth.php';
    $platform = Auth::register([
        'full_name'        => $pending['full_name'],
        'email'            => $pending['email'],
        'phone'            => $pending['mobile'],
        'password'         => $password,
        'confirm_password' => $confirm,
        'referral_code'    => trim((string) ($_POST['referral_code'] ?? '')),
        'agree_terms'      => '1',
    ]);
    if (!$platform['success'] || empty($platform['user_id'])) {
        return ['success' => false, 'message' => $platform['message']];
    }

    $result = registerWebsiteUser($pending['full_name'], $pending['email'], $pending['mobile']);
    if (!$result['success'] || empty($result['user_id'])) {
        Auth::establishUserSession((int) $platform['user_id']);
    } else {
        establishWebsiteUserSession((int) $result['user_id'], $pending['full_name'], $pending['email']);
        Auth::establishUserSession((int) $platform['user_id']);
    }

    db()->execute('DELETE FROM registration_pending_otps WHERE email = ?', [$pending['email']]);

    clearRegPending();

    startSession();
    $_SESSION['website_register_success'] = MSG_REG_SUCCESS;

    return [
        'success'  => true,
        'message'  => MSG_REG_SUCCESS,
        'redirect' => absoluteUrl('index.php?registered=1'),
    ];
}
