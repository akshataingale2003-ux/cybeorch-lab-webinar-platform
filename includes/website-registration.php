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
    if (!isPublicAuthEnabled()) {
        return true;
    }
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

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['created']                   = time();
    $_SESSION[WEBSITE_REG_SESSION_KEY]   = true;
    $_SESSION[WEBSITE_REG_ID_KEY]         = $userId;
    $_SESSION[WEBSITE_REG_EMAIL_KEY]      = strtolower(trim($email));
    $_SESSION[WEBSITE_REG_NAME_KEY]       = $fullName;
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

        require_once __DIR__ . '/form-submissions.php';
        recordFormSubmission([
            'form_key'          => 'website-registration',
            'form_label'        => 'Website popup registration',
            'source_page'       => 'index.php',
            'full_name'         => $fullName,
            'email'             => $email,
            'phone'             => $mobile,
            'summary'           => 'Website access registration',
            'storage_table'     => 'website_users',
            'storage_record_id' => $userId,
        ]);
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

/** Pages allowed before popup registration (public marketing & catalog). */
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
        'reset-password.php',
        'send-reset-email.php',
        'send_magic_link.php',
        'magic-login.php',
        'terms.php',
        'privacy.php',
        'favicon.php',
        'webinars.php',
        'bootcamps.php',
        'checkout.php',
        'products.php',
        'services.php',
        'solutions.php',
        'about.php',
        'corporate-services.php',
        'portfolio.php',
        'case-studies.php',
        'company-profile.php',
        'contact.php',
        'freelancer.php',
        'freelance-projects.php',
        'live-projects.php',
        'hands-on-projects.php',
        'enquire-enroll.php',
        'webinar-registration-confirm.php',
        'registration-success.php',
        'secure-payment.php',
        'assignment-register.php',
        'start-project.php',
        'book-consulting.php',
    ];
}

/** @return array<int, array<string, mixed>> */
function getWebsiteUsers(int $limit = 500): array
{
    return adminFetchWebsiteUsers(['limit' => $limit, 'status' => 'all']);
}

/**
 * @param array{status?: string, q?: string, limit?: int} $filters
 * @return array<int, array<string, mixed>>
 */
function websiteUsersSupportsColumn(string $column): bool
{
    require_once __DIR__ . '/admin-actions.php';
    ensureAdminActionsSchema();
    return adminTableHasColumn('website_users', $column);
}

/** Linked platform account (users table) for a website registration email. */
function getPlatformUserForWebsiteEmail(string $email): ?array
{
    require_once __DIR__ . '/admin-actions.php';
    require_once __DIR__ . '/admin-users.php';
    ensureAdminActionsSchema();
    ensureAdminUsersSchema();

    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $userActive = adminTableHasColumn('users', 'deleted_at')
        ? adminSqlActive('u')
        : '1=1';

    $plainSel = adminTableHasColumn('users', 'password_plain') ? ', u.password_plain' : '';
    $row = dbTry(
        static fn () => db()->fetchOne(
            'SELECT u.id, u.email, u.password' . $plainSel . ', COALESCE(u.is_blocked, 0) AS is_blocked
             FROM users u
             WHERE LOWER(u.email) = ? AND ' . $userActive . '
             LIMIT 1',
            [$email]
        ),
        null
    );

    return $row ?: null;
}

function adminWebsiteUserHasPlatformPassword(array $row): bool
{
    $hash = (string) ($row['platform_password_hash'] ?? '');
    if ($hash !== '' && str_starts_with($hash, '$2')) {
        return true;
    }
    $platform = getPlatformUserForWebsiteEmail((string) ($row['email'] ?? ''));
    $hash = (string) ($platform['password'] ?? '');
    return $hash !== '' && str_starts_with($hash, '$2');
}

function adminWebsiteUserDisplayPassword(array $row): string
{
    require_once __DIR__ . '/admin-users.php';
    $plain = trim((string) ($row['platform_password_plain'] ?? ''));
    if ($plain !== '') {
        return $plain;
    }
    $platform = getPlatformUserForWebsiteEmail((string) ($row['email'] ?? ''));
    if ($platform) {
        return adminUserDisplayPassword($platform);
    }
    return '';
}

function adminFetchWebsiteUsers(array $filters = []): array
{
    ensureWebsiteUsersSchema();
    require_once __DIR__ . '/admin-actions.php';
    require_once __DIR__ . '/admin-users.php';
    ensureAdminActionsSchema();
    ensureAdminUsersSchema();

    $status = $filters['status'] ?? 'all';
    $q = trim((string) ($filters['q'] ?? ''));
    $limit = max(1, min(500, (int) ($filters['limit'] ?? 500)));

    $where = ['1=1'];
    $params = [];
    $hasDeleted = websiteUsersSupportsColumn('deleted_at');
    $hasBlocked = websiteUsersSupportsColumn('is_blocked');

    if ($status === 'deleted' && $hasDeleted) {
        $where[] = 'w.deleted_at IS NOT NULL';
    } elseif ($status === 'blocked' && $hasBlocked) {
        if ($hasDeleted) {
            $where[] = adminSqlActive('w');
        }
        $where[] = 'w.is_blocked = 1';
    } elseif ($status === 'all') {
        if ($hasDeleted) {
            $where[] = adminSqlActive('w');
        }
    } elseif ($status === 'active') {
        if ($hasDeleted) {
            $where[] = adminSqlActive('w');
        }
        if ($hasBlocked) {
            $where[] = '(w.is_blocked = 0 OR w.is_blocked IS NULL)';
        }
    }

    if ($q !== '') {
        $where[] = '(w.full_name LIKE ? OR w.email LIKE ? OR w.mobile LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like]);
    }

    $userActive = adminTableHasColumn('users', 'deleted_at') ? adminSqlActive('u') : '1=1';

    $plainCol = adminTableHasColumn('users', 'password_plain')
        ? ', u.password_plain AS platform_password_plain'
        : '';
    $sql = 'SELECT w.*, u.id AS platform_user_id, u.password AS platform_password_hash' . $plainCol . '
            FROM website_users w
            LEFT JOIN users u ON LOWER(u.email) = LOWER(w.email) AND ' . $userActive . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY w.created_at DESC
            LIMIT ' . $limit;

    return db()->fetchAll($sql, $params);
}

function adminCountWebsiteUsersByStatus(): array
{
    ensureWebsiteUsersSchema();
    $hasDeleted = websiteUsersSupportsColumn('deleted_at');
    $hasBlocked = websiteUsersSupportsColumn('is_blocked');

    $activeWhere = '1=1';
    if ($hasDeleted) {
        $activeWhere = adminSqlActive('w') . ($hasBlocked ? ' AND (w.is_blocked = 0 OR w.is_blocked IS NULL)' : '');
    } elseif ($hasBlocked) {
        $activeWhere = '(w.is_blocked = 0 OR w.is_blocked IS NULL)';
    }

    return [
        'active'  => (int) dbTry(fn () => db()->fetchOne(
            'SELECT COUNT(*) AS c FROM website_users w WHERE ' . $activeWhere
        )['c'] ?? 0, 0),
        'blocked' => $hasBlocked ? (int) dbTry(fn () => db()->fetchOne(
            'SELECT COUNT(*) AS c FROM website_users w WHERE '
            . ($hasDeleted ? adminSqlActive('w') . ' AND ' : '')
            . 'w.is_blocked = 1'
        )['c'] ?? 0, 0) : 0,
        'deleted' => $hasDeleted ? (int) dbTry(fn () => db()->fetchOne(
            'SELECT COUNT(*) AS c FROM website_users w WHERE w.deleted_at IS NOT NULL'
        )['c'] ?? 0, 0) : 0,
        'all'     => (int) dbTry(fn () => getWebsiteUsersCount(), 0),
    ];
}

function getWebsiteUserById(int $id): ?array
{
    ensureWebsiteUsersSchema();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM website_users WHERE id = ?', [$id]);
    return $row ?: null;
}

function getWebsiteUsersCount(): int
{
    ensureWebsiteUsersSchema();
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM website_users')['c'] ?? 0);
}

/** Redirect guests to homepage popup until registered. */
function enforceWebsiteRegistration(): void
{
    if (!isPublicAuthEnabled() || PHP_SAPI === 'cli') {
        return;
    }

    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (isAdminAreaRequest()) {
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
