<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';
require_once __DIR__ . '/../includes/admin-actions.php';

ensureInvoiceAmcSchema();

function dashboardCount(): int
{
    $stats = invoiceAmcDashboardStats();
    return (int) ($stats['total_clients'] ?? -1);
}

function rowById(int $id): ?array
{
    $row = db()->fetchOne(
        'SELECT id, client_code, name, email, status, is_blocked, deleted_at, created_at, updated_at FROM clients WHERE id = ?',
        [$id]
    );
    return is_array($row) ? $row : null;
}

function countRowsByEmail(string $email): int
{
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM clients WHERE email = ?', [$email])['c'] ?? 0);
}

function totalClientRows(): int
{
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM clients')['c'] ?? 0);
}

$suffix = uniqid('proof_', true);
$emailA = 'proof-a-' . $suffix . '@example.com';
$emailB = 'proof-b-' . $suffix . '@example.com';
$emailAUpdated = 'proof-a-edited-' . $suffix . '@example.com';

echo "STEP 1: CURRENT TOTAL CLIENTS COUNT\n";
$beforeCount = dashboardCount();
$beforeRows = totalClientRows();
echo 'dashboard_total_clients=' . $beforeCount . "\n";
echo 'clients_table_total_rows=' . $beforeRows . "\n\n";

echo "STEP 2: ADD CLIENT #1 (ADMIN FLOW)\n";
$clientAId = invoiceAmcResolveClient([
    'client_mode' => 'new',
    'name' => 'Proof Client A',
    'email' => $emailA,
    'phone' => '9000000001',
    'company' => 'Proof Company A',
    'address' => 'Address A',
    'gst_number' => 'GST-PROOF-A',
]);
echo 'client_a_id=' . $clientAId . "\n";
echo 'client_a_row=' . json_encode(rowById($clientAId), JSON_UNESCAPED_UNICODE) . "\n";
$afterAdd1 = dashboardCount();
echo 'dashboard_after_add_1=' . $afterAdd1 . "\n\n";

echo "STEP 3: ADD CLIENT #2 (ADMIN FLOW)\n";
$clientBId = invoiceAmcResolveClient([
    'client_mode' => 'new',
    'name' => 'Proof Client B',
    'email' => $emailB,
    'phone' => '9000000002',
    'company' => 'Proof Company B',
    'address' => 'Address B',
    'gst_number' => 'GST-PROOF-B',
]);
echo 'client_b_id=' . $clientBId . "\n";
echo 'client_b_row=' . json_encode(rowById($clientBId), JSON_UNESCAPED_UNICODE) . "\n";
$afterAdd2 = dashboardCount();
echo 'dashboard_after_add_2=' . $afterAdd2 . "\n\n";

echo "STEP 4: EDIT CLIENT #1 (UPDATE SAME RECORD, NO DUPLICATE)\n";
$rowsBeforeEdit = totalClientRows();
$editResult = invoiceAmcUpdateClient($clientAId, [
    'name' => 'Proof Client A Edited',
    'email' => $emailAUpdated,
    'phone' => '9111111111',
    'company' => 'Proof Company A Edited',
    'address' => 'Address A Edited',
    'gst_number' => 'GST-PROOF-A-EDIT',
    'notes' => 'Edited in dynamic CRUD proof test',
]);
$rowsAfterEdit = totalClientRows();
echo 'edit_result=' . json_encode($editResult, JSON_UNESCAPED_UNICODE) . "\n";
echo 'client_a_row_after_edit=' . json_encode(rowById($clientAId), JSON_UNESCAPED_UNICODE) . "\n";
echo 'rows_before_edit=' . $rowsBeforeEdit . "\n";
echo 'rows_after_edit=' . $rowsAfterEdit . "\n";
echo 'rows_with_old_email=' . countRowsByEmail($emailA) . "\n";
echo 'rows_with_new_email=' . countRowsByEmail($emailAUpdated) . "\n";
$afterEdit = dashboardCount();
echo 'dashboard_after_edit=' . $afterEdit . "\n\n";

echo "STEP 5: DELETE CLIENT #1 (SOFT DELETE)\n";
$deleteA = adminPerformRecordAction('delete', 'client', $clientAId);
echo 'delete_client_a_result=' . json_encode($deleteA, JSON_UNESCAPED_UNICODE) . "\n";
echo 'client_a_row_after_delete=' . json_encode(rowById($clientAId), JSON_UNESCAPED_UNICODE) . "\n";
$afterDelete1 = dashboardCount();
echo 'dashboard_after_delete_1=' . $afterDelete1 . "\n\n";

echo "STEP 6: DELETE CLIENT #2 (SOFT DELETE)\n";
$deleteB = adminPerformRecordAction('delete', 'client', $clientBId);
echo 'delete_client_b_result=' . json_encode($deleteB, JSON_UNESCAPED_UNICODE) . "\n";
echo 'client_b_row_after_delete=' . json_encode(rowById($clientBId), JSON_UNESCAPED_UNICODE) . "\n";
$afterDelete2 = dashboardCount();
echo 'dashboard_after_delete_2=' . $afterDelete2 . "\n\n";

echo "STEP 7: ASSERT DYNAMIC COUNT SEQUENCE\n";
$sequence = [
    'before' => $beforeCount,
    'after_add_1' => $afterAdd1,
    'after_add_2' => $afterAdd2,
    'after_edit' => $afterEdit,
    'after_delete_1' => $afterDelete1,
    'after_delete_2' => $afterDelete2,
];
echo 'expected_after_add_1=' . ($beforeCount + 1) . "\n";
echo 'expected_after_add_2=' . ($beforeCount + 2) . "\n";
echo 'expected_after_delete_1=' . ($beforeCount + 1) . "\n";
echo 'expected_after_delete_2=' . $beforeCount . "\n";
echo 'actual_after_delete_2=' . $afterDelete2 . "\n";
echo 'sequence_debug=' . json_encode($sequence, JSON_UNESCAPED_UNICODE) . "\n";
