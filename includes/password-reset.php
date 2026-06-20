<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/admin-users.php';

function passwordResetTokenTtlMinutes(): int
{
    return max(1, (int) (defined('PASSWORD_RESET_TOKEN_TTL_MINUTES') ? PASSWORD_RESET_TOKEN_TTL_MINUTES : 15));
}

function passwordResetMaxRequestsPerHour(): int
{
    return max(1, (int) (defined('PASSWORD_RESET_MAX_REQUESTS_PER_HOUR') ? PASSWORD_RESET_MAX_REQUESTS_PER_HOUR : 5));
}

function ensurePasswordResetSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY uq_password_reset_token_hash (token_hash),
        INDEX idx_password_reset_user (user_id),
        INDEX idx_password_reset_expires (expires_at),
        CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function generatePasswordResetToken(): string
{
    return bin2hex(random_bytes(32));
}

function hashPasswordResetToken(string $token): string
{
    return hash('sha256', $token);
}

function passwordResetLink(string $token): string
{
    return absoluteUrl('reset-password.php?token=' . rawurlencode($token));
}

/** @return array{success: bool, message: string, reset_link?: string, email_sent?: bool} */
function requestPasswordReset(string $email): array
{
    ensurePasswordResetSchema();

    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    $genericSuccess = 'If an account exists with this email, you will receive a password reset link shortly. Please check your inbox and spam folder.';

    $user = db()->fetchOne(
        'SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1',
        [$email]
    );

    if (!$user) {
        mailLog('info', 'password_reset', 'Reset requested for unknown email', ['email' => $email]);
        return ['success' => true, 'message' => $genericSuccess, 'email_sent' => false];
    }

    $userId = (int) $user['id'];
    $since  = date('Y-m-d H:i:s', time() - 3600);
    $recent = (int) (db()->fetchOne(
        'SELECT COUNT(*) AS c FROM password_reset_tokens WHERE user_id = ? AND created_at >= ?',
        [$userId, $since]
    )['c'] ?? 0);

    if ($recent >= passwordResetMaxRequestsPerHour()) {
        return [
            'success' => false,
            'message' => 'Too many reset requests. Please wait an hour and try again.',
        ];
    }

    $token     = generatePasswordResetToken();
    $tokenHash = hashPasswordResetToken($token);
    $expiresAt = date('Y-m-d H:i:s', time() + passwordResetTokenTtlMinutes() * 60);
    $ip        = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ua        = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    db()->execute(
        'UPDATE password_reset_tokens SET used_at = NOW()
         WHERE user_id = ? AND used_at IS NULL AND expires_at > NOW()',
        [$userId]
    );

    db()->insert(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?)',
        [$userId, $tokenHash, $expiresAt, $ip !== '' ? $ip : null, $ua !== '' ? $ua : null]
    );

    $resetLink = passwordResetLink($token);
    $mail      = sendPasswordResetEmail(
        (string) $user['email'],
        (string) ($user['full_name'] ?: 'User'),
        $resetLink,
        passwordResetTokenTtlMinutes()
    );

    if (!$mail['ok']) {
        mailLog('error', 'password_reset', (string) ($mail['error'] ?? 'SMTP error'), ['user_id' => $userId]);
        if (function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev()) {
            return [
                'success'     => true,
                'message'     => $genericSuccess . ' (Local dev: email not sent — use link below.)',
                'reset_link'  => $resetLink,
                'email_sent'  => false,
            ];
        }
        return [
            'success' => false,
            'message' => 'Could not send reset email. Please try again later or contact support.',
        ];
    }

    mailLog('info', 'password_reset', 'Reset email sent', ['user_id' => $userId, 'email' => $email]);

    $result = ['success' => true, 'message' => $genericSuccess, 'email_sent' => true];
    if (function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev()) {
        $result['reset_link'] = $resetLink;
    }

    return $result;
}

/**
 * @return array<string, mixed>|null
 */
function findValidPasswordResetToken(string $token): ?array
{
    ensurePasswordResetSchema();

    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $row = db()->fetchOne(
        'SELECT prt.id AS token_id, prt.user_id, prt.expires_at, prt.used_at,
                u.email, u.full_name
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = ?
         LIMIT 1',
        [hashPasswordResetToken($token)]
    );

    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }

    if (strtotime((string) $row['expires_at']) <= time()) {
        return null;
    }

    $row['token'] = $token;

    return $row;
}

/** @return array{success: bool, message: string} */
function completePasswordReset(string $token, string $newPassword, string $confirmPassword): array
{
    $record = findValidPasswordResetToken($token);
    if (!$record) {
        return [
            'success' => false,
            'message' => 'This password reset link is invalid, expired, or has already been used. Please request a new one.',
        ];
    }

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    $userId  = (int) $record['user_id'];
    $tokenId = (int) $record['token_id'];
    $hashed  = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => (int) (defined('HASH_COST') ? HASH_COST : 12)]);

    ensureAdminUsersSchema();

    if (function_exists('adminTableHasColumn') && adminTableHasColumn('users', 'password_plain')) {
        db()->execute(
            'UPDATE users SET password = ?, password_plain = ?, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ?',
            [$hashed, $newPassword, $userId]
        );
    } else {
        db()->execute(
            'UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL, updated_at = NOW() WHERE id = ?',
            [$hashed, $userId]
        );
    }

    db()->execute(
        'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?',
        [$tokenId]
    );

    db()->execute(
        'UPDATE password_reset_tokens SET used_at = NOW()
         WHERE user_id = ? AND used_at IS NULL',
        [$userId]
    );

    mailLog('info', 'password_reset', 'Password updated via reset token', ['user_id' => $userId]);

    return [
        'success' => true,
        'message' => 'Password changed successfully. Please login with your new password.',
    ];
}
