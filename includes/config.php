<?php
// ============================================
// CYBEORCH LAB - Configuration File
// ============================================

// App base path (XAMPP: /CYBEORCH_LAB_Platform/cybeorch)
$docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
$basePath = '';
if ($docRoot !== '' && $appRoot !== '' && str_starts_with($appRoot, $docRoot)) {
    $basePath = substr($appRoot, strlen($docRoot));
}
if ($basePath === '' && PHP_SAPI !== 'cli') {
    $scriptDir = rtrim(dirname(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if (str_ends_with($scriptDir, '/api')) {
        $scriptDir = substr($scriptDir, 0, -4) ?: '';
    }
    if ($scriptDir !== '' && $scriptDir !== '/') {
        $basePath = $scriptDir;
    }
}
define('BASE_PATH', $basePath === '' ? '' : $basePath);
define('CYBEORCH_APP_ROOT', $appRoot);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $scheme . '://' . $host . BASE_PATH);

if (PHP_SAPI !== 'cli' && !headers_sent() && !defined('CYBEORCH_JSON_API')) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Database Configuration (XAMPP defaults)
define('DB_HOST', '127.0.0.1');          // Use 127.0.0.1 on Windows/XAMPP (more reliable than "localhost")
define('DB_PORT', 3306);
define('DB_USER', 'root');               // XAMPP default; change for production
define('DB_PASS', '');                   // XAMPP default; change for production
define('DB_NAME', 'cybeorch_db');
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'CYBEORCH LAB');
define('SITE_EMAIL', 'info@cybeorch.com');
define('ADMIN_EMAIL', 'admin@cybeorch.com');
define('SITE_TWITTER_HANDLE', 'cybeorch');
define('SITE_TWITTER_URL', 'https://x.com/cybeorch');

// Razorpay Configuration (required for paid webinar/bootcamp checkout)
// Get test keys from https://dashboard.razorpay.com/app/keys
define('RAZORPAY_KEY_ID', 'rzp_test_XXXXXXXXXXXXXXX');      // Replace with your Key ID
define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');   // Replace with your Key Secret
define('RAZORPAY_CURRENCY', 'INR');

// NxL Wallet Configuration
define('NXL_SIGNUP_BONUS', 25);
define('NXL_REFERRAL_BONUS', 50);
define('NXL_WEBINAR_REWARD', 15);
define('NXL_BOOTCAMP_REWARD', 100);

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'CYBEORCH_session');

// Email (SMTP) — .env (EMAIL_USER/EMAIL_PASS) like Node tutorial, or config.local.php
require_once __DIR__ . '/env-loader.php';

if (is_file(cybeorchEnvPath())) {
    cybeorchLoadDotEnv();
    cybeorchApplySmtpFromEnv();
} else {
    $localConfig = __DIR__ . '/config.local.php';
    if (is_file($localConfig)) {
        require_once $localConfig;
    }
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

if (!defined('CYBEORCH_OTP_DEV_MODE')) {
    $otpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    define(
        'CYBEORCH_OTP_DEV_MODE',
        $otpHost === 'localhost'
            || $otpHost === '127.0.0.1'
            || str_starts_with($otpHost, 'localhost:')
            || str_starts_with($otpHost, '127.0.0.1:')
    );
}

// File Upload Limits
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Security
define('HASH_COST', 12);
define('TOKEN_LENGTH', 32);

// Registration: Gmail + company domains auto-authorize via Google Sign-In
// Comma-separated extra domains (e.g. 'acme.com,partner.org')
define('REGISTER_COMPANY_EMAIL_DOMAINS', 'cybeorch.com');

// Google OAuth — redirect URI: {SITE_URL}/oauth-callback.php
define('GOOGLE_OAUTH_CLIENT_ID', '');
define('GOOGLE_OAUTH_CLIENT_SECRET', '');

// Timezone
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/helpers.php';
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

enforceWebsiteRegistration();
enforcePublicSiteAuth();

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
