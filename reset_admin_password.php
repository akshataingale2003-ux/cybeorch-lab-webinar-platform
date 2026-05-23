<?php
/**
 * One-time fix: sets a working admin password hash.
 * Open in browser once, then delete this file.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/setup_admin.php';

header('Content-Type: text/plain; charset=utf-8');

$setup = ensureDefaultAdminAccount();
if (!($setup['ok'] ?? false)) {
    echo "FAILED: " . ($setup['message'] ?? 'Unknown error') . "\n";
    echo "Import database: open phpMyAdmin -> Import -> cybeorch/database.sql\n";
    exit(1);
}

$action = ($setup['fixed'] ?? false) ? 'fixed' : 'already_ok';
echo "Admin account {$action}.\n\n";
echo "=== ADMIN LOGIN ===\n";
echo "URL:      " . SITE_URL . "/admin/login.php\n";
echo "Username: cybeorch_admin   (or email below)\n";
echo "Email:    admin@cybeorch.com\n";
echo "Password: Admin@123\n\n";
echo "=== USER REGISTRATION & TRAINEE LOGIN ===\n";
echo "URL:      " . SITE_URL . "/login.php\n";
echo "Use the email + password from signup.php (no default user registration & trainee account).\n\n";
echo "Delete this file after login works: reset_admin_password.php\n";
