<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/site-database.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
require_once __DIR__ . '/../includes/webinar-attendance.php';

ensureSiteDatabaseSchemas();

$reg = db()->fetchOne(
    "SELECT wr.id FROM webinar_registrations wr
     WHERE wr.attended = 0
     ORDER BY wr.id DESC LIMIT 1"
);

if (!$reg) {
    $reg = db()->fetchOne('SELECT id FROM webinar_registrations ORDER BY id DESC LIMIT 1');
}

if (!$reg) {
    echo "No webinar registrations found to test.\n";
    exit(1);
}

$regId = (int) $reg['id'];
echo "Marking registration #{$regId}...\n";

$result = markWebinarRegistrationAttended($regId, 1);
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

$count = webinarAttendanceCount();
echo "Attendance count: {$count}\n";

$rows = webinarAttendanceFetchList(5);
echo "Latest attendance rows: " . count($rows) . "\n";
if ($rows) {
    echo json_encode($rows[0], JSON_PRETTY_PRINT) . "\n";
}
