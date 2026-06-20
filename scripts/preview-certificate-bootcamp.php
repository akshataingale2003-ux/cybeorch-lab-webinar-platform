<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/certificate-system.php';

ensureCertificatesSchema();

$sample = [
    'certificate_id' => 'CYB-2026-06-000001',
    'user_name'      => 'Akshata Ingale',
    'event_name'     => 'Ethical Hacking Bootcamp',
    'event_type'     => 'bootcamp',
    'event_date'     => '2026-06-20',
];

$result = generateCertificateAssets($sample);
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
