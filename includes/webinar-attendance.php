<?php
declare(strict_types=1);

/**
 * Webinar attendance log: create/list records when admin marks registration attended.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin-schema.php';

function webinarAttendanceTableHasColumn(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $row = db()->fetchOne(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $cache[$key] = ((int) ($row['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function webinarAttendanceEnsureExtendedColumns(): void
{
    ensureAttendanceSchema();

    $columns = [
        ['registration_id', 'INT UNSIGNED NULL DEFAULT NULL'],
        ['marked_by_admin_id', 'INT UNSIGNED NULL DEFAULT NULL'],
        ['user_name', 'VARCHAR(200) NULL DEFAULT NULL'],
        ['webinar_title', 'VARCHAR(255) NULL DEFAULT NULL'],
        ['attendance_date', 'DATE NULL DEFAULT NULL'],
    ];

    foreach (['webinar_attendance_log', 'attendance'] as $table) {
        try {
            if (db()->fetchOne("SHOW TABLES LIKE '{$table}'") === null) {
                continue;
            }
        } catch (Throwable $e) {
            continue;
        }

        foreach ($columns as [$column, $definition]) {
            if (webinarAttendanceTableHasColumn($table, $column)) {
                continue;
            }
            try {
                db()->execute("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
            }
        }

        if (webinarAttendanceTableHasColumn($table, 'registration_id')) {
            try {
                db()->execute(
                    "ALTER TABLE `{$table}` ADD UNIQUE INDEX uq_{$table}_registration (registration_id)"
                );
            } catch (Throwable $e) {
                // index may already exist
            }
        }
    }
}

/**
 * @return array{ok:bool, id?:int, duplicate?:bool, error?:string}
 */
function webinarAttendanceLogMark(
    int $registrationId,
    int $userId,
    int $webinarId,
    string $userName,
    string $webinarTitle,
    ?int $adminId = null,
    ?int $durationMins = null
): array {
    webinarAttendanceEnsureExtendedColumns();
    $table = attendanceResolveAdminTable();

    if ($registrationId > 0 && webinarAttendanceTableHasColumn($table, 'registration_id')) {
        $existing = db()->fetchOne(
            "SELECT id FROM `{$table}` WHERE registration_id = ? LIMIT 1",
            [$registrationId]
        );
        if ($existing) {
            return ['ok' => true, 'id' => (int) $existing['id'], 'duplicate' => true];
        }
    }

    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $duration = max(0, (int) ($durationMins ?? 0));

    $fields = [
        'user_id'       => $userId,
        'webinar_id'    => $webinarId,
        'joined_at'     => $now,
        'duration_mins' => $duration,
        'marked_by'     => 'admin',
    ];

    if (webinarAttendanceTableHasColumn($table, 'registration_id')) {
        $fields['registration_id'] = $registrationId;
    }
    if (webinarAttendanceTableHasColumn($table, 'marked_by_admin_id') && $adminId !== null && $adminId > 0) {
        $fields['marked_by_admin_id'] = $adminId;
    }
    if (webinarAttendanceTableHasColumn($table, 'user_name')) {
        $fields['user_name'] = $userName;
    }
    if (webinarAttendanceTableHasColumn($table, 'webinar_title')) {
        $fields['webinar_title'] = $webinarTitle;
    }
    if (webinarAttendanceTableHasColumn($table, 'attendance_date')) {
        $fields['attendance_date'] = $today;
    }

    $cols = array_keys($fields);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));

    try {
        $id = (int) db()->insert(
            'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')',
            array_values($fields)
        );
        return ['ok' => true, 'id' => $id];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** @return array<int, array<string, mixed>> */
function webinarAttendanceFetchList(int $limit = 100): array
{
    webinarAttendanceEnsureExtendedColumns();
    $table = attendanceResolveAdminTable();

    if (!function_exists('adminSqlActive')) {
        require_once __DIR__ . '/admin-actions.php';
    }

    $active = adminSqlActive('a', false, $table);
    $adminJoin = webinarAttendanceTableHasColumn($table, 'marked_by_admin_id')
        ? 'LEFT JOIN admin adm ON adm.id = a.marked_by_admin_id'
        : '';
    $markedByExpr = webinarAttendanceTableHasColumn($table, 'marked_by_admin_id')
        ? "COALESCE(NULLIF(adm.full_name, ''), adm.username, 'Admin')"
        : "CASE a.marked_by WHEN 'admin' THEN 'Admin' WHEN 'self' THEN 'Self' ELSE 'Auto' END";

    return db()->fetchAll(
        "SELECT a.*,
                COALESCE(NULLIF(a.user_name, ''), u.full_name) AS full_name,
                u.email,
                COALESCE(NULLIF(a.webinar_title, ''), w.title) AS webinar_title,
                {$markedByExpr} AS marked_by_label
         FROM `{$table}` a
         INNER JOIN users u ON u.id = a.user_id
         INNER JOIN webinars w ON w.id = a.webinar_id
         {$adminJoin}
         WHERE {$active}
         ORDER BY a.created_at DESC
         LIMIT " . (int) max(1, $limit)
    );
}

function webinarAttendanceCount(): int
{
    webinarAttendanceEnsureExtendedColumns();
    $table = attendanceResolveAdminTable();
    if (!function_exists('adminSqlActive')) {
        require_once __DIR__ . '/admin-actions.php';
    }
    $active = adminSqlActive(null, false, $table);
    try {
        return (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$active}")['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}
