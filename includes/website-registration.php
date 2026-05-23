<?php
/**
 * Mandatory website popup registration — session, validation, database.
 */
declare(strict_types=1);

const WEBSITE_REG_SESSION_KEY = 'website_user_registered';
const WEBSITE_REG_ID_KEY      = 'website_user_id';
const WEBSITE_REG_EMAIL_KEY   = 'website_user_email';
const WEBSITE_REG_NAME_KEY    = 'website_user_name';

/** Create website_users table (MySQL). */
function ensureWebsiteUsersSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute('CREATE TABLE IF NOT EXISTS website_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_website_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/** Popup registration completed or user logged into platform. */
function hasWebsiteAccess(): bool
{
    if (isLoggedIn()) {
        return true;
    }
    return isWebsiteUserRegistered();
}

/** Session flag after successful popup registration. */
function isWebsiteUserRegistered(): bool
{
    startSession();
    return !empty($_SESSION[WEBSITE_REG_SESSION_KEY])
        && !empty($_SESSION[WEBSITE_REG_ID_KEY]);
}

/**
 * @return array{ok: bool, message: string, full_name?: string, email?: string, mobile?: string}
 */
function validateWebsiteRegistrationInput(string $fullName, string $email, string $mobile): array
{
    $fullName = trim($fullName);
    $email    = strtolower(trim($email));
    $mobile   = preg_replace('/\s+/', '', trim($mobile)) ?? '';

    if (strlen($fullName) < 2) {
        return ['ok' => false, 'message' => 'Please enter your full name (at least 2 characters).'];
    }
    if (strlen($fullName) > 255) {
        return ['ok' => false, 'message' => 'Full name is too long.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }
    if (strlen($email) > 255) {
        return ['ok' => false, 'message' => 'Email address is too long.'];
    }
    if (strlen($mobile) < 8 || strlen($mobile) > 20) {
        return ['ok' => false, 'message' => 'Please enter a valid mobile number (8–20 characters).'];
    }
    if (!preg_match('/^[0-9+\-()]{8,20}$/', $mobile)) {
        return ['ok' => false, 'message' => 'Mobile number contains invalid characters.'];
    }

    return [
        'ok'        => true,
        'message'   => '',
        'full_name' => $fullName,
        'email'     => $email,
        'mobile'    => $mobile,
    ];
}

/** Secure session after registration (session_regenerate_id). */
function establishWebsiteUserSession(int $userId, string $fullName, string $email): void
{
    startSession();
    $csrf = $_SESSION['csrf_token'] ?? null;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION = [
        'created'                 => time(),
        WEBSITE_REG_SESSION_KEY     => true,
        WEBSITE_REG_ID_KEY          => $userId,
        WEBSITE_REG_EMAIL_KEY       => strtolower(trim($email)),
        WEBSITE_REG_NAME_KEY        => $fullName,
    ];

    if ($csrf !== null) {
        $_SESSION['csrf_token'] = $csrf;
    }
}

/**
 * Register in website_users (prepared statements). Handles duplicate email.
 *
 * @return array{success: bool, message: string, user_id?: int}
 */
function registerWebsiteUser(string $fullName, string $email, string $mobile): array
{
    ensureWebsiteUsersSchema();

    $valid = validateWebsiteRegistrationInput($fullName, $email, $mobile);
    if (!$valid['ok']) {
        return ['success' => false, 'message' => $valid['message']];
    }

    $fullName = $valid['full_name'];
    $email    = $valid['email'];
    $mobile   = $valid['mobile'];

    $existing = db()->fetchOne(
        'SELECT id, full_name, email FROM website_users WHERE email = ? LIMIT 1',
        [$email]
    );
    if ($existing) {
        return [
            'success' => true,
            'message' => 'Welcome back! You are already registered.',
            'user_id' => (int) $existing['id'],
        ];
    }

    try {
        $userId = db()->insert(
            'INSERT INTO website_users (full_name, email, mobile) VALUES (?, ?, ?)',
            [$fullName, $email, $mobile]
        );
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
            $row = db()->fetchOne('SELECT id FROM website_users WHERE email = ? LIMIT 1', [$email]);
            if ($row) {
                return [
                    'success' => true,
                    'message' => 'Welcome back! You are already registered.',
                    'user_id' => (int) $row['id'],
                ];
            }
        }
        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    return [
        'success' => true,
        'message' => 'Registration successful! Welcome to CYBEORCH.',
        'user_id' => $userId,
    ];
}

/**
 * Create/link platform user registration & trainee account so dashboard & locked pages work.
 */
function provisionPlatformUserAfterWebsiteRegister(string $fullName, string $email, string $mobile): void
{
    require_once __DIR__ . '/auth.php';

    $email = strtolower(trim($email));
    $user  = db()->fetchOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);

    if ($user) {
        Auth::establishUserSession((int) $user['id']);
        return;
    }

    $tempPass = bin2hex(random_bytes(16)) . 'A1';
    $result   = Auth::register([
        'full_name'        => strlen($fullName) >= 3 ? $fullName : str_pad($fullName, 3, '.'),
        'email'            => $email,
        'phone'            => $mobile,
        'password'         => $tempPass,
        'confirm_password' => $tempPass,
        'referral_code'    => '',
        'agree_terms'      => '1',
    ]);

    if ($result['success'] && !empty($result['user_id'])) {
        Auth::establishUserSession((int) $result['user_id']);
    }
}

/** Pages allowed before popup registration. */
function websiteRegistrationExemptScripts(): array
{
    return [
        'index.php',
        'register-website.php',
        'send_otp.php',
        'verify_otp.php',
        'setup-smtp.php',
        'login.php',
        'logout.php',
        'forgot-password.php',
        'send_magic_link.php',
        'magic-login.php',
        'terms.php',
        'privacy.php',
        'favicon.php',
    ];
}

/** Redirect guests to homepage popup until registered. */
function enforceWebsiteRegistration(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($scriptPath, '/admin/')) {
        return;
    }

    $script = basename($scriptPath);
    if (in_array($script, websiteRegistrationExemptScripts(), true)) {
        return;
    }

    if (hasWebsiteAccess()) {
        return;
    }

    if (!headers_sent()) {
        header('Location: ' . url('index.php?register_required=1'));
        exit;
    }
}
