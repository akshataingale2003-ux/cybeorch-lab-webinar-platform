<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
require_once __DIR__ . '/../includes/certificate-system.php';

ensureCertificatesSchema();

echo "=== Attended webinars without certificates ===\n";
$missing = db()->fetchAll(
    "SELECT wr.id, wr.user_id, u.full_name, w.title
     FROM webinar_registrations wr
     JOIN users u ON u.id = wr.user_id
     JOIN webinars w ON w.id = wr.webinar_id
     LEFT JOIN certificates c ON c.source_type = 'webinar_registration' AND c.source_id = wr.id
     WHERE wr.attended = 1 AND c.id IS NULL"
);
print_r($missing);

if ($missing) {
    $regId = (int) $missing[0]['id'];
    echo "\n=== Testing issueCertificateForWebinarRegistration($regId) ===\n";
    $result = issueCertificateForWebinarRegistration($regId, false);
    print_r($result);
    if (!empty($result['certificate'])) {
        $cert = $result['certificate'];
        foreach (['pdf_path', 'png_path', 'jpg_path'] as $k) {
            $abs = certificateAbsolutePath((string) ($cert[$k] ?? ''));
            echo "$k: " . ($abs && is_file($abs) ? 'OK ' . filesize($abs) . ' bytes' : 'MISSING') . "\n";
        }
    }
}

echo "\n=== Total certificates ===\n";
print_r(db()->fetchAll('SELECT certificate_id, user_id, event_name, email_sent, pdf_path FROM certificates'));

echo "\n=== syncMissingCertificatesForUser(6) ===\n";
echo 'issued: ' . syncMissingCertificatesForUser(6) . "\n";
