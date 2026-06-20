<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';

ensureInvoiceAmcSchema();

try {
    db()->beginTransaction();

    $before = invoiceAmcDashboardStats();

    $clientCode = 'TST-CL-' . date('His') . '-' . substr(uniqid('', true), -4);
    $clientId = (int) db()->insert(
        'INSERT INTO clients (client_code, name, email, phone, company, address, gst_number, notes)
         VALUES (?,?,?,?,?,?,?,?)',
        [
            $clientCode,
            'Client Count Test',
            'client-count-' . uniqid() . '@example.com',
            '9999999999',
            'Test Company',
            'Test Address',
            'GST-TEST-001',
            'Initial create for count verification',
        ]
    );

    $afterCreate = invoiceAmcDashboardStats();

    $update = invoiceAmcUpdateClient($clientId, [
        'name' => 'Client Count Test Updated',
        'email' => 'client-count-updated-' . uniqid() . '@example.com',
        'phone' => '8888888888',
        'company' => 'Test Company Updated',
        'address' => 'Updated Address',
        'gst_number' => 'GST-TEST-002',
        'notes' => 'Updated during count verification',
    ]);
    if (empty($update['ok'])) {
        throw new RuntimeException('Update failed: ' . (string) ($update['error'] ?? 'Unknown error'));
    }

    $afterEdit = invoiceAmcDashboardStats();

    db()->execute('UPDATE clients SET deleted_at = NOW() WHERE id = ?', [$clientId]);
    $afterDelete = invoiceAmcDashboardStats();

    echo 'Before total_clients: ' . (int) $before['total_clients'] . PHP_EOL;
    echo 'After create total_clients: ' . (int) $afterCreate['total_clients'] . PHP_EOL;
    echo 'After edit total_clients: ' . (int) $afterEdit['total_clients'] . PHP_EOL;
    echo 'After delete total_clients: ' . (int) $afterDelete['total_clients'] . PHP_EOL;
    echo 'Delta create: ' . ((int) $afterCreate['total_clients'] - (int) $before['total_clients']) . PHP_EOL;
    echo 'Delta edit: ' . ((int) $afterEdit['total_clients'] - (int) $afterCreate['total_clients']) . PHP_EOL;
    echo 'Delta delete: ' . ((int) $afterDelete['total_clients'] - (int) $afterEdit['total_clients']) . PHP_EOL;

    $ok = ((int) $afterCreate['total_clients'] === (int) $before['total_clients'] + 1)
        && ((int) $afterEdit['total_clients'] === (int) $afterCreate['total_clients'])
        && ((int) $afterDelete['total_clients'] === (int) $before['total_clients']);

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
