<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';

ensureInvoiceAmcSchema();

try {
    db()->beginTransaction();

    $before = invoiceAmcDashboardStats();

    $clientId = (int) db()->insert(
        'INSERT INTO clients (client_code, name, email, phone, company) VALUES (?,?,?,?,?)',
        [
            'TST-CL-' . date('His'),
            'Stats Test Client',
            'stats-test-' . uniqid() . '@example.com',
            '0000000000',
            'Stats Test Co',
        ]
    );

    $invoiceNo = 'TST/INV/' . date('Y') . '/' . substr((string) microtime(true), -6);
    $invoiceId = (int) db()->insert(
        'INSERT INTO invoices
        (invoice_no, client_id, invoice_date, project_type, project_name, currency, subtotal, discount, tax_rate, tax_amount, total_amount, status, payment_terms, validity_days, payment_methods)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $invoiceNo,
            $clientId,
            date('Y-m-d'),
            'Custom Projects',
            'Stats Verification Project',
            'USD',
            100,
            0,
            0,
            0,
            100,
            'pending',
            'Test Terms',
            30,
            'Test Method',
        ]
    );

    $afterCreate = invoiceAmcDashboardStats();

    db()->execute('UPDATE invoices SET deleted_at = NOW() WHERE id = ?', [$invoiceId]);

    $afterDelete = invoiceAmcDashboardStats();

    echo "Before total_invoices: " . (int) $before['total_invoices'] . PHP_EOL;
    echo "After create total_invoices: " . (int) $afterCreate['total_invoices'] . PHP_EOL;
    echo "After delete total_invoices: " . (int) $afterDelete['total_invoices'] . PHP_EOL;
    echo "Delta create: " . ((int) $afterCreate['total_invoices'] - (int) $before['total_invoices']) . PHP_EOL;
    echo "Delta delete: " . ((int) $afterDelete['total_invoices'] - (int) $afterCreate['total_invoices']) . PHP_EOL;

    $ok = ((int) $afterCreate['total_invoices'] === (int) $before['total_invoices'] + 1)
        && ((int) $afterDelete['total_invoices'] === (int) $before['total_invoices']);
    echo $ok ? "PASS\n" : "FAIL\n";

    db()->rollBack();
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
