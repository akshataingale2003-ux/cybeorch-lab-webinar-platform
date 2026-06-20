<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureFreelancerRegistrationsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS freelancer_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(15) DEFAULT NULL,
        primary_role VARCHAR(50) NOT NULL,
        experience_level ENUM('fresher','1-2','3-5','5+') NOT NULL,
        skills TEXT NOT NULL,
        portfolio_url VARCHAR(500) DEFAULT NULL,
        github_url VARCHAR(500) DEFAULT NULL,
        linkedin_url VARCHAR(500) DEFAULT NULL,
        availability ENUM('full_time','part_time','project_based') NOT NULL,
        location VARCHAR(100) DEFAULT NULL,
        about TEXT,
        status ENUM('pending','reviewed','shortlisted','rejected','active') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_freelancer_email_role (email, primary_role),
        UNIQUE KEY unique_freelancer_user_role (user_id, primary_role)
    )");

    migrateFreelancerRegistrationsUniqueKeys();
    migrateFreelancerResumeColumns();
}

function migrateFreelancerResumeColumns(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db()->execute('ALTER TABLE freelancer_registrations ADD COLUMN resume_path VARCHAR(500) DEFAULT NULL AFTER about');
    } catch (Throwable $e) {
        // column already exists
    }
    try {
        db()->execute('ALTER TABLE freelancer_registrations ADD COLUMN resume_original_name VARCHAR(255) DEFAULT NULL AFTER resume_path');
    } catch (Throwable $e) {
        // column already exists
    }
}

function migrateFreelancerRegistrationsUniqueKeys(): void
{
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    try {
        if (db()->fetchOne("SHOW INDEX FROM freelancer_registrations WHERE Key_name = 'unique_freelancer_email'")) {
            db()->execute('ALTER TABLE freelancer_registrations DROP INDEX unique_freelancer_email');
        }
    } catch (Throwable $e) {
        // Table may not exist yet on first bootstrap.
    }

    try {
        if (!db()->fetchOne("SHOW INDEX FROM freelancer_registrations WHERE Key_name = 'unique_freelancer_email_role'")) {
            db()->execute('ALTER TABLE freelancer_registrations ADD UNIQUE KEY unique_freelancer_email_role (email, primary_role)');
        }
    } catch (Throwable $e) {
    }

    try {
        if (!db()->fetchOne("SHOW INDEX FROM freelancer_registrations WHERE Key_name = 'unique_freelancer_user_role'")) {
            db()->execute('ALTER TABLE freelancer_registrations ADD UNIQUE KEY unique_freelancer_user_role (user_id, primary_role)');
        }
    } catch (Throwable $e) {
    }
}

/** @return list<string> */
function getFreelancerAppliedRoles(?int $userId = null, string $email = ''): array
{
    ensureFreelancerRegistrationsSchema();

    if ($userId) {
        $rows = db()->fetchAll(
            'SELECT primary_role FROM freelancer_registrations WHERE user_id = ? ORDER BY created_at ASC',
            [$userId]
        );

        return array_values(array_column($rows, 'primary_role'));
    }

    $email = strtolower(trim($email));
    if ($email === '') {
        return [];
    }

    $rows = db()->fetchAll(
        'SELECT primary_role FROM freelancer_registrations WHERE email = ? ORDER BY created_at ASC',
        [$email]
    );

    return array_values(array_column($rows, 'primary_role'));
}

function freelancerRoleAlreadyApplied(?int $userId, string $email, string $role): bool
{
    ensureFreelancerRegistrationsSchema();
    $role = trim($role);
    if ($role === '') {
        return false;
    }

    if ($userId) {
        return (bool) db()->fetchOne(
            'SELECT id FROM freelancer_registrations WHERE user_id = ? AND primary_role = ? LIMIT 1',
            [$userId, $role]
        );
    }

    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }

    return (bool) db()->fetchOne(
        'SELECT id FROM freelancer_registrations WHERE email = ? AND primary_role = ? LIMIT 1',
        [$email, $role]
    );
}

function ensureAttendanceSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute('CREATE TABLE IF NOT EXISTS webinar_attendance_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        webinar_id INT NOT NULL,
        joined_at DATETIME DEFAULT NULL,
        left_at DATETIME DEFAULT NULL,
        duration_mins INT DEFAULT 0,
        marked_by ENUM(\'self\',\'admin\',\'auto\') DEFAULT \'auto\',
        registration_id INT UNSIGNED NULL DEFAULT NULL,
        marked_by_admin_id INT UNSIGNED NULL DEFAULT NULL,
        user_name VARCHAR(200) NULL DEFAULT NULL,
        webinar_title VARCHAR(255) NULL DEFAULT NULL,
        attendance_date DATE NULL DEFAULT NULL,
        record_status ENUM(\'active\',\'blocked\') NOT NULL DEFAULT \'active\',
        deleted_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_webinar_attendance_user (user_id),
        INDEX idx_webinar_attendance_webinar (webinar_id)
    )');

    db()->execute('CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        webinar_id INT NOT NULL,
        joined_at DATETIME DEFAULT NULL,
        left_at DATETIME DEFAULT NULL,
        duration_mins INT DEFAULT 0,
        marked_by ENUM(\'self\',\'admin\',\'auto\') DEFAULT \'auto\',
        registration_id INT UNSIGNED NULL DEFAULT NULL,
        marked_by_admin_id INT UNSIGNED NULL DEFAULT NULL,
        user_name VARCHAR(200) NULL DEFAULT NULL,
        webinar_title VARCHAR(255) NULL DEFAULT NULL,
        attendance_date DATE NULL DEFAULT NULL,
        record_status ENUM(\'active\',\'blocked\') NOT NULL DEFAULT \'active\',
        deleted_at DATETIME NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attendance_user (user_id),
        INDEX idx_attendance_webinar (webinar_id)
    )');

    attendanceEnsureActionColumns();
}

/** Add soft-delete / block columns to attendance tables (idempotent). */
function attendanceEnsureActionColumns(): void
{
    foreach (['webinar_attendance_log', 'attendance'] as $table) {
        try {
            $exists = db()->fetchOne("SHOW TABLES LIKE '{$table}'");
            if ($exists === null) {
                continue;
            }
        } catch (Throwable $e) {
            continue;
        }
        foreach (
            [
                ['deleted_at', 'DATETIME NULL DEFAULT NULL'],
                ['record_status', "ENUM('active','blocked') NOT NULL DEFAULT 'active'"],
                ['registration_id', 'INT UNSIGNED NULL DEFAULT NULL'],
                ['marked_by_admin_id', 'INT UNSIGNED NULL DEFAULT NULL'],
                ['user_name', 'VARCHAR(200) NULL DEFAULT NULL'],
                ['webinar_title', 'VARCHAR(255) NULL DEFAULT NULL'],
                ['attendance_date', 'DATE NULL DEFAULT NULL'],
            ] as [$column, $definition]
        ) {
            try {
                $row = db()->fetchOne(
                    'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$table, $column]
                );
                if (((int) ($row['c'] ?? 0)) > 0) {
                    continue;
                }
                db()->execute("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (Throwable $e) {
                // ignore per-column migration failures
            }
        }

        try {
            db()->execute("ALTER TABLE `{$table}` ADD UNIQUE INDEX uq_{$table}_registration (registration_id)");
        } catch (Throwable $e) {
        }
    }
}

/** @return 'webinar_attendance_log'|'attendance' */
function attendanceResolveAdminTable(): string
{
    ensureAttendanceSchema();
    try {
        if (db()->fetchOne("SHOW TABLES LIKE 'webinar_attendance_log'") !== null) {
            return 'webinar_attendance_log';
        }
        if (db()->fetchOne("SHOW TABLES LIKE 'attendance'") !== null) {
            return 'attendance';
        }
    } catch (Throwable $e) {
    }
    return 'webinar_attendance_log';
}

function attendanceAdminEntityForTable(string $table): string
{
    return $table === 'attendance' ? 'attendance' : 'webinar_attendance_log';
}

function getPendingFreelancerCount(): int
{
    ensureFreelancerRegistrationsSchema();
    return (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM freelancer_registrations WHERE status='pending'")['c'] ?? 0);
}
