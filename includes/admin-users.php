<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin-actions.php';

/** Ensure users table has block / soft-delete columns. */
function ensureAdminUsersSchema(): void
{
    ensureAdminActionsSchema();
    static $plainCol = false;
    if ($plainCol) {
        return;
    }
    $plainCol = true;
    if (!adminTableHasColumn('users', 'password_plain')) {
        try {
            db()->execute(
                'ALTER TABLE users ADD COLUMN password_plain VARCHAR(255) NULL DEFAULT NULL AFTER password'
            );
        } catch (Throwable $e) {
            // column may already exist
        }
    }
}

/** Plaintext password for admin display (stored when user registers or password is reset). */
function adminUserDisplayPassword(array $row): string
{
    return trim((string) ($row['password_plain'] ?? ''));
}

function adminStoreUserPasswordPlain(int $userId, string $plainPassword): void
{
    ensureAdminUsersSchema();
    if ($userId < 1 || !adminTableHasColumn('users', 'password_plain')) {
        return;
    }
    db()->execute('UPDATE users SET password_plain = ? WHERE id = ?', [$plainPassword, $userId]);
}

/**
 * @return 'active'|'blocked'|'deleted'
 */
function adminUserRecordStatus(array $row): string
{
    if (!empty($row['deleted_at']) && $row['deleted_at'] !== '0000-00-00 00:00:00') {
        return 'deleted';
    }
    if (adminRecordIsBlocked($row)) {
        return 'blocked';
    }
    return 'active';
}

function adminRenderUserStatusBadge(array $row): void
{
    $status = adminUserRecordStatus($row);
    if ($status === 'deleted') {
        echo '<span class="badge-status badge-deleted">Deleted</span>';
        return;
    }
    if ($status === 'blocked') {
        echo '<span class="badge-status badge-blocked">Blocked</span>';
        return;
    }
    echo '<span class="badge-status badge-active">Active</span>';
}

/**
 * @param array{status?: string, q?: string, limit?: int} $filters
 * @return array<int, array<string, mixed>>
 */
function adminFetchUsers(array $filters = []): array
{
    ensureAdminUsersSchema();

    $status = $filters['status'] ?? 'active';
    $q = trim((string) ($filters['q'] ?? ''));
    $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));

    $where = [];
    $params = [];

    if ($status === 'deleted') {
        $where[] = 'u.deleted_at IS NOT NULL';
    } elseif ($status === 'blocked') {
        $where[] = adminSqlActive('u');
        $where[] = 'u.is_blocked = 1';
    } elseif ($status === 'all') {
        $where[] = adminSqlActive('u');
    } else {
        $where[] = adminSqlActive('u');
        $where[] = '(u.is_blocked = 0 OR u.is_blocked IS NULL)';
    }

    if ($q !== '') {
        $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.referral_code LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $sql = 'SELECT u.*, COALESCE(w.balance, 0) AS wallet_balance
            FROM users u
            LEFT JOIN wallet w ON w.user_id = u.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY u.created_at DESC
            LIMIT ' . $limit;

    return db()->fetchAll($sql, $params);
}

function adminFetchUserById(int $id, bool $includeDeleted = true): ?array
{
    ensureAdminUsersSchema();
    if ($id < 1) {
        return null;
    }
    $sql = 'SELECT u.*, COALESCE(w.balance, 0) AS wallet_balance
            FROM users u
            LEFT JOIN wallet w ON w.user_id = u.id
            WHERE u.id = ?';
    if (!$includeDeleted) {
        $sql .= ' AND ' . adminSqlActive('u');
    }
    $row = db()->fetchOne($sql, [$id]);
    return $row ?: null;
}

/**
 * @return array{success: bool, message: string}
 */
function adminResetUserPassword(int $userId, string $newPassword, string $confirmPassword): array
{
    ensureAdminUsersSchema();

    if ($userId < 1) {
        return ['success' => false, 'message' => 'Invalid user.'];
    }

    $user = adminFetchUserById($userId, true);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }

    if (adminUserRecordStatus($user) === 'deleted') {
        return ['success' => false, 'message' => 'Cannot reset password for a deleted user. Restore the account first.'];
    }

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        return ['success' => false, 'message' => 'Password must include at least one uppercase letter and one number.'];
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => defined('HASH_COST') ? HASH_COST : 12]);
    if (adminTableHasColumn('users', 'password_plain')) {
        db()->execute('UPDATE users SET password = ?, password_plain = ? WHERE id = ?', [$hash, $newPassword, $userId]);
    } else {
        db()->execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $userId]);
    }

    return [
        'success' => true,
        'message' => 'Password reset successfully. The user must use the new password on next login.',
    ];
}

function adminCountUsersByStatus(): array
{
    ensureAdminUsersSchema();
    return [
        'active'  => (int) dbTry(fn () => db()->fetchOne(
            'SELECT COUNT(*) AS c FROM users u WHERE ' . adminSqlActive('u') . ' AND (u.is_blocked = 0 OR u.is_blocked IS NULL)'
        )['c'] ?? 0, 0),
        'blocked' => (int) dbTry(fn () => adminBlockedUsersCount(), 0),
        'deleted' => (int) dbTry(fn () => db()->fetchOne(
            'SELECT COUNT(*) AS c FROM users u WHERE u.deleted_at IS NOT NULL'
        )['c'] ?? 0, 0),
        'all'     => (int) dbTry(fn () => db()->fetchOne(
            'SELECT COUNT(*) AS c FROM users u WHERE ' . adminSqlActive('u')
        )['c'] ?? 0, 0),
    ];
}
