<?php
declare(strict_types=1);

/**
 * Standard bootstrap for all admin pages.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/contact-messages.php';
require_once __DIR__ . '/admin-schema.php';
require_once __DIR__ . '/site-database.php';
require_once __DIR__ . '/admin-layout.php';
require_once __DIR__ . '/admin-actions.php';

startSession();

if (!isset($_SESSION['admin_name']) && isset($_SESSION['admin_id'])) {
    $_SESSION['admin_name'] = 'Admin';
}

/** Ensure MySQL schemas exist and ping connection (all admin pages). */
function adminBootstrapDatabase(): bool
{
    return (bool) dbTry(static function () {
        ensureSiteDatabaseSchemas();
        ensureAdminActionsSchema();
        db()->fetchOne('SELECT 1 AS ok');
        return true;
    }, false);
}

/**
 * Safe database access for admin pages (shows banner instead of a blank error page).
 *
 * @template T
 * @param callable(): T $fn
 */
function adminDb(callable $fn, mixed $default = null): mixed
{
    return dbTry($fn, $default);
}

adminBootstrapDatabase();

function adminUrl(string $path = 'dashboard.php'): string
{
    $path = ltrim($path, '/');
    if (!str_starts_with($path, 'admin/')) {
        $path = 'admin/' . $path;
    }
    return absoluteUrl($path);
}

function adminDbErrorBanner(): string
{
    if (!defined('DB_ERROR_MESSAGE')) {
        return '';
    }
    return '<div class="alert alert-error" style="margin:1rem 1.5rem 0">'
        . '<i class="fas fa-database me-2"></i>'
        . htmlspecialchars(DB_ERROR_MESSAGE)
        . '</div>';
}
