<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/certificate-system.php';

ensureCertificatesSchema();

echo "=== Attended webinars without certificate ===\n";
$missing = db()->fetchAll(
    "SELECT wr.id, wr.user_id, wr.attended, u.full_name, u.email, w.title
     FROM webinar_registrations wr
     JOIN users u ON u.id = wr.user_id
     JOIN webinars w ON w.id = wr.webinar_id
     LEFT JOIN certificates c ON c.source_type = 'webinar_registration' AND c.source_id = wr.id
     WHERE wr.attended = 1 AND c.id IS NULL
     LIMIT 10"
);
echo json_encode($missing, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Recent certificates ===\n";
$certs = db()->fetchAll('SELECT id, certificate_id, user_id, source_type, source_id, pdf_path, png_path, email_sent, email_error FROM certificates ORDER BY id DESC LIMIT 5');
foreach ($certs as $c) {
    $pdf = certificateAbsolutePath($c['pdf_path'] ?? null);
    $c['pdf_exists'] = $pdf && is_file($pdf);
    echo json_encode($c) . "\n";
}

if (!empty($missing[0]['id'])) {
    $regId = (int) $missing[0]['id'];
    echo "\n=== Trying issueCertificateForWebinarRegistration($regId) ===\n";
    $result = issueCertificateForWebinarRegistration($regId, false);
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
