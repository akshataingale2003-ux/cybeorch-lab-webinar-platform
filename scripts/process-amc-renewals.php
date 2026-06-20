<?php
/**
 * AMC renewal reminder cron job.
 * Schedule daily: php scripts/process-amc-renewals.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/invoice-amc.php';

ensureInvoiceAmcSchema();
$result = invoiceAmcProcessRenewalReminders();

echo date('Y-m-d H:i:s') . ' — Reminders sent: ' . (int) $result['sent'] . PHP_EOL;
if (!empty($result['errors'])) {
    foreach ($result['errors'] as $err) {
        echo '  ERROR: ' . $err . PHP_EOL;
    }
}
