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
$host   = (string) ($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''));
define('SITE_URL', $scheme . '://' . $host . BASE_PATH);

if (PHP_SAPI !== 'cli' && !headers_sent() && !defined('CYBEORCH_JSON_API')) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Database Configuration
// DB_HOST is intentionally empty by default so production/live hosts can be set via env/config.local.php.
// When DB_HOST is empty, the PDO DSN builder omits host entirely (PDO uses its own default host behavior).
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'root');               // XAMPP default; change for production
define('DB_PASS', '');                   // XAMPP default; change for production
define('DB_NAME', 'cybeorch_db');
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'CYBEORCH LAB');
define('SITE_EMAIL', 'info@cybeorch.com');
define('SITE_PHONE_E164', '+919764096069');
define('SITE_PHONE_DISPLAY', '+91 97640 96069');
define('SITE_WHATSAPP', '919764096069');
define('ADMIN_EMAIL', 'admin@cybeorch.com');
define('SITE_TWITTER_HANDLE', 'cybeorch');
define('SITE_TWITTER_URL', 'https://x.com/cybeorch');

// Razorpay Configuration (required for paid webinar/bootcamp checkout)
// Get test keys from https://dashboard.razorpay.com/app/keys
define('RAZORPAY_KEY_ID', 'rzp_test_XXXXXXXXXXXXXXX');      // Replace with your Key ID
define('RAZORPAY_KEY_SECRET', 'XXXXXXXXXXXXXXXXXXXXXXXX');   // Replace with your Key Secret
define('RAZORPAY_CURRENCY', 'INR');

// Manual UPI / bank (secure-payment.php) — override in config.local.php
define('PAYMENT_ACCOUNT_NAME', 'CYBEORCH LAB');
define('PAYMENT_BANK_NAME', 'HDFC Bank');
define('PAYMENT_ACCOUNT_NUMBER', '50200012345678');
define('PAYMENT_IFSC', 'HDFC0001234');
define('PAYMENT_ACCOUNT_TYPE', 'Current');
define('PAYMENT_BRANCH', 'Mumbai, India');
define('PAYMENT_UPI_ID', 'cybeorch@hdfcbank');

// Gateway placeholders (connect live keys in production)
define('PHONEPE_MERCHANT_ID', '');
define('PHONEPE_SALT_KEY', '');

// NxL Wallet Configuration
define('NXL_SIGNUP_BONUS', 25);
define('NXL_REFERRAL_BONUS', 50);
define('NXL_WEBINAR_REWARD', 15);
define('NXL_BOOTCAMP_REWARD', 100);
define('NXL_INR_VALUE', 1);
/** Max share of available wallet balance usable in one payment (e.g. 50 = half of balance). */
define('NXL_MAX_WALLET_BALANCE_PERCENT', 50);

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

/** Admin panel base URL (e.g. https://admin.cybeorch.com). Override via .env ADMIN_URL or config.local.php (use '' for local XAMPP). */
if (!defined('ADMIN_URL')) {
    $adminUrlFromEnv = trim((string) (getenv('ADMIN_URL') ?: ($_ENV['ADMIN_URL'] ?? '')));
    define('ADMIN_URL', $adminUrlFromEnv !== '' ? rtrim($adminUrlFromEnv, '/') : 'https://admin.cybeorch.com');
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

/**
 * Public website authentication (login, register popup, protected pages).
 * Set to true when re-enabling trainee accounts and route protection.
 */
define('PUBLIC_AUTH_ENABLED', true);

require_once __DIR__ . '/auth-feature.php';
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

if (!defined('CYBEORCH_JSON_API') && isPublicAuthEnabled()) {
    enforceWebsiteRegistration();
    enforcePublicSiteAuth();
}

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
