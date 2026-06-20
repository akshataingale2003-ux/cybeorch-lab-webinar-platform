<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin-actions.php';

/** 50 GB max video size */
const RECORDED_SESSION_MAX_BYTES = 53687091200;
const RECORDED_SESSION_CHUNK_BYTES = 8388608; // 8 MB per chunk
const RECORDED_SESSION_STATUS_DRAFT = 'Draft';
const RECORDED_SESSION_STATUS_PUBLISHED = 'Published';
const RECORDED_SESSION_LINK_WEBINAR = 'webinar';
const RECORDED_SESSION_LINK_BOOTCAMP = 'bootcamp';
const COURSE_ACCESS_MONTHS = 6;
const RECORDED_SESSION_MAX_WATCHES = 2;
/** User must reach this fraction of duration for a view to count. */
const RECORDED_SESSION_COMPLETION_RATIO = 0.95;

function ensureRecordedSessionsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute(
        'CREATE TABLE IF NOT EXISTS recorded_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT,
            link_type ENUM(\'webinar\',\'bootcamp\') NOT NULL DEFAULT \'webinar\',
            webinar_id INT NULL DEFAULT NULL,
            bootcamp_id INT NULL DEFAULT NULL,
            video_path VARCHAR(500) DEFAULT NULL,
            video_original VARCHAR(255) DEFAULT NULL,
            video_mime VARCHAR(120) DEFAULT NULL,
            video_size BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'Draft\',
            sort_order INT NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL DEFAULT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_recorded_sessions_slug (slug),
            INDEX idx_rs_webinar (webinar_id),
            INDEX idx_rs_bootcamp (bootcamp_id),
            INDEX idx_rs_status (status),
            INDEX idx_rs_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    db()->execute(
        'CREATE TABLE IF NOT EXISTS recorded_session_watch_stats (
            user_id INT NOT NULL,
            session_id INT NOT NULL,
            completed_views INT NOT NULL DEFAULT 0,
            session_peak_position DOUBLE NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, session_id),
            INDEX idx_rsw_session (session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    db()->execute(
        'CREATE TABLE IF NOT EXISTS recorded_session_upload_jobs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            upload_token VARCHAR(64) NOT NULL,
            admin_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            temp_path VARCHAR(500) NOT NULL,
            file_ext VARCHAR(12) NOT NULL,
            total_size BIGINT UNSIGNED NOT NULL,
            received_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            mime VARCHAR(120) DEFAULT NULL,
            status ENUM(\'pending\',\'complete\',\'claimed\',\'failed\',\'cancelled\') NOT NULL DEFAULT \'pending\',
            final_path VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            UNIQUE KEY uk_rs_upload_token (upload_token),
            INDEX idx_rs_upload_admin (admin_id),
            INDEX idx_rs_upload_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    recordedSessionMigrateSchemaColumns();
    ensureRecordedSessionUploadsDir();
}

function recordedSessionMigrateSchemaColumns(): void
{
    if (!adminTableHasColumn('recorded_sessions', 'id')) {
        return;
    }

    if (!adminTableHasColumn('recorded_sessions', 'link_type')) {
        db()->execute(
            "ALTER TABLE recorded_sessions ADD COLUMN link_type ENUM('webinar','bootcamp') NOT NULL DEFAULT 'webinar' AFTER description"
        );
    }
    if (!adminTableHasColumn('recorded_sessions', 'webinar_id')) {
        db()->execute(
            'ALTER TABLE recorded_sessions ADD COLUMN webinar_id INT NULL DEFAULT NULL AFTER link_type'
        );
    }
    if (!adminTableHasColumn('recorded_sessions', 'bootcamp_id')) {
        db()->execute(
            'ALTER TABLE recorded_sessions ADD COLUMN bootcamp_id INT NULL DEFAULT NULL AFTER webinar_id'
        );
    }

    try {
        if (!recordedSessionIndexExists('recorded_sessions', 'idx_rs_webinar')) {
            db()->execute('ALTER TABLE recorded_sessions ADD INDEX idx_rs_webinar (webinar_id)');
        }
        if (!recordedSessionIndexExists('recorded_sessions', 'idx_rs_bootcamp')) {
            db()->execute('ALTER TABLE recorded_sessions ADD INDEX idx_rs_bootcamp (bootcamp_id)');
        }
    } catch (Throwable $e) {
    }
}

function recordedSessionIndexExists(string $table, string $indexName): bool
{
    try {
        $row = db()->fetchOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );
        return ((int) ($row['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function recordedSessionUploadsDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recorded-sessions';
}

function recordedSessionUploadTempDir(): string
{
    return recordedSessionUploadsDir() . DIRECTORY_SEPARATOR . '.tmp';
}

function ensureRecordedSessionUploadsDir(): void
{
    foreach ([recordedSessionUploadsDir(), recordedSessionUploadTempDir()] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    $htaccess = recordedSessionUploadsDir() . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents(
            $htaccess,
            "Options -Indexes\n\nRequire all denied\n"
        );
    }

    $tmpHtaccess = recordedSessionUploadTempDir() . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($tmpHtaccess)) {
        @file_put_contents($tmpHtaccess, "Require all denied\n");
    }
}

/** @return list<string> */
function recordedSessionAllowedExtensions(): array
{
    return ['mp4', 'mkv', 'webm', 'mov', 'avi', 'm4v'];
}

/** @return array<string, list<string>> */
function recordedSessionAllowedMimeTypes(): array
{
    return [
        'mp4'  => ['video/mp4', 'application/mp4'],
        'mkv'  => ['video/x-matroska', 'video/mkv'],
        'webm' => ['video/webm'],
        'mov'  => ['video/quicktime', 'video/mp4'],
        'avi'  => ['video/x-msvideo', 'video/avi', 'video/msvideo'],
        'm4v'  => ['video/x-m4v', 'video/mp4'],
    ];
}

function recordedSessionStatusOptions(): array
{
    return [RECORDED_SESSION_STATUS_DRAFT, RECORDED_SESSION_STATUS_PUBLISHED];
}

function recordedSessionLinkTypeOptions(): array
{
    return [
        RECORDED_SESSION_LINK_WEBINAR  => 'Webinar',
        RECORDED_SESSION_LINK_BOOTCAMP => 'Bootcamp',
    ];
}

function recordedSessionFormatBytes(int|float $bytes): string
{
    $bytes = (float) $bytes;
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return (int) $bytes . ' B';
}

function recordedSessionMaxBytesLabel(): string
{
    return recordedSessionFormatBytes(RECORDED_SESSION_MAX_BYTES);
}

function recordedSessionValidateExtension(string $filename): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, recordedSessionAllowedExtensions(), true)) {
        return null;
    }
    return $ext;
}

function recordedSessionDetectMime(string $absolutePath, string $ext): string
{
    $allowedMimes = recordedSessionAllowedMimeTypes()[$ext] ?? [];
    if (function_exists('finfo_open') && is_file($absolutePath)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = (string) finfo_file($finfo, $absolutePath);
            finfo_close($finfo);
            if ($detected !== '') {
                if ($allowedMimes === [] || in_array($detected, $allowedMimes, true) || str_starts_with($detected, 'video/')) {
                    return $detected;
                }
            }
        }
    }
    return $allowedMimes[0] ?? 'video/mp4';
}

function recordedSessionUploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Upload chunk exceeds server limit. Try again or contact support.',
        UPLOAD_ERR_PARTIAL => 'Upload was interrupted. Please retry.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder missing. Contact support.',
        UPLOAD_ERR_CANT_WRITE => 'Could not write upload to disk. Check permissions.',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
        default => 'Could not upload the video. Please try again.',
    };
}

/**
 * @param array<string, mixed> $file $_FILES['video']
 * @return array{ok: bool, path: string, original_name: string, mime: string, size: int, error: string}
 */
function validateAndStoreRecordedSessionVideo(array $file): array
{
    $fail = static fn (string $message): array => [
        'ok' => false, 'path' => '', 'original_name' => '', 'mime' => '', 'size' => 0, 'error' => $message,
    ];

    $name = trim((string) ($file['name'] ?? ''));
    if ($name === '') {
        return $fail('Please upload a video file (MP4, MKV, WEBM, MOV, AVI, or M4V).');
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $fail('Please upload a video file.');
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        return $fail(recordedSessionUploadErrorMessage($errorCode));
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $fail('The uploaded video file is empty.');
    }
    if ($size > RECORDED_SESSION_MAX_BYTES) {
        return $fail('Video must be under ' . recordedSessionMaxBytesLabel() . '.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid video upload. Please try again.');
    }

    $ext = recordedSessionValidateExtension($name);
    if ($ext === null) {
        return $fail('Allowed video formats: MP4, MKV, WEBM, MOV, AVI, M4V.');
    }

    ensureRecordedSessionUploadsDir();
    $base = slugify(pathinfo($name, PATHINFO_FILENAME)) ?: 'session';
    $stored = $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = recordedSessionUploadsDir() . DIRECTORY_SEPARATOR . $stored;

    if (!move_uploaded_file($tmp, $dest)) {
        return $fail('Could not save the video file. Check folder permissions.');
    }

    return [
        'ok'            => true,
        'path'          => 'uploads/recorded-sessions/' . $stored,
        'original_name' => $name,
        'mime'          => recordedSessionDetectMime($dest, $ext),
        'size'          => $size,
        'error'         => '',
    ];
}

function recordedSessionDeleteVideoFile(?string $relativePath): void
{
    if ($relativePath === null || trim($relativePath) === '') {
        return;
    }
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    if (!str_starts_with($relativePath, 'uploads/recorded-sessions/')) {
        return;
    }
    $absolute = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function recordedSessionAbsolutePath(?string $relativePath): ?string
{
    if ($relativePath === null || trim($relativePath) === '') {
        return null;
    }
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    if (!str_starts_with($relativePath, 'uploads/recorded-sessions/')) {
        return null;
    }
    $absolute = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($absolute) ? $absolute : null;
}

function recordedSessionGetById(int $id, bool $includeTrashed = false): ?array
{
    ensureRecordedSessionsSchema();
    $sql = 'SELECT rs.*, w.title AS webinar_title, b.title AS bootcamp_title
            FROM recorded_sessions rs
            LEFT JOIN webinars w ON w.id = rs.webinar_id
            LEFT JOIN bootcamps b ON b.id = rs.bootcamp_id
            WHERE rs.id = ?';
    if (!$includeTrashed) {
        $sql .= ' AND ' . adminSqlActive('rs');
    }
    $row = db()->fetchOne($sql, [$id]);
    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function recordedSessionListForAdmin(): array
{
    ensureRecordedSessionsSchema();
    return db()->fetchAll(
        'SELECT rs.*, w.title AS webinar_title, b.title AS bootcamp_title
         FROM recorded_sessions rs
         LEFT JOIN webinars w ON w.id = rs.webinar_id
         LEFT JOIN bootcamps b ON b.id = rs.bootcamp_id
         WHERE ' . adminSqlActive('rs') . '
         ORDER BY rs.sort_order ASC, rs.created_at DESC'
    );
}

/**
 * @return array{enrolled_at: string, expires_at: string}|null
 */
function recordedSessionGetUserEnrollment(int $userId, array $session): ?array
{
    if ($userId < 1) {
        return null;
    }
    $linkType = (string) ($session['link_type'] ?? '');
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        $row = db()->fetchOne(
            "SELECT registered_at AS enrolled_at FROM webinar_registrations
             WHERE user_id = ? AND webinar_id = ? AND payment_status IN ('paid','free')
             ORDER BY registered_at DESC LIMIT 1",
            [$userId, (int) ($session['webinar_id'] ?? 0)]
        );
    } elseif ($linkType === RECORDED_SESSION_LINK_BOOTCAMP) {
        $row = db()->fetchOne(
            "SELECT enrolled_at FROM bootcamp_enrollments
             WHERE user_id = ? AND bootcamp_id = ? AND payment_status = 'paid'
             ORDER BY enrolled_at DESC LIMIT 1",
            [$userId, (int) ($session['bootcamp_id'] ?? 0)]
        );
    } else {
        return null;
    }
    if (!$row || empty($row['enrolled_at'])) {
        return null;
    }
    $enrolledAt = (string) $row['enrolled_at'];
    $expiresAt = date('Y-m-d H:i:s', strtotime($enrolledAt . ' +' . COURSE_ACCESS_MONTHS . ' months'));
    return ['enrolled_at' => $enrolledAt, 'expires_at' => $expiresAt];
}

function recordedSessionEnrollmentIsActive(int $userId, array $session): bool
{
    $enrollment = recordedSessionGetUserEnrollment($userId, $session);
    if ($enrollment === null) {
        return false;
    }
    return strtotime($enrollment['expires_at']) > time();
}

function recordedSessionUserHasWebinarAccess(int $userId, int $webinarId): bool
{
    if ($userId < 1 || $webinarId < 1) {
        return false;
    }
    $row = db()->fetchOne(
        "SELECT id FROM webinar_registrations
         WHERE user_id = ? AND webinar_id = ? AND payment_status IN ('paid','free')
           AND registered_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
         LIMIT 1",
        [$userId, $webinarId, COURSE_ACCESS_MONTHS]
    );
    return $row !== null;
}

function recordedSessionUserHasBootcampAccess(int $userId, int $bootcampId): bool
{
    if ($userId < 1 || $bootcampId < 1) {
        return false;
    }
    $row = db()->fetchOne(
        "SELECT id FROM bootcamp_enrollments
         WHERE user_id = ? AND bootcamp_id = ? AND payment_status = 'paid'
           AND enrolled_at > DATE_SUB(NOW(), INTERVAL ? MONTH)
         LIMIT 1",
        [$userId, $bootcampId, COURSE_ACCESS_MONTHS]
    );
    return $row !== null;
}

function recordedSessionUserHasAccess(int $userId, array $session): bool
{
    if ($userId < 1) {
        return false;
    }
    if (($session['status'] ?? '') !== RECORDED_SESSION_STATUS_PUBLISHED) {
        return false;
    }
    if (adminRecordIsBlocked($session)) {
        return false;
    }
    if (!recordedSessionHasVideoFile($session)) {
        return false;
    }

    $linkType = (string) ($session['link_type'] ?? '');
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        return recordedSessionUserHasWebinarAccess($userId, (int) ($session['webinar_id'] ?? 0));
    }
    if ($linkType === RECORDED_SESSION_LINK_BOOTCAMP) {
        return recordedSessionUserHasBootcampAccess($userId, (int) ($session['bootcamp_id'] ?? 0));
    }
    return false;
}

function recordedSessionAdminHasAccess(): bool
{
    startSession();
    return isAdminLoggedIn();
}

/** @return array{completed_views: int, remaining: int, can_watch: bool} */
function recordedSessionGetWatchStats(int $userId, int $sessionId): array
{
    ensureRecordedSessionsSchema();
    $row = db()->fetchOne(
        'SELECT completed_views FROM recorded_session_watch_stats WHERE user_id = ? AND session_id = ?',
        [$userId, $sessionId]
    );
    $completed = (int) ($row['completed_views'] ?? 0);
    $remaining = max(0, RECORDED_SESSION_MAX_WATCHES - $completed);
    return [
        'completed_views' => $completed,
        'remaining'       => $remaining,
        'can_watch'       => $remaining > 0,
    ];
}

function recordedSessionUserCanWatch(int $userId, array $session): bool
{
    if (!recordedSessionUserHasAccess($userId, $session)) {
        return false;
    }
    $stats = recordedSessionGetWatchStats($userId, (int) $session['id']);
    return $stats['can_watch'];
}

function recordedSessionCanStream(int $userId, array $session): bool
{
    if (recordedSessionAdminHasAccess()) {
        return true;
    }
    return recordedSessionUserCanWatch($userId, $session);
}

/** @return list<array<string, mixed>> */
function recordedSessionListForUser(int $userId): array
{
    ensureRecordedSessionsSchema();
    if ($userId < 1) {
        return [];
    }

    $months = COURSE_ACCESS_MONTHS;
    return db()->fetchAll(
        "SELECT rs.*, w.title AS webinar_title, b.title AS bootcamp_title,
                COALESCE(wr.registered_at, be.enrolled_at) AS enrolled_at,
                COALESCE(
                    DATE_ADD(wr.registered_at, INTERVAL {$months} MONTH),
                    DATE_ADD(be.enrolled_at, INTERVAL {$months} MONTH)
                ) AS access_expires_at,
                COALESCE(ws.completed_views, 0) AS completed_views
         FROM recorded_sessions rs
         LEFT JOIN webinars w ON w.id = rs.webinar_id
         LEFT JOIN bootcamps b ON b.id = rs.bootcamp_id
         LEFT JOIN webinar_registrations wr ON rs.link_type = 'webinar'
             AND wr.webinar_id = rs.webinar_id AND wr.user_id = ?
             AND wr.payment_status IN ('paid','free')
             AND wr.registered_at > DATE_SUB(NOW(), INTERVAL {$months} MONTH)
         LEFT JOIN bootcamp_enrollments be ON rs.link_type = 'bootcamp'
             AND be.bootcamp_id = rs.bootcamp_id AND be.user_id = ?
             AND be.payment_status = 'paid'
             AND be.enrolled_at > DATE_SUB(NOW(), INTERVAL {$months} MONTH)
         LEFT JOIN recorded_session_watch_stats ws ON ws.session_id = rs.id AND ws.user_id = ?
         WHERE " . adminSqlActive('rs') . "
           AND rs.status = ?
           AND rs.video_path IS NOT NULL AND TRIM(rs.video_path) != ''
           AND (wr.id IS NOT NULL OR be.id IS NOT NULL)
         ORDER BY rs.sort_order ASC, rs.created_at DESC",
        [$userId, $userId, $userId, RECORDED_SESSION_STATUS_PUBLISHED]
    );
}

/**
 * @return array{ok: bool, completed_views: int, remaining: int, counted: bool, message: string}
 */
function recordedSessionRecordWatchProgress(int $userId, int $sessionId, float $position, float $duration): array
{
    ensureRecordedSessionsSchema();
    $position = max(0, $position);
    $duration = max(0, $duration);

    db()->execute(
        'INSERT INTO recorded_session_watch_stats (user_id, session_id, session_peak_position)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            session_peak_position = GREATEST(session_peak_position, VALUES(session_peak_position)),
            updated_at = NOW()',
        [$userId, $sessionId, $position]
    );

    $stats = recordedSessionGetWatchStats($userId, $sessionId);
    return [
        'ok'              => true,
        'completed_views' => $stats['completed_views'],
        'remaining'       => $stats['remaining'],
        'counted'         => false,
        'message'         => '',
    ];
}

/**
 * @return array{ok: bool, completed_views: int, remaining: int, counted: bool, message: string}
 */
function recordedSessionRecordWatchComplete(int $userId, int $sessionId, float $position, float $duration): array
{
    ensureRecordedSessionsSchema();
    $session = recordedSessionGetById($sessionId);
    if (!$session || !recordedSessionUserHasAccess($userId, $session)) {
        return ['ok' => false, 'completed_views' => 0, 'remaining' => 0, 'counted' => false, 'message' => 'Access denied.'];
    }

    $stats = recordedSessionGetWatchStats($userId, $sessionId);
    if (!$stats['can_watch']) {
        return [
            'ok'              => false,
            'completed_views' => $stats['completed_views'],
            'remaining'       => 0,
            'counted'         => false,
            'message'         => 'You have reached the maximum of ' . RECORDED_SESSION_MAX_WATCHES . ' full views for this video.',
        ];
    }

    $duration = max(0.0, $duration);
    $position = max(0.0, $position);
    if ($duration < 1) {
        return ['ok' => false, 'completed_views' => $stats['completed_views'], 'remaining' => $stats['remaining'], 'counted' => false, 'message' => 'Invalid playback duration.'];
    }

    $row = db()->fetchOne(
        'SELECT session_peak_position FROM recorded_session_watch_stats WHERE user_id = ? AND session_id = ?',
        [$userId, $sessionId]
    );
    $peak = max((float) ($row['session_peak_position'] ?? 0), $position);
    $required = $duration * RECORDED_SESSION_COMPLETION_RATIO;

    if ($peak < $required) {
        return [
            'ok'              => false,
            'completed_views' => $stats['completed_views'],
            'remaining'       => $stats['remaining'],
            'counted'         => false,
            'message'         => 'View not counted — watch the full video to completion.',
        ];
    }

    db()->execute(
        'INSERT INTO recorded_session_watch_stats (user_id, session_id, completed_views, session_peak_position)
         VALUES (?, ?, 1, 0)
         ON DUPLICATE KEY UPDATE
            completed_views = completed_views + 1,
            session_peak_position = 0,
            updated_at = NOW()',
        [$userId, $sessionId]
    );

    $stats = recordedSessionGetWatchStats($userId, $sessionId);
    return [
        'ok'              => true,
        'completed_views' => $stats['completed_views'],
        'remaining'       => $stats['remaining'],
        'counted'         => true,
        'message'         => 'Full view recorded (' . $stats['completed_views'] . ' of ' . RECORDED_SESSION_MAX_WATCHES . ').',
    ];
}

function recordedSessionStreamUrl(int $sessionId): string
{
    return url('api/recorded-session-stream.php?id=' . $sessionId);
}

function recordedSessionWatchApiUrl(): string
{
    return url('api/recorded-session-watch.php');
}

function recordedSessionLinkedProgramLabel(array $session): string
{
    $linkType = (string) ($session['link_type'] ?? '');
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        return (string) ($session['webinar_title'] ?? 'Webinar');
    }
    if ($linkType === RECORDED_SESSION_LINK_BOOTCAMP) {
        return (string) ($session['bootcamp_title'] ?? 'Bootcamp');
    }
    return '—';
}

function recordedSessionLinkedProgramTypeLabel(array $session): string
{
    $linkType = (string) ($session['link_type'] ?? '');
    return recordedSessionLinkTypeOptions()[$linkType] ?? ucfirst($linkType);
}

/** Whether the session row is tied to a webinar or bootcamp. */
function recordedSessionIsLinkedToProgram(array $session): bool
{
    $linkType = (string) ($session['link_type'] ?? '');
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        return (int) ($session['webinar_id'] ?? 0) > 0;
    }
    if ($linkType === RECORDED_SESSION_LINK_BOOTCAMP) {
        return (int) ($session['bootcamp_id'] ?? 0) > 0;
    }
    return false;
}

/** Whether save payload includes a program link. */
function recordedSessionDataIsLinked(array $data): bool
{
    $linkType = (string) ($data['link_type'] ?? '');
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        return !empty($data['webinar_id']);
    }
    if ($linkType === RECORDED_SESSION_LINK_BOOTCAMP) {
        return !empty($data['bootcamp_id']);
    }
    return false;
}

function recordedSessionHasVideoFile(array $session): bool
{
    return trim((string) ($session['video_path'] ?? '')) !== '';
}

/**
 * Active enrollments for the linked program (within access window).
 */
function recordedSessionCountActiveEnrolledUsers(array $session): int
{
    if (!recordedSessionIsLinkedToProgram($session)) {
        return 0;
    }
    $months = COURSE_ACCESS_MONTHS;
    $linkType = (string) ($session['link_type'] ?? '');
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        return (int) (db()->fetchOne(
            "SELECT COUNT(DISTINCT user_id) AS c FROM webinar_registrations
             WHERE webinar_id = ? AND payment_status IN ('paid','free')
               AND registered_at > DATE_SUB(NOW(), INTERVAL ? MONTH)",
            [(int) $session['webinar_id'], $months]
        )['c'] ?? 0);
    }
    return (int) (db()->fetchOne(
        "SELECT COUNT(DISTINCT user_id) AS c FROM bootcamp_enrollments
         WHERE bootcamp_id = ? AND payment_status = 'paid'
           AND enrolled_at > DATE_SUB(NOW(), INTERVAL ? MONTH)",
        [(int) $session['bootcamp_id'], $months]
    )['c'] ?? 0);
}

/**
 * When a video is saved and linked to a course, publish so My Courses updates immediately.
 */
function recordedSessionResolveStatusOnSave(string $requestedStatus, bool $hasVideo, array $data): string
{
    if (!in_array($requestedStatus, recordedSessionStatusOptions(), true)) {
        $requestedStatus = RECORDED_SESSION_STATUS_PUBLISHED;
    }
    if ($hasVideo && recordedSessionDataIsLinked($data)) {
        if ($requestedStatus === RECORDED_SESSION_STATUS_DRAFT) {
            return RECORDED_SESSION_STATUS_DRAFT;
        }
        return RECORDED_SESSION_STATUS_PUBLISHED;
    }
    return $requestedStatus;
}

function recordedSessionPublishedSaveMessage(array $session): string
{
    $base = 'Recorded session saved successfully!';
    if (($session['status'] ?? '') !== RECORDED_SESSION_STATUS_PUBLISHED || !recordedSessionHasVideoFile($session)) {
        return $base;
    }
    if (!recordedSessionIsLinkedToProgram($session)) {
        return $base . ' Link a webinar or bootcamp to show it in My Courses.';
    }
    $count = recordedSessionCountActiveEnrolledUsers($session);
    return $base . ' It is now visible in My Courses for ' . $count . ' enrolled user' . ($count === 1 ? '' : 's') . '.';
}

function recordedSessionFormatExpiryLabel(?string $expiresAt): string
{
    if ($expiresAt === null || trim($expiresAt) === '') {
        return '';
    }
    $ts = strtotime($expiresAt);
    if ($ts === false) {
        return '';
    }
    return date('d M Y', $ts);
}

/**
 * @param array<string, mixed> $post
 * @return array{ok: bool, data: array<string, mixed>, error: string}
 */
function recordedSessionBuildPayloadFromPost(array $post): array
{
    $title = trim((string) ($post['title'] ?? ''));
    $description = trim((string) ($post['description'] ?? ''));
    $linkType = strtolower(trim((string) ($post['link_type'] ?? '')));
    $webinarId = (int) ($post['webinar_id'] ?? 0);
    $bootcampId = (int) ($post['bootcamp_id'] ?? 0);
    $status = trim((string) ($post['status'] ?? RECORDED_SESSION_STATUS_PUBLISHED));
    $sortOrder = (int) ($post['sort_order'] ?? 0);

    if (strlen($title) < 3) {
        return ['ok' => false, 'data' => [], 'error' => 'Title is required (minimum 3 characters).'];
    }
    if (!array_key_exists($linkType, recordedSessionLinkTypeOptions())) {
        return ['ok' => false, 'data' => [], 'error' => 'Please select whether this recording is linked to a webinar or bootcamp.'];
    }
    if ($linkType === RECORDED_SESSION_LINK_WEBINAR) {
        if ($webinarId < 1 || !db()->fetchOne('SELECT id FROM webinars WHERE id = ?', [$webinarId])) {
            return ['ok' => false, 'data' => [], 'error' => 'Please select a valid webinar.'];
        }
        $bootcampId = 0;
    } else {
        if ($bootcampId < 1 || !db()->fetchOne('SELECT id FROM bootcamps WHERE id = ?', [$bootcampId])) {
            return ['ok' => false, 'data' => [], 'error' => 'Please select a valid bootcamp.'];
        }
        $webinarId = 0;
    }
    if (!in_array($status, recordedSessionStatusOptions(), true)) {
        $status = RECORDED_SESSION_STATUS_DRAFT;
    }

    $slug = slugify($title) ?: 'recorded-session';

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'title'       => sanitize($title),
            'slug'        => $slug,
            'description' => sanitize($description),
            'link_type'   => $linkType,
            'webinar_id'  => $webinarId > 0 ? $webinarId : null,
            'bootcamp_id' => $bootcampId > 0 ? $bootcampId : null,
            'status'      => $status,
            'sort_order'  => $sortOrder,
        ],
    ];
}

function recordedSessionEnsureUniqueSlug(string $slug, int $excludeId = 0): string
{
    $candidate = $slug;
    $suffix = 1;
    while (true) {
        $params = [$candidate];
        $sql = 'SELECT id FROM recorded_sessions WHERE slug = ?';
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        if (!db()->fetchOne($sql, $params)) {
            return $candidate;
        }
        $candidate = $slug . '-' . $suffix;
        $suffix++;
    }
}

function recordedSessionRenderVideoPlayer(int $sessionId, array $opts = []): void
{
    $streamUrl = recordedSessionStreamUrl($sessionId);
    $watchUrl = recordedSessionWatchApiUrl();
    $csrf = generateCSRF();
    $trackWatch = !empty($opts['track_watch']);
    $stats = $opts['watch_stats'] ?? null;
    $mime = (string) ($opts['mime'] ?? 'video/mp4');
    $class = trim('recorded-session-player w-100 ' . (string) ($opts['class'] ?? ''));
    ?>
    <div class="recorded-session-player-wrap" data-session-id="<?= (int) $sessionId ?>">
      <?php if ($trackWatch && is_array($stats)): ?>
      <p class="recorded-watch-meta" style="font-size:.82rem;color:var(--cyber-muted);margin-bottom:.75rem">
        <i class="fas fa-eye me-1"></i>
        Full views used: <strong style="color:var(--cyber-accent)"><?= (int) $stats['completed_views'] ?></strong>
        / <?= RECORDED_SESSION_MAX_WATCHES ?>
        <?php if ((int) $stats['remaining'] > 0): ?>
        · <?= (int) $stats['remaining'] ?> remaining
        <?php else: ?>
        · <span style="color:#ff8888">Limit reached</span>
        <?php endif; ?>
      </p>
      <?php endif; ?>
      <video id="recordedSessionVideo" class="<?= htmlspecialchars($class) ?>" controls controlsList="nodownload" preload="metadata" playsinline
             style="max-width:100%;border-radius:10px;background:#000;aspect-ratio:16/9"
             <?php if ($trackWatch): ?>data-track-watch="1" data-watch-url="<?= htmlspecialchars($watchUrl) ?>" data-csrf="<?= htmlspecialchars($csrf) ?>" data-session-id="<?= (int) $sessionId ?>"<?php endif; ?>>
        <source src="<?= htmlspecialchars($streamUrl) ?>" type="<?= htmlspecialchars($mime) ?>">
        Your browser does not support HTML5 video playback.
      </video>
      <?php if ($trackWatch): ?>
      <p id="recordedWatchNotice" class="password-hint mt-2" style="display:none"></p>
      <?php endif; ?>
    </div>
    <?php if ($trackWatch): ?>
    <script src="<?= url('assets/js/recorded-session-watch.js') ?>"></script>
    <?php endif; ?>
    <?php
}

function recordedSessionStreamFile(string $absolutePath, string $mime): never
{
    $size = filesize($absolutePath);
    if ($size === false || $size < 1) {
        http_response_code(404);
        exit('Video not found.');
    }

    $start = 0;
    $end = $size - 1;
    $length = $size;

    header('Content-Type: ' . ($mime !== '' ? $mime : 'video/mp4'));
    header('Accept-Ranges: bytes');
    header('Content-Disposition: inline; filename="recording"');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $matches)) {
        if ($matches[1] !== '') {
            $start = (int) $matches[1];
        }
        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        if ($end >= $size) {
            $end = $size - 1;
        }
        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }

    header('Content-Length: ' . $length);

    $fp = fopen($absolutePath, 'rb');
    if ($fp === false) {
        http_response_code(500);
        exit('Could not read video.');
    }
    fseek($fp, $start);

    $chunkSize = 1048576;
    $bytesSent = 0;
    while (!feof($fp) && $bytesSent < $length) {
        $read = min($chunkSize, $length - $bytesSent);
        $buffer = fread($fp, $read);
        if ($buffer === false) {
            break;
        }
        echo $buffer;
        $bytesSent += strlen($buffer);
        if (connection_aborted()) {
            break;
        }
    }
    fclose($fp);
    exit;
}

require_once __DIR__ . '/recorded-session-upload.php';
