<?php
declare(strict_types=1);

require_once __DIR__ . '/public-catalog.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/popup-registration.php';

const WEBINAR_INTAKE_SESSION_KEY = 'cybeorch_webinar_intake_token';

function ensureWebinarRegistrationIntakeSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensurePublicCatalogSchemas();

    db()->execute('CREATE TABLE IF NOT EXISTS webinar_registration_intakes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        webinar_id INT NOT NULL,
        user_id INT DEFAULT NULL,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        organization VARCHAR(255) DEFAULT NULL,
        city VARCHAR(120) NOT NULL,
        confirm_token VARCHAR(64) NOT NULL,
        status ENUM(\'pending\',\'confirmed\',\'cancelled\') NOT NULL DEFAULT \'pending\',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME DEFAULT NULL,
        UNIQUE KEY uniq_confirm_token (confirm_token),
        KEY idx_webinar_email (webinar_id, email),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}

/** Resolve DB webinar id for homepage/static catalog cards. */
function publicResolveWebinarIdForCatalogCard(array $cat): int
{
    $mustBeFree = !empty($cat['is_free']);
    $candidates = array_filter([
        trim((string) ($cat['webinar_slug'] ?? '')),
        trim((string) ($cat['slug'] ?? '')),
    ]);

    $aliases = [
        'cybersecurity' => ['cybersec-fundamentals', 'cybersecurity-awareness-webinar'],
        'devops'        => ['devops-pipeline-security-webinar', 'cybersecurity-awareness-webinar'],
        'ai-ml'         => ['ai-tools-automation-webinar'],
        'blockchain'    => ['ethical-hacking-pentest'],
    ];
    $key = (string) ($cat['slug'] ?? '');
    if (isset($aliases[$key])) {
        $candidates = array_merge($candidates, $aliases[$key]);
    }

    foreach (array_unique($candidates) as $slug) {
        if ($slug === '') {
            continue;
        }
        $webinar = publicFetchWebinarBySlug($slug);
        if (!$webinar) {
            continue;
        }
        if ($mustBeFree && empty($webinar['is_free'])) {
            continue;
        }
        return (int) $webinar['id'];
    }

    if ($mustBeFree) {
        foreach (publicFetchWebinars(25) as $webinar) {
            if (!empty($webinar['is_free'])) {
                return (int) $webinar['id'];
            }
        }
    }

    return 0;
}

function webinarRegistrationConfirmPath(int $webinarId, string $token = ''): string
{
    $path = 'webinar-registration-confirm.php?webinar_id=' . max(1, $webinarId);
    if ($token !== '') {
        $path .= '&token=' . rawurlencode($token);
    }
    return $path;
}

function webinarRegistrationConfirmUrl(int $webinarId, string $token = ''): string
{
    return url(webinarRegistrationConfirmPath($webinarId, $token));
}

/** @return array{full_name: string, email: string, mobile: string, organization: string}|null */
function webinarIntakeProfileFromSession(): ?array
{
    require_once __DIR__ . '/website-registration.php';

    if (isLoggedIn()) {
        $user = dbTry(static fn () => db()->fetchOne(
            'SELECT full_name, email, phone FROM users WHERE id = ?',
            [(int) $_SESSION['user_id']]
        ), null);
        if ($user && trim((string) ($user['email'] ?? '')) !== '') {
            return [
                'full_name'    => trim((string) ($user['full_name'] ?? '')),
                'email'        => trim((string) ($user['email'] ?? '')),
                'mobile'       => trim((string) ($user['phone'] ?? '')),
                'organization' => '',
            ];
        }
    }

    if (isWebsiteUserRegistered()) {
        $websiteUserId = (int) ($_SESSION[WEBSITE_REG_ID_KEY] ?? 0);
        $websiteUser = $websiteUserId > 0
            ? dbTry(static fn () => db()->fetchOne(
                'SELECT full_name, email, mobile FROM website_users WHERE id = ?',
                [$websiteUserId]
            ), null)
            : null;
        if ($websiteUser && trim((string) ($websiteUser['email'] ?? '')) !== '') {
            return [
                'full_name'    => trim((string) ($websiteUser['full_name'] ?? $_SESSION[WEBSITE_REG_NAME_KEY] ?? '')),
                'email'        => trim((string) ($websiteUser['email'] ?? $_SESSION[WEBSITE_REG_EMAIL_KEY] ?? '')),
                'mobile'       => trim((string) ($websiteUser['mobile'] ?? '')),
                'organization' => '',
            ];
        }
    }

    return null;
}

/** @return array<string, mixed>|null */
function fetchPendingWebinarIntakeByEmail(int $webinarId, string $email): ?array
{
    ensureWebinarRegistrationIntakeSchema();
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $row = db()->fetchOne(
        "SELECT * FROM webinar_registration_intakes
         WHERE webinar_id = ? AND LOWER(email) = ? AND status = 'pending'
         ORDER BY id DESC LIMIT 1",
        [$webinarId, $email]
    );

    return $row ?: null;
}

function ensureWebinarIntakeForConfirm(int $webinarId): ?array
{
    $profile = webinarIntakeProfileFromSession();
    if (!$profile || trim($profile['mobile']) === '') {
        return null;
    }

    $existing = fetchPendingWebinarIntakeByEmail($webinarId, $profile['email']);
    if ($existing) {
        startSession();
        $_SESSION[WEBINAR_INTAKE_SESSION_KEY] = (string) ($existing['confirm_token'] ?? '');
        return $existing;
    }

    return null;
}

function webinarRegistrationRegisterFreeUrl(int $webinarId): string
{
    if ($webinarId < 1) {
        return url('webinars.php');
    }

    return webinarRegistrationConfirmUrl($webinarId);
}

function webinarRegisterFormPath(int $webinarId): string
{
    return webinarRegistrationConfirmPath($webinarId);
}

/** @return array{ok: bool, message: string, field?: string, full_name?: string, email?: string, mobile?: string, organization?: string, city?: string} */
function validateWebinarIntakeInput(string $fullName, string $email, string $mobile, string $city, string $organization = ''): array
{
    $base = validatePopupRegistrationInput($fullName, $email, $mobile);
    if (!$base['ok']) {
        return $base;
    }

    $city = trim($city);
    $organization = trim($organization);

    if ($city === '' || strlen($city) < 2) {
        return ['ok' => false, 'message' => 'Please enter your city.', 'field' => 'city'];
    }

    return [
        'ok'           => true,
        'message'      => '',
        'full_name'    => $base['full_name'],
        'email'        => $base['email'],
        'mobile'       => $base['mobile'],
        'city'         => $city,
        'organization' => $organization !== '' ? $organization : null,
    ];
}

function webinarRegistrationActiveSql(string $alias = 'wr'): string
{
    return publicWebinarRegistrationActiveSql($alias);
}

/** @return array<string, mixed>|null */
function fetchActiveWebinarRegistrationByUser(int $userId, int $webinarId): ?array
{
    $active = webinarRegistrationActiveSql('wr');
    $row = db()->fetchOne(
        "SELECT wr.* FROM webinar_registrations wr WHERE wr.user_id = ? AND wr.webinar_id = ? AND {$active} LIMIT 1",
        [$userId, $webinarId]
    );

    return $row ?: null;
}

function userHasActiveWebinarRegistration(int $userId, int $webinarId): bool
{
    return fetchActiveWebinarRegistrationByUser($userId, $webinarId) !== null;
}

function webinarEmailAlreadyRegistered(int $webinarId, string $email): bool
{
    ensureWebinarRegistrationIntakeSchema();
    $email = strtolower(trim($email));
    $active = webinarRegistrationActiveSql('wr');

    if (db()->fetchOne(
        "SELECT wr.id FROM webinar_registrations wr INNER JOIN users u ON u.id = wr.user_id
         WHERE wr.webinar_id = ? AND LOWER(u.email) = ? AND {$active} LIMIT 1",
        [$webinarId, $email]
    )) {
        return true;
    }

    return (bool) db()->fetchOne(
        "SELECT id FROM webinar_registration_intakes
         WHERE webinar_id = ? AND LOWER(email) = ? AND status = 'pending' LIMIT 1",
        [$webinarId, $email]
    );
}

function webinarEmailHasActiveRegistration(int $webinarId, string $email): bool
{
    ensureWebinarRegistrationIntakeSchema();
    $email = strtolower(trim($email));
    $active = webinarRegistrationActiveSql('wr');

    return (bool) db()->fetchOne(
        "SELECT wr.id FROM webinar_registrations wr INNER JOIN users u ON u.id = wr.user_id
         WHERE wr.webinar_id = ? AND LOWER(u.email) = ? AND {$active} LIMIT 1",
        [$webinarId, $email]
    );
}

/** @return array{success: bool, message: string, token?: string, redirect?: string, field?: string} */
function saveWebinarRegistrationIntake(int $webinarId, array $data, ?int $loggedInUserId = null): array
{
    ensureWebinarRegistrationIntakeSchema();

    $webinar = publicFetchWebinarById($webinarId);
    if (!$webinar) {
        return ['success' => false, 'message' => 'Webinar not found or no longer available.'];
    }
    if (empty($webinar['is_free'])) {
        return ['success' => false, 'message' => 'This webinar requires payment. Please use the paid registration flow.'];
    }

    $maxSeats = (int) ($webinar['max_seats'] ?? 0);
    $registered = (int) ($webinar['registered_seats'] ?? 0);
    if ($maxSeats > 0 && $registered >= $maxSeats) {
        return ['success' => false, 'message' => 'Sorry, this webinar is full. No seats available.'];
    }

    $validated = validateWebinarIntakeInput(
        (string) ($data['full_name'] ?? ''),
        (string) ($data['email'] ?? ''),
        (string) ($data['mobile'] ?? ''),
        (string) ($data['city'] ?? ''),
        (string) ($data['organization'] ?? '')
    );
    if (!$validated['ok']) {
        return ['success' => false, 'message' => $validated['message'], 'field' => $validated['field'] ?? null];
    }

    if (webinarEmailAlreadyRegistered($webinarId, $validated['email'])) {
        return ['success' => false, 'message' => 'This email is already registered for this webinar.', 'field' => 'email'];
    }

    $token = bin2hex(random_bytes(24));
    $userId = ($loggedInUserId !== null && $loggedInUserId > 0) ? $loggedInUserId : null;

    db()->insert(
        'INSERT INTO webinar_registration_intakes
         (webinar_id, user_id, full_name, email, mobile, organization, city, confirm_token, status)
         VALUES (?,?,?,?,?,?,?,?,\'pending\')',
        [$webinarId, $userId, $validated['full_name'], $validated['email'], $validated['mobile'],
            $validated['organization'], $validated['city'], $token]
    );

    startSession();
    $_SESSION[WEBINAR_INTAKE_SESSION_KEY] = $token;

    try {
        require_once __DIR__ . '/form-submissions.php';
        recordFormSubmission([
            'form_key'    => 'webinar-registration-intake',
            'form_label'  => 'Webinar registration form',
            'source_page' => 'webinar-registration-confirm.php',
            'full_name'   => $validated['full_name'],
            'email'       => $validated['email'],
            'phone'       => $validated['mobile'],
            'summary'     => ($webinar['title'] ?? 'Webinar') . ' — intake',
            'payload'     => array_merge($validated, ['webinar_id' => $webinarId, 'title' => $webinar['title'] ?? '']),
        ]);
    } catch (Throwable $e) {
    }

    return [
        'success'  => true,
        'message'  => 'Details saved. Review and confirm your registration.',
        'token'    => $token,
        'redirect' => absoluteUrl(webinarRegistrationConfirmPath($webinarId, $token)),
    ];
}

/** @return array<string, mixed>|null */
function fetchWebinarIntakeByToken(string $token, int $webinarId = 0): ?array
{
    ensureWebinarRegistrationIntakeSchema();
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $params = [$token];
    $sql = "SELECT * FROM webinar_registration_intakes WHERE confirm_token = ? AND status = 'pending'";
    if ($webinarId > 0) {
        $sql .= ' AND webinar_id = ?';
        $params[] = $webinarId;
    }

    $row = db()->fetchOne($sql . ' LIMIT 1', $params);
    return $row ?: null;
}

function resolveWebinarIntakeAccess(int $webinarId): ?array
{
    startSession();
    $token = trim((string) ($_GET['token'] ?? $_SESSION[WEBINAR_INTAKE_SESSION_KEY] ?? ''));
    if ($token === '') {
        return null;
    }
    $intake = fetchWebinarIntakeByToken($token, $webinarId);
    if ($intake) {
        $_SESSION[WEBINAR_INTAKE_SESSION_KEY] = $token;
    }
    return $intake;
}

/** @return array{user_id: int, created: bool} */
function resolveOrCreateUserForWebinarIntake(array $intake): array
{
    require_once __DIR__ . '/auth.php';

    $email = strtolower(trim((string) ($intake['email'] ?? '')));
    $existing = db()->fetchOne('SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1', [$email]);

    if ($existing) {
        $userId = (int) $existing['id'];
        $phone = (string) ($intake['mobile'] ?? '');
        if ($phone !== '' && empty($existing['phone'])) {
            db()->execute('UPDATE users SET phone = ? WHERE id = ?', [$phone, $userId]);
        }
        Auth::establishUserSession($userId);
        return ['user_id' => $userId, 'created' => false];
    }

    $name = trim((string) ($intake['full_name'] ?? 'Participant'));
    $phone = (string) ($intake['mobile'] ?? '');
    $refCode = generateReferralCode($name);
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost' => HASH_COST]);

    $userId = db()->insert(
        'INSERT INTO users (full_name, email, phone, password, referral_code, verification_token) VALUES (?,?,?,?,?,?)',
        [$name, $email, $phone, $password, $refCode, generateToken()]
    );
    db()->execute('INSERT INTO wallet (user_id, balance) VALUES (?, 0)', [$userId]);
    require_once __DIR__ . '/nxl-wallet.php';
    grantNxlReward($userId, 'signup_bonus', null, 'Welcome bonus NxL tokens!');
    Auth::establishUserSession($userId);

    return ['user_id' => $userId, 'created' => true];
}

/** @return array{success: bool, message: string, registration_no?: string, redirect?: string} */
function completeFreeWebinarRegistrationFromIntake(string $token, int $webinarId): array
{
    ensureWebinarRegistrationIntakeSchema();

    $intake = fetchWebinarIntakeByToken($token, $webinarId);
    if (!$intake) {
        return ['success' => false, 'message' => 'Registration session expired. Please start again from the webinars page.'];
    }

    $webinarId = (int) ($intake['webinar_id'] ?? 0);
    $webinar = publicFetchWebinarById($webinarId);
    if (!$webinar || empty($webinar['is_free'])) {
        return ['success' => false, 'message' => 'This webinar is not available for free registration.'];
    }

    if (webinarEmailHasActiveRegistration($webinarId, (string) $intake['email'])) {
        return ['success' => false, 'message' => 'This email is already registered for this webinar.'];
    }

    $userResult = resolveOrCreateUserForWebinarIntake($intake);
    $userId = $userResult['user_id'];
    $user = db()->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
    if (!$user) {
        return ['success' => false, 'message' => 'Could not create your account. Please try again.'];
    }

    $existing = fetchActiveWebinarRegistrationByUser($userId, $webinarId);
    if ($existing) {
        db()->execute(
            "UPDATE webinar_registration_intakes SET status = 'confirmed', confirmed_at = NOW(), user_id = ? WHERE id = ?",
            [$userId, (int) $intake['id']]
        );
        return [
            'success'  => true,
            'message'  => 'You are already registered for this webinar.',
            'redirect' => absoluteUrl('registration-success.php?type=webinar&id=' . $webinarId),
        ];
    }

    $regNo = generateRegNo('CYB-W');
    $regId = db()->insert(
        "INSERT INTO webinar_registrations (user_id, webinar_id, registration_no, payment_status) VALUES (?,?,?,'free')",
        [$userId, $webinarId, $regNo]
    );
    db()->execute(
        "UPDATE webinar_registration_intakes SET status = 'confirmed', confirmed_at = NOW(), user_id = ? WHERE id = ?",
        [$userId, (int) $intake['id']]
    );

    try {
        sendNotification($userId, 'registration', 'Webinar registration confirmed', 'You are registered for: ' . ($webinar['title'] ?? 'Webinar'));
    } catch (Throwable $e) {
    }

    try {
        require_once __DIR__ . '/form-submissions.php';
        recordWebinarRegistrationForm($regId, [
            'full_name'       => $intake['full_name'] ?? $user['full_name'] ?? '',
            'email'           => $intake['email'] ?? $user['email'] ?? '',
            'phone'           => $intake['mobile'] ?? $user['phone'] ?? '',
            'organization'    => $intake['organization'] ?? '',
            'city'            => $intake['city'] ?? '',
            'title'           => $webinar['title'] ?? '',
            'registration_no' => $regNo,
            'payment_status'  => 'free',
            'webinar_id'      => $webinarId,
        ]);
    } catch (Throwable $e) {
    }

    $message = 'Registration completed successfully.';
    try {
        $mailResult = sendWebinarRegistrationConfirmationEmail(
            (string) ($intake['email'] ?? $user['email'] ?? ''),
            (string) ($intake['full_name'] ?? $user['full_name'] ?? 'Participant'),
            $webinar,
            $regNo
        );
        if (!$mailResult['ok']) {
            $message .= ' (Confirmation email could not be sent.)';
        }
    } catch (Throwable $e) {
        $message .= ' (Confirmation email could not be sent.)';
    }

    startSession();
    unset($_SESSION[WEBINAR_INTAKE_SESSION_KEY]);

    return [
        'success'         => true,
        'message'         => $message,
        'registration_no' => $regNo,
        'redirect'        => absoluteUrl('registration-success.php?type=webinar&id=' . $webinarId),
    ];
}
