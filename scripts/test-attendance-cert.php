<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
require_once __DIR__ . '/../includes/certificate-system.php';

// Find unattended registration to test full attendance -> certificate flow
$row = db()->fetchOne('SELECT id FROM webinar_registrations WHERE attended = 0 LIMIT 1');
if (!$row) {
    echo "No unattended registration to test.\n";
    exit(0);
}
$regId = (int) $row['id'];
echo "Marking registration $regId as attended...\n";
$result = markWebinarRegistrationAttended($regId);
print_r($result);
$cert = db()->fetchOne(
    "SELECT * FROM certificates WHERE source_type = 'webinar_registration' AND source_id = ?",
    [$regId]
);
echo $cert ? "Certificate: {$cert['certificate_id']}\n" : "No certificate row.\n";
