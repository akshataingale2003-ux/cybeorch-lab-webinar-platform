<?php

// ============================================
// CYBEORCH LABS - Helper Functions
// ============================================

/** Cookie path for this app install (subdirectory-safe). */
function sessionCookiePath(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (str_starts_with($host, 'admin.')) {
        return '/';
    }

    $path = defined('BASE_PATH') ? (string) BASE_PATH : '';
    if ($path === '' || $path === '/') {
        return '/';
    }
    // Folder names with spaces/% break cookie path matching in browsers (common on XAMPP).
    if (preg_match('/\s|%/', $path) !== 0) {
        return '/';
    }
    return str_ends_with($path, '/') ? $path : $path . '/';
}

/** Admin URL path segment from .env (e.g. "admin"). Empty when admin PHP files sit at web root. */
function adminBasePath(): string
{
    return defined('ADMIN_BASE_PATH') ? (string) ADMIN_BASE_PATH : 'admin';
}

/** True when admin pages are served with no URL prefix (ADMIN_BASE_PATH is empty). */
function adminPrefixInBasePath(): bool
{
    return adminBasePath() === '';
}

/** Whether the current request is served from the admin/ PHP tree. */
function isAdminAreaRequest(): bool
{
    $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    if ($scriptFile !== '' && str_contains($scriptFile, '/admin/')) {
        return true;
    }
    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $adminBase = adminBasePath();
    if ($adminBase !== '' && str_contains($scriptPath, '/' . $adminBase . '/')) {
        return true;
    }
    return str_contains($scriptPath, '/admin/');
}

/**
 * Build an admin route: {ADMIN_BASE_PATH}/{file.php} (legacy admin/ prefix is stripped).
 */
function normalizeAdminRoute(string $path): string
{
    $path = ltrim(str_replace('\\', '/', trim($path)), '/');
    if ($path === '') {
        $path = 'dashboard.php';
    }

    $query = '';
    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
    }

    if (str_starts_with($path, 'admin/')) {
        $path = substr($path, 6);
    }

    $base = adminBasePath();
    if ($base !== '' && str_starts_with($path, $base . '/')) {
        $path = substr($path, strlen($base) + 1);
    }

    $route = $base !== '' ? $base . '/' . $path : $path;
    return $query !== '' ? $route . '?' . $query : $route;
}

/** Full admin page URL: {SITE_URL}/{ADMIN_BASE_PATH}/{file.php} */
function adminUrl(string $path = 'dashboard.php'): string
{
    return absoluteUrl(normalizeAdminRoute($path));
}

/** Admin login page URL. */
function adminLoginUrl(array $query = []): string
{
    $url = adminUrl('login.php');
    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
    return $url;
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

    if (headers_sent($sentFile, $sentLine)) {
        error_log('[session] startSession skipped — headers already sent in ' . $sentFile . ':' . $sentLine);
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
    if (defined('SESSION_LIFETIME')) {
        ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
    }

    session_name(SESSION_NAME);

    $cookieLifetime = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 86400;
    session_set_cookie_params([
        'lifetime' => $cookieLifetime,
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

/** SQL fragment: active (non-deleted) users row — safe when deleted_at column is missing. */
function userActiveSql(string $alias = ''): string
{
    if (!function_exists('adminTableHasColumn')) {
        require_once __DIR__ . '/admin-actions.php';
    }
    if (function_exists('ensureAdminActionsSchema')) {
        ensureAdminActionsSchema();
    }
    $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    if (function_exists('adminTableHasColumn') && adminTableHasColumn('users', 'deleted_at')) {
        return '(' . $prefix . 'deleted_at IS NULL OR ' . $prefix . 'deleted_at = \'0000-00-00 00:00:00\')';
    }

    return '1=1';
}

/**
 * Load platform user for session validation (schema-safe).
 *
 * @return array<string, mixed>|null
 */
function fetchSessionUserById(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }
    if (!function_exists('dbTry')) {
        return null;
    }
    if (!function_exists('adminTableHasColumn')) {
        require_once __DIR__ . '/admin-actions.php';
    }
    if (function_exists('ensureAdminActionsSchema')) {
        ensureAdminActionsSchema();
    }

    $blockedSel = (function_exists('adminTableHasColumn') && adminTableHasColumn('users', 'is_blocked'))
        ? ', COALESCE(is_blocked, 0) AS is_blocked'
        : ', 0 AS is_blocked';

    $user = dbTry(
        static fn () => db()->fetchOne(
            'SELECT id, full_name, email' . $blockedSel . ' FROM users WHERE id = ? AND ' . userActiveSql(''),
            [$userId]
        ),
        null
    );

    return is_array($user) ? $user : null;
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

    $user = fetchSessionUserById($uid);

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

const ADMIN_LOGIN_REDIRECT_KEY = 'admin_login_redirect';

function storeAdminLoginRedirect(string $path): void
{
    startSession();
    $path = trim($path);
    if ($path === '') {
        return;
    }
    $_SESSION[ADMIN_LOGIN_REDIRECT_KEY] = $path;
}

function pullAdminLoginRedirect(): string
{
    startSession();
    $path = (string) ($_SESSION[ADMIN_LOGIN_REDIRECT_KEY] ?? '');
    unset($_SESSION[ADMIN_LOGIN_REDIRECT_KEY]);

    return safeAdminRedirectPath($path);
}

// Require admin login
function requireAdminLogin() {

    if (!isAdminLoggedIn()) {
        $return = adminAuthReturnPath();
        if ($return !== '') {
            storeAdminLoginRedirect($return);
        }
        header('Location: ' . adminLoginUrl());
        exit;
    }
}

/** Path under the admin area to return to after login (e.g. bootcamps.php?action=add). */
function adminAuthReturnPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $base = (string) BASE_PATH;
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    $path = ltrim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
    $adminBase = adminBasePath();

    if ($adminBase !== '') {
        if (!str_starts_with($path, $adminBase . '/')) {
            return '';
        }
        $path = substr($path, strlen($adminBase) + 1);
    } elseif (str_starts_with($path, 'admin/')) {
        $path = substr($path, 6);
    }

    $script = explode('?', $path)[0];
    if ($path === '' || $script === 'index.php' || $script === 'login.php' || $script === 'logout.php') {
        return '';
    }
    if (str_contains($path, '..')) {
        return '';
    }
    $query = parse_url($uri, PHP_URL_QUERY);
    return $query ? $path . '?' . $query : $path;
}

function safeAdminRedirectPath(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if (str_starts_with($path, 'admin/')) {
        $path = substr($path, 6);
    }
    $adminBase = adminBasePath();
    if ($adminBase !== '' && str_starts_with($path, $adminBase . '/')) {
        $path = substr($path, strlen($adminBase) + 1);
    }
    if ($path === '' || str_contains($path, '..')) {
        return normalizeAdminRoute('dashboard.php');
    }
    $script = explode('?', $path)[0];
    if (in_array($script, ['login.php', 'logout.php', 'index.php'], true)) {
        return normalizeAdminRoute('dashboard.php');
    }
    return normalizeAdminRoute($path);
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

/** INR per 1 USD (from env CYBEORCH_INR_PER_USD or catalog default ~95). */
function cybeorchInrPerUsd(): float
{
    if (defined('CYBEORCH_INR_PER_USD')) {
        $rate = (float) CYBEORCH_INR_PER_USD;
        if ($rate > 0) {
            return $rate;
        }
    }

    return 94.988;
}

/** Convert INR fee to whole-dollar USD (e.g. ₹37,909.19 → $399). */
function cybeorchConvertInrToUsd(float $inr): float
{
    if ($inr <= 0) {
        return 0.0;
    }

    return round($inr / cybeorchInrPerUsd(), 0);
}

function cybeorchFormatUsdFee(float $usd): string
{
    return '$' . number_format($usd, 0) . ' USD';
}

/** @return array{inr: string, usd: string, label: string} */
function cybeorchDualPriceLines(float $inr, int $inrDecimals = 2): array
{
    $usd = cybeorchConvertInrToUsd($inr);
    $inrLine = formatRupee($inr, $inrDecimals);
    $usdLine = cybeorchFormatUsdFee($usd);

    return [
        'inr'   => $inrLine,
        'usd'   => $usdLine,
        'label' => $inrLine . ' (' . $usdLine . ')',
    ];
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

// Sanitize input (HTML-escape — use for immediate output only, not database storage)
function sanitize($input): string {

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/** Trim user text for database storage without HTML-encoding. */
function sanitizePlainText($input): string
{
    $text = trim((string) $input);
    $text = str_replace("\0", '', $text);

    return $text;
}

/**
 * Decode HTML entities stored in the database (legacy double-encoding cleanup).
 * Loops until stable so &amp;amp; becomes &.
 */
function decodeStoredText(string $text): string
{
    if ($text === '') {
        return '';
    }

    // Leading & was lost through repeated htmlspecialchars + sanitize cycles (amp;amp;...).
    if (preg_match('/(?<![&])amp;(?:amp;)+/i', $text)) {
        $text = preg_replace('/(?<![&])amp;(?:amp;)+/i', '&', $text);
    }

    $prev = null;
    while ($prev !== $text) {
        $prev = $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $text;
}

/** Whether stored text still contains encoded HTML entities or corrupted amp chains. */
function storedTextHasEncodedEntities(string $text): bool
{
    if ($text === '') {
        return false;
    }

    return (bool) preg_match('/&(?:amp|quot|#0?39|lt|gt);|(?<![&])amp;(?:amp;)+/i', $text);
}

/** Trim, decode legacy entities, and return plain text for database storage. */
function sanitizeStoredText($input): string
{
    return decodeStoredText(sanitizePlainText((string) $input));
}

/** Safe HTML output: decode legacy entities once, then escape for the page. */
function escHtml(string $text): string
{
    return htmlspecialchars(decodeStoredText($text), ENT_QUOTES, 'UTF-8');
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
    return isset($_SESSION['csrf_token']) && $token !== '' && hash_equals($_SESSION['csrf_token'], $token);
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

/**
 * Public site URL for links in outbound email (reset links, etc.).
 * Set CYBEORCH_SITE_URL in .env on production so emails do not contain localhost URLs.
 */
function cybeorchPublicSiteUrl(): string
{
    if (function_exists('cybeorchEnv')) {
        $override = trim(cybeorchEnv('CYBEORCH_SITE_URL', ''));
        if ($override !== '') {
            return rtrim($override, '/');
        }
    }

    return rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
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
    require_once __DIR__ . '/nxl-wallet.php';
    ensureNxlWalletForUser($userId);
    $wallet = db()->fetchOne('SELECT balance FROM wallet WHERE user_id = ?', [$userId]);
    return $wallet ? (float) $wallet['balance'] : 0.0;
}

/** @return array{success: bool, message: string, balance_after?: float} */
function debitWallet(int $userId, float $amount, string $reason, ?int $referenceId = null, string $description = ''): array {
    require_once __DIR__ . '/nxl-wallet.php';
    try {
        $tx = recordWalletTransaction($userId, 'debit', $amount, $reason, $referenceId, $description, $reason, $description);
        return ['success' => true, 'message' => 'Wallet debited.', 'balance_after' => $tx['balance_after']];
    } catch (Throwable $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
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

require_once __DIR__ . '/referral-helpers.php';
require_once __DIR__ . '/heading-divider.php';
