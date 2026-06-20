<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/setup_admin.php';

function adminPasswordResetTokenTtlMinutes(): int
{
    return max(15, min(30, (int) (defined('PASSWORD_RESET_TOKEN_TTL_MINUTES') ? PASSWORD_RESET_TOKEN_TTL_MINUTES : 15)));
}

function adminPasswordResetMaxRequestsPerHour(): int
{
    return max(3, (int) (defined('PASSWORD_RESET_MAX_REQUESTS_PER_HOUR') ? PASSWORD_RESET_MAX_REQUESTS_PER_HOUR : 8));
}

function ensureAdminPasswordResetSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    ensureAdminRegisteredEmail();

    db()->execute("CREATE TABLE IF NOT EXISTS admin_password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY uq_admin_password_reset_token_hash (token_hash),
        INDEX idx_admin_password_reset_admin (admin_id),
        INDEX idx_admin_password_reset_expires (expires_at),
        CONSTRAINT fk_admin_password_reset_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $column = db()->fetchOne("SHOW COLUMNS FROM admin_password_reset_tokens LIKE 'token_plain'");
        if (!$column) {
            db()->execute('ALTER TABLE admin_password_reset_tokens ADD COLUMN token_plain VARCHAR(64) NULL AFTER token_hash');
        }
    } catch (Throwable $e) {
        // Migration offline or already applied
    }

    try {
        db()->execute('DELETE FROM admin_password_reset_tokens WHERE expires_at < NOW()');
    } catch (Throwable $e) {
        // ignore
    }
}

function adminPasswordResetLink(string $token): string
{
    return rtrim(cybeorchPublicSiteUrl(), '/') . '/' . normalizeAdminRoute('reset-password.php?token=' . rawurlencode($token));
}

/**
 * Send a password reset email to the registered primary admin account.
 *
 * @return array{success: bool, message: string, email_sent?: bool}
 */
function requestPrimaryAdminPasswordReset(): array
{
    ensureAdminRegisteredEmail();

    return requestAdminPasswordReset(cybeorchAdminRegisteredEmail());
}

/** @return string|null Error message or null if valid. */
function validateAdminPasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one number.';
    }

    return null;
}

/**
 * @return array{success: bool, message: string, email_sent?: bool}
 */
function requestAdminPasswordReset(string $email): array
{
    ensureAdminPasswordResetSchema();

    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    $canonicalEmail = strtolower(cybeorchAdminRegisteredEmail());
    $admin = db()->fetchOne(
        'SELECT id, email, full_name FROM admin WHERE LOWER(email) = ? LIMIT 1',
        [$email]
    );

    if (!$admin && $email === $canonicalEmail) {
        $admin = db()->fetchOne(
            "SELECT id, email, full_name FROM admin WHERE username = 'cybeorch_admin' OR role = 'super_admin' ORDER BY id ASC LIMIT 1"
        );
        if ($admin) {
            db()->execute('UPDATE admin SET email = ? WHERE id = ?', [cybeorchAdminRegisteredEmail(), (int) $admin['id']]);
            $admin['email'] = cybeorchAdminRegisteredEmail();
        }
    }

    if (!$admin) {
        mailLog('info', 'admin_password_reset', 'Reset requested for unknown admin email', ['email' => $email]);

        return [
            'success' => false,
            'message' => 'No admin account is registered with this email address. Password reset is sent only to ' . cybeorchAdminRegisteredEmail() . '.',
        ];
    }

    $deliveryEmail = strtolower(trim((string) $admin['email']));
    if ($deliveryEmail !== $canonicalEmail) {
        db()->execute('UPDATE admin SET email = ? WHERE id = ?', [cybeorchAdminRegisteredEmail(), (int) $admin['id']]);
        $deliveryEmail = $canonicalEmail;
        $admin['email'] = cybeorchAdminRegisteredEmail();
    }

    $adminId = (int) $admin['id'];
    $since = date('Y-m-d H:i:s', time() - 3600);
    $recent = (int) (db()->fetchOne(
        'SELECT COUNT(*) AS c FROM admin_password_reset_tokens WHERE admin_id = ? AND created_at >= ?',
        [$adminId, $since]
    )['c'] ?? 0);

    if ($recent >= adminPasswordResetMaxRequestsPerHour()) {
        $existing = db()->fetchOne(
            'SELECT token_plain, expires_at
             FROM admin_password_reset_tokens
             WHERE admin_id = ? AND used_at IS NULL AND expires_at > NOW() AND token_plain IS NOT NULL
             ORDER BY id DESC LIMIT 1',
            [$adminId]
        );

        if ($existing && trim((string) ($existing['token_plain'] ?? '')) !== '') {
            $token = trim((string) $existing['token_plain']);
            $resetLink = adminPasswordResetLink($token);
            $mail = sendAdminPasswordResetEmail(
                $deliveryEmail,
                (string) ($admin['full_name'] ?: 'Admin'),
                $resetLink,
                adminPasswordResetTokenTtlMinutes()
            );

            if ($mail['ok']) {
                mailLog('info', 'admin_password_reset', 'Resent existing admin reset email after rate limit', [
                    'admin_id' => $adminId,
                    'email'    => $deliveryEmail,
                ]);

                return [
                    'success'    => true,
                    'message'    => 'A reset link was already generated recently and has been sent again to ' . $deliveryEmail . '. Check your inbox and spam/junk folder.',
                    'email_sent' => true,
                ];
            }

            return [
                'success'    => false,
                'message'    => 'Could not resend reset email: ' . (string) ($mail['error'] ?? 'SMTP error'),
                'email_sent' => false,
            ];
        }

        $retryAt = db()->fetchOne(
            'SELECT created_at FROM admin_password_reset_tokens WHERE admin_id = ? ORDER BY id DESC LIMIT 1',
            [$adminId]
        );
        $waitMinutes = 60;
        if ($retryAt && !empty($retryAt['created_at'])) {
            $elapsed = time() - strtotime((string) $retryAt['created_at']);
            $waitMinutes = max(1, (int) ceil((3600 - $elapsed) / 60));
        }

        return [
            'success' => false,
            'message' => 'Too many reset requests. Please wait about ' . $waitMinutes . ' minute(s), then try again. Also check spam/junk for an earlier email sent to ' . $deliveryEmail . '.',
        ];
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + adminPasswordResetTokenTtlMinutes() * 60);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    db()->execute(
        'UPDATE admin_password_reset_tokens SET used_at = NOW()
         WHERE admin_id = ? AND used_at IS NULL AND expires_at > NOW()',
        [$adminId]
    );

    db()->insert(
        'INSERT INTO admin_password_reset_tokens (admin_id, token_hash, token_plain, expires_at, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$adminId, $tokenHash, $token, $expiresAt, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]
    );

    $resetLink = adminPasswordResetLink($token);
    $mail = sendAdminPasswordResetEmail(
        $deliveryEmail,
        (string) ($admin['full_name'] ?: 'Admin'),
        $resetLink,
        adminPasswordResetTokenTtlMinutes()
    );

    if (!$mail['ok']) {
        $smtpError = (string) ($mail['error'] ?? 'Unknown SMTP error');
        mailLog('error', 'admin_password_reset', $smtpError, [
            'admin_id' => $adminId,
            'email'    => $email,
            'smtp'     => function_exists('smtpConfigurationSummary') ? smtpConfigurationSummary() : [],
        ]);

        return [
            'success'    => false,
            'message'    => 'Could not send reset email: ' . $smtpError . ' Check SMTP settings in .env or setup-smtp.php, then try again.',
            'email_sent' => false,
        ];
    }

    mailLog('info', 'admin_password_reset', 'Admin reset email sent', [
        'admin_id' => $adminId,
        'email'    => $email,
        'to'       => (string) $admin['email'],
    ]);

    return [
        'success'    => true,
        'message'    => 'Password reset link has been sent to ' . (string) $admin['email'] . '. Check your inbox and spam/junk folder.',
        'email_sent' => true,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function findValidAdminPasswordResetToken(string $token): ?array
{
    ensureAdminPasswordResetSchema();

    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $row = db()->fetchOne(
        'SELECT prt.id AS token_id, prt.admin_id, prt.expires_at, prt.used_at,
                a.email, a.full_name, a.username
         FROM admin_password_reset_tokens prt
         INNER JOIN admin a ON a.id = prt.admin_id
         WHERE prt.token_hash = ?
         LIMIT 1',
        [hash('sha256', $token)]
    );

    if (!$row || !empty($row['used_at'])) {
        return null;
    }

    if (strtotime((string) $row['expires_at']) <= time()) {
        return null;
    }

    $row['token'] = $token;

    return $row;
}

/** @return array{success: bool, message: string} */
function completeAdminPasswordReset(string $token, string $newPassword, string $confirmPassword): array
{
    $record = findValidAdminPasswordResetToken($token);
    if (!$record) {
        return [
            'success' => false,
            'message' => 'This password reset link is invalid, expired, or has already been used. Please request a new one.',
        ];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Password and Confirm Password must match.'];
    }

    $strengthError = validateAdminPasswordStrength($newPassword);
    if ($strengthError !== null) {
        return ['success' => false, 'message' => $strengthError];
    }

    $adminId = (int) $record['admin_id'];
    $tokenId = (int) $record['token_id'];
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => (int) (defined('HASH_COST') ? HASH_COST : 12)]);

    db()->execute('UPDATE admin SET password = ? WHERE id = ?', [$hashed, $adminId]);

    db()->execute(
        'UPDATE admin_password_reset_tokens SET used_at = NOW(), token_plain = NULL WHERE id = ?',
        [$tokenId]
    );
    db()->execute(
        'UPDATE admin_password_reset_tokens SET used_at = NOW(), token_plain = NULL WHERE admin_id = ? AND used_at IS NULL',
        [$adminId]
    );

    mailLog('info', 'admin_password_reset', 'Admin password updated via reset token', ['admin_id' => $adminId]);

    return [
        'success' => true,
        'message' => 'Your admin password has been reset successfully. You can now sign in.',
    ];
}
