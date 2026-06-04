<?php

// ============================================
// CYBEORCH LAB - Helper Functions
// ============================================

/** Cookie path for this app install (subdirectory-safe). */
function sessionCookiePath(): string
{
    $path = defined('BASE_PATH') ? (string) BASE_PATH : '';
    if ($path === '' || $path === '/') {
        return '/';
    }
    return str_ends_with($path, '/') ? $path : $path . '/';
}

function isHttpsRequest(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Remove session cookie from the browser. */
function expireSessionCookie(): void
{
    if (headers_sent()) {
        return;
    }
    $name = session_name();
    if ($name === '') {
        $name = defined('SESSION_NAME') ? SESSION_NAME : 'CYBEORCH_SESSION';
    }
    $params = session_get_cookie_params();
    setcookie($name, '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'] ?: sessionCookiePath(),
        'domain'   => $params['domain'] ?? '',
        'secure'   => $params['secure'] ?? false,
        'httponly' => $params['httponly'] ?? true,
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

/** Clear user registration & trainee session keys only (keeps admin session if present). */
function clearUserSession(): void
{
    startSession();
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['user_email'],
        $_SESSION['auth_checked']
    );
}

/** Clear admin session keys only. */
function clearAdminSession(): void
{
    startSession();
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_role']
    );
}

/** Fully end session: unset data, destroy, expire cookie. */
function destroySessionCompletely(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        startSession();
    }
    $_SESSION = [];
    session_unset();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    expireSessionCookie();
}

/** Log out user registration & trainee — full session destroy + cookie cleared. */
function logoutUser(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        startSession();
    }
    $_SESSION = [];
    session_unset();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    expireSessionCookie();
}

/** Log out admin — full session destroy + cookie cleared. */
function logoutAdmin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        startSession();
    }
    $_SESSION = [];
    session_unset();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    expireSessionCookie();
}

// Secure session start — only after explicit login should user_id exist
function startSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    if (!ob_get_level()) {
        ob_start();
    }

    if (!defined('SESSION_NAME')) {
        define('SESSION_NAME', 'CYBEORCH_SESSION');
    }
    if (!defined('SESSION_LIFETIME')) {
        define('SESSION_LIFETIME', 86400);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => sessionCookiePath(),
        'secure'   => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - (int) $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

/**
 * Confirm session user_id exists in database (prevents stale/garbage sessions).
 */
function refreshUserSessionFromDatabase(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }

    $uid = (int) $_SESSION['user_id'];
    if ($uid <= 0) {
        clearUserSession();
        return false;
    }

    if (isset($_SESSION['auth_checked']) && (int) $_SESSION['auth_checked'] === $uid) {
        return true;
    }

    if (!function_exists('dbTry')) {
        return true;
    }

    $user = dbTry(
        static fn () => db()->fetchOne(
            'SELECT id, full_name, email, COALESCE(is_blocked, 0) AS is_blocked FROM users WHERE id = ? AND deleted_at IS NULL',
            [$uid]
        ),
        null
    );

    if (!$user || !empty($user['is_blocked'])) {
        clearUserSession();
        return false;
    }

    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['auth_checked'] = (int) $user['id'];

    return true;
}

function isLoggedIn(): bool
{
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    return refreshUserSessionFromDatabase();
}

function isAdminLoggedIn(): bool
{
    startSession();
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    $aid = (int) $_SESSION['admin_id'];
    if ($aid <= 0) {
        clearAdminSession();
        return false;
    }
    if (isset($_SESSION['admin_checked']) && (int) $_SESSION['admin_checked'] === $aid) {
        return true;
    }
    if (!function_exists('dbTry')) {
        return true;
    }
    $admin = dbTry(
        static fn () => db()->fetchOne('SELECT id, full_name FROM admin WHERE id = ?', [$aid]),
        null
    );
    if (!$admin) {
        clearAdminSession();
        return false;
    }
    $_SESSION['admin_id']   = (int) $admin['id'];
    $_SESSION['admin_name'] = $admin['full_name'] ?? 'Admin';
    $_SESSION['admin_checked'] = (int) $admin['id'];
    return true;
}

// Require login (skipped when PUBLIC_AUTH_ENABLED is false in config.php)
function requireLogin(string $redirect = 'login.php') {
    if (!isPublicAuthEnabled()) {
        return;
    }
    if (!isLoggedIn()) {
        $return = publicAuthReturnPath();
        $dest = url($redirect);
        if ($return !== '') {
            $dest .= (str_contains($redirect, '?') ? '&' : '?') . 'redirect=' . rawurlencode($return);
        }
        header('Location: ' . $dest);
        exit;
    }
}

// Require admin login
function requireAdminLogin() {

    if (!isAdminLoggedIn()) {
        $login = adminUrl('login.php');
        $return = adminAuthReturnPath();
        if ($return !== '') {
            $login .= '?redirect=' . rawurlencode($return);
        }
        header('Location: ' . $login);
        exit;
    }
}

/** Path under admin/ to return to after login (e.g. bootcamps.php?action=add). */
function adminAuthReturnPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $base = BASE_PATH;
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    $path = ltrim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
    if (!str_starts_with($path, 'admin/') || $path === 'admin/login.php' || $path === 'admin/logout.php') {
        return '';
    }
    $query = parse_url($uri, PHP_URL_QUERY);
    return $query ? $path . '?' . $query : $path;
}

function safeAdminRedirectPath(string $path): string
{
    $path = ltrim($path, '/');
    if ($path === '' || !str_starts_with($path, 'admin/') || str_contains($path, '..')) {
        return 'admin/dashboard.php';
    }
    $script = explode('?', $path)[0];
    if (in_array($script, ['admin/login.php', 'admin/logout.php', 'admin/index.php'], true)) {
        return 'admin/dashboard.php';
    }
    return $path;
}

// Indian Rupee symbol (UTF-8) — use instead of &#8377; to avoid mojibake (â‚¹)
function rupee(): string
{
    return "\u{20B9}";
}

function formatRupee(float|int $amount, int $decimals = 0): string
{
    return rupee() . number_format((float) $amount, $decimals);
}

/** Bank & UPI details for secure payment page. */
function cybeorchBankPaymentDetails(): array
{
    return [
        'account_name'   => defined('PAYMENT_ACCOUNT_NAME') ? PAYMENT_ACCOUNT_NAME : SITE_NAME,
        'bank_name'      => defined('PAYMENT_BANK_NAME') ? PAYMENT_BANK_NAME : '',
        'account_number' => defined('PAYMENT_ACCOUNT_NUMBER') ? PAYMENT_ACCOUNT_NUMBER : '',
        'ifsc'           => defined('PAYMENT_IFSC') ? PAYMENT_IFSC : '',
        'account_type'   => defined('PAYMENT_ACCOUNT_TYPE') ? PAYMENT_ACCOUNT_TYPE : 'Current',
        'branch'         => defined('PAYMENT_BRANCH') ? PAYMENT_BRANCH : '',
        'upi_id'         => defined('PAYMENT_UPI_ID') ? PAYMENT_UPI_ID : '',
    ];
}

function cybeorchPaymentReferenceCode(string $slug, float $amount): string
{
    $slugPart = preg_replace('/[^a-z0-9]/i', '', $slug);

    return 'CYB-' . strtoupper(substr($slugPart !== '' ? $slugPart : 'PAY', 0, 12)) . '-' . (int) round($amount);
}

/** Payment method tiles for secure-payment.php (integration placeholders). */
function cybeorchPaymentMethodOptions(): array
{
    return [
        'razorpay' => [
            'label' => 'Razorpay',
            'desc'  => 'Cards, UPI, net banking & wallets via Razorpay Checkout.',
            'icon'  => 'fa-bolt',
            'badge' => 'Recommended',
        ],
        'phonepe' => [
            'label' => 'PhonePe',
            'desc'  => 'Pay with PhonePe app or PhonePe Payment Gateway.',
            'icon'  => 'fa-mobile-screen-button',
            'badge' => 'Popular',
        ],
        'upi' => [
            'label' => 'UPI',
            'desc'  => 'Google Pay, Paytm, BHIM & any UPI app.',
            'icon'  => 'fa-qrcode',
            'badge' => '',
        ],
        'card' => [
            'label' => 'Debit / Credit Card',
            'desc'  => 'Visa, Mastercard, RuPay & international cards.',
            'icon'  => 'fa-credit-card',
            'badge' => '',
        ],
        'netbanking' => [
            'label' => 'Net Banking',
            'desc'  => 'All major Indian banks supported.',
            'icon'  => 'fa-building-columns',
            'badge' => '',
        ],
        'wallet' => [
            'label' => 'Wallets',
            'desc'  => 'Paytm, Mobikwik, Amazon Pay & more.',
            'icon'  => 'fa-wallet',
            'badge' => '',
        ],
    ];
}

// Sanitize input
function sanitize($input): string {

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail(string $email): bool {

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** User-facing message when Gmail-only validation fails. */
const GMAIL_VALIDATION_MESSAGE = 'Please enter a valid Gmail address';

/**
 * Strict Gmail-only check: local-part@domain must end with @gmail.com (not googlemail or other hosts).
 */
function isValidGmailAddress(string $email): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 254) {
        return false;
    }
    if (!preg_match('/^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?@gmail\.com$/', $email)) {
        return false;
    }
    $local = strstr($email, '@', true);
    if ($local === false || str_contains($local, '..')) {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate Indian phone number
function isValidPhone(string $phone): bool {

    return preg_match('/^[6-9]\d{9}$/', $phone);
}

// Generate token
function generateToken(int $length = 16): string {

    return bin2hex(random_bytes($length));
}

// JSON response
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): void {

    http_response_code($code);

    header('Content-Type: application/json');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

// CSRF protection
function generateCSRF(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messages
function setFlash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    startSession();
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function showFlash(): string {
    $flash = getFlash();
    if (!$flash) {
        return '';
    }
    $type = $flash['type'] === 'error' ? 'danger' : $flash['type'];
    $icon = $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle');
    $msg  = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    return '<div class="alert alert-' . $type . ' mb-3"><i class="fas fa-' . $icon . ' me-2"></i>' . $msg . '</div>';
}

function redirectToRegistrationSuccess(string $type, int $id): void {
    header('Location: ' . url('registration-success.php?type=' . rawurlencode($type) . '&id=' . $id));
    exit;
}

function redirectWith(string $path, string $type, string $message): void {
    setFlash($type, $message);
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

function url(string $path = ''): string {
    $base = rtrim(BASE_PATH, '/');
    if ($path === '' || $path === '/') {
        return $base . '/';
    }
    if (str_starts_with($path, '/#')) {
        return $base . $path;
    }
    if (str_starts_with($path, '/')) {
        return $base . $path;
    }
    return $base . '/' . ltrim($path, '/');
}

/** Shareable referral signup path (no scheme/host; e.g. cybeorch-cybeorch-new/index.php?register_required=1&ref=CODE). */
/*function referralShareUrl(string $referralCode): string
{
    $path = 'index.php?register_required=1&ref=' . rawurlencode(trim($referralCode));

    return ltrim(url($path), '/');
} */

/** Asset URL with filemtime cache-buster so browsers pick up replaced images. */
function urlVersioned(string $path): string
{
    $relative = ltrim(str_replace('\\', '/', $path), '/');
    $fsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $href = url($path);
    if (!is_file($fsPath)) {
        return $href;
    }
    $sep = str_contains($href, '?') ? '&' : '?';
    return $href . $sep . 'v=' . filemtime($fsPath);
}

/** Full URL for favicon and static assets (works in XAMPP subfolders). */
function absoluteUrl(string $path = ''): string
{
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/** Base URL for the admin panel (ADMIN_URL or SITE_URL when ADMIN_URL is empty). */
function adminBaseUrl(): string
{
    if (defined('ADMIN_URL') && ADMIN_URL !== '') {
        return rtrim((string) ADMIN_URL, '/');
    }

    return rtrim(SITE_URL, '/');
}

/** Full admin URL, e.g. https://admin.cybeorch.com/admin/login.php */
function adminUrl(string $path = 'dashboard.php'): string
{
    $path = ltrim($path, '/');
    if (!str_starts_with($path, 'admin/')) {
        $path = 'admin/' . $path;
    }

    return adminBaseUrl() . '/' . $path;
}

function generateReferralCode(string $name): string {
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 4));
    if (strlen($prefix) < 2) {
        $prefix = 'CYB';
    }
    return $prefix . random_int(1000, 9999);
}

function generateInvoiceNo(): string {
    return 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function creditWallet(int $userId, float $amount, string $reason, ?int $referenceId = null, string $description = ''): void {
    require_once __DIR__ . '/nxl-wallet.php';
    $standardRewards = ['signup_bonus', 'webinar_reward', 'referral_bonus', 'bootcamp_reward', 'special_reward'];
    if (in_array($reason, $standardRewards, true)) {
        grantNxlReward($userId, $reason, $referenceId, $description !== '' ? $description : null);
        return;
    }
    recordWalletTransaction($userId, 'credit', $amount, $reason, $referenceId, $description, $reason, $description);
    notifyAdminNxlCredit($userId, $amount, $reason, $description);
}

function sendNotification(int $userId, string $type, string $title, string $message, ?int $referenceId = null, ?string $referenceType = null): void {
    require_once __DIR__ . '/db.php';
    db()->execute(
        'INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $type, $title, $message, $referenceId, $referenceType]
    );
}

function getWalletBalance(int $userId): float {
    require_once __DIR__ . '/db.php';
    $wallet = db()->fetchOne('SELECT balance FROM wallet WHERE user_id = ?', [$userId]);
    return $wallet ? (float) $wallet['balance'] : 0.0;
}

function debitWallet(int $userId, float $amount, string $reason, ?int $referenceId = null, string $description = ''): void {
    require_once __DIR__ . '/nxl-wallet.php';
    recordWalletTransaction($userId, 'debit', $amount, $reason, $referenceId, $description, $reason, $description);
}

function generateRegNo(string $prefix = 'CYB'): string {
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(3))) . '-' . date('ymd');
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

function timeAgo(?string $datetime): string {
    if (empty($datetime)) {
        return '';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ' min' . ($mins === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 2592000) {
        $weeks = (int) floor($diff / 604800);
        return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
    }
    return date('M j, Y', $timestamp);
}

function renderPublicDbAlert(): void
{
    if (!function_exists('hasDbError') || !hasDbError()) {
        return;
    }
    $msg = function_exists('dbErrorMessage') ? dbErrorMessage() : '';
    if ($msg === '') {
        return;
    }
    echo '<div class="cybeorch-db-alert" role="alert">'
        . '<div class="container">'
        . '<i class="fas fa-database me-2" aria-hidden="true"></i>'
        . '<span>' . htmlspecialchars($msg) . '</span>'
        . '</div></div>';
}

function renderStandardViewport(): void
{
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
}
