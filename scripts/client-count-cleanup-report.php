<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';

$predicate = "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
AND (status IS NULL OR status = '' OR LOWER(status) NOT IN ('deleted','inactive'))
AND (is_blocked = 0 OR is_blocked IS NULL)";

$allBefore = db()->fetchAll(
    "SELECT id, client_code, name, email, status, is_blocked, deleted_at, created_at
     FROM clients
     ORDER BY id ASC"
);
$countedBefore = db()->fetchAll(
    "SELECT id, client_code, name, email, status, is_blocked, deleted_at, created_at
     FROM clients
     WHERE {$predicate}
     ORDER BY id ASC"
);

echo "ALL_CLIENTS_BEFORE=" . json_encode($allBefore, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "COUNTED_CLIENTS_BEFORE=" . json_encode($countedBefore, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "COUNTED_TOTAL_BEFORE=" . count($countedBefore) . PHP_EOL;

$ids = array_map(static fn (array $r): int => (int) $r['id'], $countedBefore);
if ($ids !== []) {
    $marks = implode(',', array_fill(0, count($ids), '?'));
    db()->execute("UPDATE clients SET deleted_at = NOW() WHERE id IN ({$marks})", $ids);
}

$countAfter = (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM clients WHERE {$predicate}")['c'] ?? 0);
$allAfter = db()->fetchAll(
    "SELECT id, client_code, name, email, status, is_blocked, deleted_at, created_at
     FROM clients
     ORDER BY id ASC"
);

echo "MARKED_IDS=" . json_encode($ids, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "COUNTED_TOTAL_AFTER=" . $countAfter . PHP_EOL;
echo "ALL_CLIENTS_AFTER=" . json_encode($allAfter, JSON_UNESCAPED_UNICODE) . PHP_EOL;
