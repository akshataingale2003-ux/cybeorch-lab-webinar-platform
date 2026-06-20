<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
require_once __DIR__ . '/../includes/certificate-system.php';

$regId = (int) ($argv[1] ?? 11);
echo "Testing markWebinarRegistrationAttended($regId)...\n";
$result = markWebinarRegistrationAttended($regId);
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

$cert = db()->fetchOne(
    "SELECT certificate_id, pdf_path, email_sent, email_error FROM certificates WHERE source_type = 'webinar_registration' AND source_id = ?",
    [$regId]
);
echo $cert ? "DB: " . json_encode($cert) . "\n" : "No certificate in DB.\n";
