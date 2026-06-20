<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/site-database.php';

ensureSiteDatabaseSchemas();

$tables = ['webinar_attendance_log', 'attendance'];
$columns = ['deleted_at', 'record_status'];

foreach ($tables as $table) {
    echo "Table: {$table}" . PHP_EOL;
    try {
        $exists = db()->fetchOne("SHOW TABLES LIKE '{$table}'");
        if ($exists === null) {
            echo "  (not found)" . PHP_EOL;
            continue;
        }
    } catch (Throwable $e) {
        echo "  (check failed: {$e->getMessage()})" . PHP_EOL;
        continue;
    }
    foreach ($columns as $column) {
        $row = db()->fetchOne(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $ok = ((int) ($row['c'] ?? 0)) > 0;
        echo '  ' . ($ok ? '[OK]' : '[MISSING]') . " {$column}" . PHP_EOL;
    }
}

$table = attendanceResolveAdminTable();
$sql = "SELECT a.*, u.full_name, w.title AS webinar_title
        FROM `{$table}` a
        JOIN users u ON u.id = a.user_id
        JOIN webinars w ON w.id = a.webinar_id
        WHERE " . adminSqlActive('a', false, $table) . "
        ORDER BY a.created_at DESC LIMIT 5";
db()->fetchAll($sql);
echo "Attendance list query OK on {$table}" . PHP_EOL;
