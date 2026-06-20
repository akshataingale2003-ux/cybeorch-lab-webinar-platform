<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/site-database.php';
require_once __DIR__ . '/../includes/webinar-attendance.php';

ensureSiteDatabaseSchemas();
webinarAttendanceEnsureExtendedColumns();

$user = db()->fetchOne('SELECT id, full_name FROM users LIMIT 1');
$webinar = db()->fetchOne('SELECT id, title, duration_mins FROM webinars LIMIT 1');

if (!$user || !$webinar) {
    echo "Need at least one user and webinar.\n";
    exit(1);
}

$result = webinarAttendanceLogMark(
    999,
    (int) $user['id'],
    (int) $webinar['id'],
    (string) $user['full_name'],
    (string) $webinar['title'],
    1,
    (int) $webinar['duration_mins']
);

echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
echo 'count: ' . webinarAttendanceCount() . "\n";
$list = webinarAttendanceFetchList(1);
echo json_encode($list[0] ?? [], JSON_PRETTY_PRINT) . "\n";
