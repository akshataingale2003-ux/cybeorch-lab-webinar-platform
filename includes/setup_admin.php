<?php
declare(strict_types=1);

/**
 * Ensures default admin exists with a valid password hash (Admin@123).
 * Fixes database.sql placeholder: XYZ_REPLACE_WITH_HASHED_PASSWORD
 */
function ensureDefaultAdminAccount(): array
{
    $defaultUser = 'cybeorch_admin';
    $defaultEmail = 'admin@cybeorch.com';
    $defaultPass = 'Admin@123';

    try {
        $admin = db()->fetchOne(
            'SELECT id, password FROM admin WHERE username = ? OR email = ? LIMIT 1',
            [$defaultUser, $defaultEmail]
        );

        $broken = !$admin
            || str_contains((string) $admin['password'], 'XYZ_REPLACE')
            || !str_starts_with((string) $admin['password'], '$2y$');

        if (!$broken) {
            return ['fixed' => false, 'ok' => true, 'message' => ''];
        }

        $hash = password_hash($defaultPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

        if ($admin) {
            db()->execute('UPDATE admin SET password = ? WHERE id = ?', [$hash, $admin['id']]);
        } else {
            db()->insert(
                'INSERT INTO admin (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)',
                [$defaultUser, $defaultEmail, $hash, 'CYBEORCH Admin', 'super_admin']
            );
        }

        return [
            'fixed' => true,
            'ok' => true,
            'message' => 'Default admin password was reset. Sign in with Admin@123.',
        ];
    } catch (Throwable $e) {
        return [
            'fixed' => false,
            'ok' => false,
            'message' => 'Database error: import cybeorch/database.sql in phpMyAdmin, then refresh this page.',
        ];
    }
}
