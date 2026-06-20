<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';
require_once __DIR__ . '/../includes/admin-actions.php';

ensureInvoiceAmcSchema();

/**
 * @param array<string,mixed> $stats
 */
function statsClients(array $stats): int
{
    return (int) ($stats['total_clients'] ?? -1);
}

function currentDashboardClients(): int
{
    return statsClients(invoiceAmcDashboardStats());
}

/**
 * @return int created client id
 */
function createDashboardTestClient(string $label): int
{
    return invoiceAmcResolveClient([
        'client_mode' => 'new',
        'name' => 'Dashboard Seq ' . $label,
        'email' => 'dashboard-seq-' . $label . '-' . uniqid() . '@example.com',
        'phone' => '',
        'company' => 'Seq Test Co',
        'address' => '',
        'gst_number' => '',
    ]);
}

db()->beginTransaction();
try {
    $ids = [];

    $start = currentDashboardClients();
    echo "START={$start}\n";

    $ids[] = createDashboardTestClient('A');
    $afterAdd1 = currentDashboardClients();
    echo "AFTER_ADD_1={$afterAdd1}\n";

    adminPerformRecordAction('delete', 'client', (int) $ids[0]);
    $afterDelete1 = currentDashboardClients();
    echo "AFTER_DELETE_1={$afterDelete1}\n";

    $ids[] = createDashboardTestClient('B');
    $ids[] = createDashboardTestClient('C');
    $ids[] = createDashboardTestClient('D');
    $afterAdd3 = currentDashboardClients();
    echo "AFTER_ADD_3={$afterAdd3}\n";

    adminPerformRecordAction('delete', 'client', (int) $ids[1]);
    adminPerformRecordAction('delete', 'client', (int) $ids[2]);
    $afterDelete2 = currentDashboardClients();
    echo "AFTER_DELETE_2={$afterDelete2}\n";

    $ok = true;
    $ok = $ok && ($start === 0);
    $ok = $ok && ($afterAdd1 === 1);
    $ok = $ok && ($afterDelete1 === 0);
    $ok = $ok && ($afterAdd3 === 3);
    $ok = $ok && ($afterDelete2 === 1);

    echo $ok ? "PASS\n" : "FAIL\n";
    db()->rollBack();
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    echo 'ERROR=' . $e->getMessage() . "\n";
    exit(1);
}
