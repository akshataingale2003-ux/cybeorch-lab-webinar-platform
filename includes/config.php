<?php
// ============================================
// CYBEORCH LABS - Configuration File
// ============================================

require_once __DIR__ . '/env-loader.php';
if (is_file(cybeorchEnvPath())) {
    cybeorchLoadDotEnv();
}

// App base path (XAMPP: /CYBEORCH_LAB_Platform/cybeorch) — always the site root, never /admin
$docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$basePath = '';
if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
    $basePath = substr($appRoot, strlen($docRoot));
}
if ($basePath === '' && PHP_SAPI !== 'cli') {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim(dirname($scriptName), '/');
    if (str_ends_with($scriptDir, '/api')) {
        $scriptDir = substr($scriptDir, 0, -4) ?: '';
    }
    // Admin routes must not become BASE_PATH (that caused /admin/login.php → /login.php redirects)
    $adminSegment = trim(cybeorchEnv('ADMIN_BASE_PATH', 'admin'), '/');
    if ($adminSegment !== '' && str_contains($scriptName, '/' . $adminSegment . '/')) {
        $scriptDir = preg_replace('#/' . preg_quote($adminSegment, '#') . '(/|$)#', '$1', $scriptDir) ?? $scriptDir;
        $scriptDir = rtrim($scriptDir, '/');
    } elseif (str_contains($scriptName, '/admin/')) {
        $scriptDir = preg_replace('#/admin(/|$)#', '$1', $scriptDir) ?? $scriptDir;
        $scriptDir = rtrim($scriptDir, '/');
    }
    if ($scriptDir !== '' && $scriptDir !== '/') {
        $basePath = $scriptDir;
    }
}
define('BASE_PATH', $basePath === '' ? '' : $basePath);
define('CYBEORCH_APP_ROOT', $appRoot);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
define('SITE_URL', $scheme . '://' . $host . BASE_PATH);

// Site Configuration (non-secret)
define('SITE_NAME', 'CYBEORCH LABS');
define('SITE_EMAIL', 'info@cybeorch.com');
define('SITE_TWITTER_HANDLE', 'cybeorch');
define('SITE_TWITTER_URL', 'https://x.com/cybeorch');

// NXL Wallet Configuration (1 NXL Credit = NXL_INR_VALUE INR)
define('NXL_WALLET_NAME', 'NXL Wallet');
define('NXL_SIGNUP_BONUS', 25);
define('NXL_REFERRAL_BONUS', 50);
define('NXL_WEBINAR_REWARD', 15);
define('NXL_BOOTCAMP_REWARD', 100);
define('NXL_INR_VALUE', 1.25);
/** Max share of available wallet balance usable in one payment (e.g. 50 = half of balance). */
define('NXL_MAX_WALLET_BALANCE_PERCENT', 50);

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'CYBEORCH_session');

// Secrets — .env (preferred) or includes/config.local.php when .env is absent
if (!is_file(cybeorchEnvPath())) {
    $localConfig = __DIR__ . '/config.local.php';
    if (is_file($localConfig)) {
        require_once $localConfig;
    }
}

cybeorchApplyAppConfigFromEnv();

if (!defined('ADMIN_BASE_PATH')) {
    define('ADMIN_BASE_PATH', trim(cybeorchEnv('ADMIN_BASE_PATH', 'admin'), '/'));
}

$cybeorchSmtpPreset = cybeorchSmtpPreset();
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', $cybeorchSmtpPreset['host']);
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', $cybeorchSmtpPreset['port']);
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', $cybeorchSmtpPreset['secure']);
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', $cybeorchSmtpPreset['from_name']);
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', $cybeorchSmtpPreset['user']);
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', 'your_smtp_password');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', SMTP_USER);
}

// Registration email OTP
define('OTP_LENGTH', 6);
define('OTP_EXPIRE_SECONDS', 300);
define('OTP_RESEND_COOLDOWN', 30);
define('OTP_VERIFY_WINDOW_SECONDS', 1800);
define('OTP_MAX_VERIFY_ATTEMPTS', 5);
define('OTP_MAX_SENDS_PER_HOUR', 5);
define('OTP_DUPLICATE_ENQUIRY_HOURS', 24);

/** Password reset link validity (minutes). */
define('PASSWORD_RESET_TOKEN_TTL_MINUTES', 15);
define('PASSWORD_RESET_MAX_REQUESTS_PER_HOUR', 5);

/** Auto-show webinar closing popup when registration closes within this many hours. */
define('WEBINAR_CLOSING_SOON_THRESHOLD_HOURS', 72);

/** Public certificate verification page (used in QR codes). */
if (!defined('CERTIFICATE_VERIFY_URL')) {
    define('CERTIFICATE_VERIFY_URL', trim((string) cybeorchEnv('CERTIFICATE_VERIFY_URL', 'https://cybeorch.com/verify-certificate')));
}

/** INR per 1 USD for automatic fee conversion (override via CYBEORCH_INR_PER_USD in .env). */
if (!defined('CYBEORCH_INR_PER_USD')) {
    $inrPerUsd = (float) cybeorchEnv('CYBEORCH_INR_PER_USD', '94.988');
    define('CYBEORCH_INR_PER_USD', $inrPerUsd > 0 ? $inrPerUsd : 94.988);
}

// File Upload Limits
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Security
define('HASH_COST', 12);
define('TOKEN_LENGTH', 32);

// Timezone
date_default_timezone_set('Asia/Kolkata');

/**
 * Public website authentication (login, register popup, protected pages).
 * Set to true when re-enabling trainee accounts and route protection.
 */
define('PUBLIC_AUTH_ENABLED', true);

require_once __DIR__ . '/auth-feature.php';
require_once __DIR__ . '/helpers.php';

if (PHP_SAPI !== 'cli') {
    startSession();
}

if (PHP_SAPI !== 'cli' && !headers_sent() && !defined('CYBEORCH_JSON_API')) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/assets.php';
require_once __DIR__ . '/public-footer.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/contact-messages.php';
require_once __DIR__ . '/training-catalog.php';
require_once __DIR__ . '/public-auth.php';
require_once __DIR__ . '/website-registration.php';

if (PHP_SAPI !== 'cli' && !defined('CYBEORCH_DB_PINGED')) {
    define('CYBEORCH_DB_PINGED', true);
    require_once __DIR__ . '/site-database.php';
    dbTry(static function () {
        ensureSiteDatabaseSchemas();
        db()->fetchOne('SELECT 1 AS ok');
        return true;
    }, null);
}

if (!defined('CYBEORCH_JSON_API') && !defined('CYBEORCH_ADMIN_PAGE') && isPublicAuthEnabled()) {
    enforceWebsiteRegistration();
    enforcePublicSiteAuth();
}

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
