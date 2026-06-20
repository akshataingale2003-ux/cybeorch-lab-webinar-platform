<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/certificate-system.php';

ensureCertificatesSchema();
$result = regenerateCertificate(1);
echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
$cert = findCertificateByPublicId('CYB-2026-06-000002');
echo 'PDF exists: ' . (is_file(certificateAbsolutePath($cert['pdf_path'] ?? '') ?? '') ? 'yes' : 'no') . PHP_EOL;
