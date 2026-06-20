<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/setup_admin.php';

echo "=== SMTP Diagnosis ===\n\n";

echo "PHPMailer autoload: " . (mailerAutoload() ? 'OK' : 'MISSING') . "\n";
echo "smtpIsConfigured: " . (smtpIsConfigured() ? 'yes' : 'no') . "\n";
echo "SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'undefined') . "\n";
echo "SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'undefined') . "\n";
echo "SMTP_SECURE: " . (defined('SMTP_SECURE') ? SMTP_SECURE : 'undefined') . "\n";
echo "SMTP_USER: " . (defined('SMTP_USER') ? SMTP_USER : 'undefined') . "\n";
echo "SMTP_PASS length: " . (defined('SMTP_PASS') ? strlen((string) SMTP_PASS) : 0) . "\n";
echo "SMTP_FROM_EMAIL: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'undefined') . "\n\n";

ensureAdminRegisteredEmail();
$admin = db()->fetchOne('SELECT id, username, email FROM admin LIMIT 1');
echo "Admin DB: " . json_encode($admin) . "\n\n";

$to = 'akshataingale2003@gmail.com';
echo "Sending test email to {$to}...\n";
$result = sendSmtpTestEmail($to, 'CYBEORCH — Admin SMTP diagnostic test');
echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
