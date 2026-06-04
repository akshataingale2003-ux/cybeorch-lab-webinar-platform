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
require_once __DIR__ . '/admin-catalog-limits.php';

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

/** Redirect after POST so refresh cannot resubmit the form (PRG pattern). */
function adminFlashRedirect(string $adminPath, string $type, string $message): never
{
    setFlash($type, $message);
    header('Location: ' . adminUrl($adminPath));
    exit;
}

/** One-time token to block duplicate form submissions (double-click / replay). */
function adminIssueFormNonce(string $scope): string
{
    startSession();
    $key = 'admin_form_nonce_' . preg_replace('/[^a-z0-9_-]/i', '', $scope);
    $token = bin2hex(random_bytes(16));
    $_SESSION[$key] = $token;
    return $token;
}

function adminValidateAndConsumeFormNonce(string $scope, string $submitted): bool
{
    startSession();
    $key = 'admin_form_nonce_' . preg_replace('/[^a-z0-9_-]/i', '', $scope);
    $expected = (string) ($_SESSION[$key] ?? '');
    if ($expected === '' || $submitted === '' || !hash_equals($expected, $submitted)) {
        return false;
    }
    unset($_SESSION[$key]);
    return true;
}
