<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/certificate-system.php';

ensureCertificatesSchema();

$sample = [
    'certificate_id' => 'CYB-2026-06-000001',
    'user_name'      => 'Akshata Ingale',
    'event_name'     => 'Cybersecurity',
    'event_type'     => 'webinar',
    'event_date'     => '2026-06-07',
];

$result = generateCertificateAssets($sample);
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
if (!empty($result['png'])) {
    $preview = dirname(__DIR__) . '/uploads/certificates/layout-preview.png';
    copy(certificateAbsolutePath($result['png']), $preview);
    echo "Preview: uploads/certificates/layout-preview.png\n";
}
