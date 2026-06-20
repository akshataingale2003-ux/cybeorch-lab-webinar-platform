<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';
require_once __DIR__ . '/../includes/admin-actions.php';

ensureInvoiceAmcSchema();

echo "STEP 1: CURRENT TOTAL CLIENTS\n";
$before = invoiceAmcDashboardStats();
echo 'dashboard_total_clients=' . (int) ($before['total_clients'] ?? -1) . "\n\n";

echo "STEP 2: CREATE NEW CLIENT (ADMIN FLOW)\n";
$newId = invoiceAmcResolveClient([
    'client_mode' => 'new',
    'name' => 'Proof Client',
    'email' => 'proof-client-' . uniqid() . '@example.com',
    'phone' => '9999999999',
    'company' => 'Proof Co',
    'address' => 'Proof Address',
    'gst_number' => 'GST-PROOF-001',
]);
echo 'created_client_id=' . (int) $newId . "\n\n";

echo "STEP 3: NEW CLIENT ROW IN clients TABLE\n";
$createdRow = db()->fetchOne(
    'SELECT id, name, email, status, is_blocked, deleted_at, created_at FROM clients WHERE id = ?',
    [(int) $newId]
);
echo 'created_row=' . json_encode($createdRow, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "STEP 4: DASHBOARD COUNT AFTER CREATION\n";
$afterCreate = invoiceAmcDashboardStats();
echo 'dashboard_total_clients=' . (int) ($afterCreate['total_clients'] ?? -1) . "\n\n";

echo "STEP 5: DELETE SAME CLIENT (ADMIN DELETE ACTION)\n";
$deleteResult = adminPerformRecordAction('delete', 'client', (int) $newId);
echo 'delete_result=' . json_encode($deleteResult, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "STEP 6: DATABASE ROW AFTER DELETION\n";
$deletedRow = db()->fetchOne(
    'SELECT id, name, email, status, is_blocked, deleted_at, updated_at FROM clients WHERE id = ?',
    [(int) $newId]
);
echo 'row_after_delete=' . json_encode($deletedRow, JSON_UNESCAPED_UNICODE) . "\n\n";

echo "STEP 7: DASHBOARD COUNT AFTER DELETION\n";
$afterDelete = invoiceAmcDashboardStats();
echo 'dashboard_total_clients=' . (int) ($afterDelete['total_clients'] ?? -1) . "\n";
