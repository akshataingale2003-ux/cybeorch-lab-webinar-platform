<?php
declare(strict_types=1);

/** Registered admin email for login and password reset delivery. */
function cybeorchAdminRegisteredEmail(): string
{
    return 'akshataingale2003@gmail.com';
}

/**
 * Ensure the primary admin account uses the registered email address.
 */
function ensureAdminRegisteredEmail(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $canonical = strtolower(cybeorchAdminRegisteredEmail());

    try {
        $primary = db()->fetchOne(
            "SELECT id, email FROM admin WHERE username = 'cybeorch_admin' OR role = 'super_admin' ORDER BY id ASC LIMIT 1"
        );
        if (!$primary) {
            return;
        }

        $primaryId = (int) $primary['id'];
        if (strtolower(trim((string) ($primary['email'] ?? ''))) === $canonical) {
            return;
        }

        $conflict = db()->fetchOne(
            'SELECT id FROM admin WHERE LOWER(email) = ? AND id != ? LIMIT 1',
            [$canonical, $primaryId]
        );
        if ($conflict) {
            return;
        }

        db()->execute('UPDATE admin SET email = ? WHERE id = ?', [cybeorchAdminRegisteredEmail(), $primaryId]);
    } catch (Throwable $e) {
        // DB offline
    }
}

/**
 * Ensures default admin exists with a valid password hash (Admin@123).
 * Fixes database.sql placeholder: XYZ_REPLACE_WITH_HASHED_PASSWORD
 */
function ensureDefaultAdminAccount(): array
{
    ensureAdminRegisteredEmail();

    $defaultUser = 'cybeorch_admin';
    $defaultEmail = cybeorchAdminRegisteredEmail();
    $defaultPass = 'Admin@123';

    try {
        $admin = db()->fetchOne(
            'SELECT id, password, email FROM admin WHERE username = ? LIMIT 1',
            [$defaultUser]
        );
        if (!$admin) {
            $admin = db()->fetchOne(
                'SELECT id, password, email FROM admin WHERE LOWER(email) = ? LIMIT 1',
                [strtolower($defaultEmail)]
            );
        }

        $broken = !$admin
            || str_contains((string) $admin['password'], 'XYZ_REPLACE')
            || !str_starts_with((string) $admin['password'], '$2y$');

        if (!$broken) {
            return ['fixed' => false, 'ok' => true, 'message' => ''];
        }

        $hash = password_hash($defaultPass, PASSWORD_BCRYPT, ['cost' => HASH_COST]);

        if ($admin) {
            db()->execute(
                'UPDATE admin SET password = ?, email = ? WHERE id = ?',
                [$hash, $defaultEmail, $admin['id']]
            );
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
