<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/certificate-system.php';

ensureCertificatesSchema();

$rows = db()->fetchAll('SELECT id, certificate_id FROM certificates ORDER BY id');
foreach ($rows as $row) {
    $result = regenerateCertificate((int) $row['id']);
    echo ($row['certificate_id'] ?? '?') . ': ' . ($result['success'] ? 'ok' : ($result['message'] ?? 'fail')) . PHP_EOL;
}
