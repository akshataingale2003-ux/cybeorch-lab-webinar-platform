<?php
declare(strict_types=1);

/**
 * Chunked upload handlers for large recorded session videos (up to 50 GB).
 */

function recordedSessionRequireAdminUpload(): int
{
    startSession();
    if (!isAdminLoggedIn()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Admin login required.']);
        exit;
    }
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function recordedSessionUploadJsonResponse(array $payload, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function recordedSessionCleanupExpiredUploadJobs(): void
{
    ensureRecordedSessionsSchema();
    $rows = db()->fetchAll(
        "SELECT id, temp_path FROM recorded_session_upload_jobs
         WHERE status IN ('pending','failed') AND expires_at < NOW()"
    );
    foreach ($rows as $row) {
        $abs = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['temp_path']);
        if (is_file($abs)) {
            @unlink($abs);
        }
        db()->execute('DELETE FROM recorded_session_upload_jobs WHERE id = ?', [(int) $row['id']]);
    }
}

/**
 * @return array{ok: bool, upload_token: string, chunk_size: int, error: string}
 */
function recordedSessionUploadInit(int $adminId, string $originalName, int $totalSize): array
{
    ensureRecordedSessionsSchema();
    recordedSessionCleanupExpiredUploadJobs();

    $fail = static fn (string $msg): array => ['ok' => false, 'upload_token' => '', 'chunk_size' => RECORDED_SESSION_CHUNK_BYTES, 'error' => $msg];

    $originalName = trim($originalName);
    if ($originalName === '') {
        return $fail('File name is required.');
    }
    if ($totalSize <= 0) {
        return $fail('Invalid file size.');
    }
    if ($totalSize > RECORDED_SESSION_MAX_BYTES) {
        return $fail('Video must be under ' . recordedSessionMaxBytesLabel() . '.');
    }

    $ext = recordedSessionValidateExtension($originalName);
    if ($ext === null) {
        return $fail('Allowed formats: MP4, MKV, WEBM, MOV, AVI, M4V.');
    }

    ensureRecordedSessionUploadsDir();
    $token = bin2hex(random_bytes(24));
    $tempFile = 'upload-' . $token . '.part';
    $tempRel = 'uploads/recorded-sessions/.tmp/' . $tempFile;
    $tempAbs = recordedSessionUploadTempDir() . DIRECTORY_SEPARATOR . $tempFile;

    if (@file_put_contents($tempAbs, '') === false) {
        return $fail('Could not create upload workspace. Check folder permissions.');
    }

    db()->execute(
        'INSERT INTO recorded_session_upload_jobs
         (upload_token, admin_id, original_name, temp_path, file_ext, total_size, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 48 HOUR))',
        [$token, $adminId, $originalName, $tempRel, $ext, $totalSize]
    );

    return [
        'ok'           => true,
        'upload_token' => $token,
        'chunk_size'   => RECORDED_SESSION_CHUNK_BYTES,
        'error'        => '',
    ];
}

/**
 * @return array{ok: bool, received_bytes: int, total_size: int, complete: bool, error: string}
 */
function recordedSessionUploadAppendChunk(int $adminId, string $token, int $offset, array $chunkFile): array
{
    ensureRecordedSessionsSchema();
    $fail = static fn (string $msg): array => ['ok' => false, 'received_bytes' => 0, 'total_size' => 0, 'complete' => false, 'error' => $msg];

    $job = db()->fetchOne(
        "SELECT * FROM recorded_session_upload_jobs WHERE upload_token = ? AND admin_id = ? AND status = 'pending'",
        [$token, $adminId]
    );
    if (!$job) {
        return $fail('Upload session not found or expired.');
    }

    $errorCode = (int) ($chunkFile['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return $fail(recordedSessionUploadErrorMessage($errorCode));
    }

    $tmp = (string) ($chunkFile['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid chunk upload.');
    }

    $chunkSize = (int) ($chunkFile['size'] ?? 0);
    if ($chunkSize <= 0) {
        return $fail('Empty chunk received.');
    }
    if ($chunkSize > RECORDED_SESSION_CHUNK_BYTES + 1048576) {
        return $fail('Chunk too large.');
    }

    $totalSize = (int) $job['total_size'];
    if ($offset < 0 || $offset > $totalSize) {
        return $fail('Invalid upload offset.');
    }
    if ($offset + $chunkSize > $totalSize) {
        return $fail('Chunk exceeds declared file size.');
    }

    $tempAbs = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $job['temp_path']);
    $fp = fopen($tempAbs, 'c+b');
    if ($fp === false) {
        return $fail('Could not open upload file.');
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return $fail('Upload lock failed. Retry.');
    }

    fseek($fp, $offset);
    $in = fopen($tmp, 'rb');
    if ($in === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return $fail('Could not read chunk.');
    }
    stream_copy_to_stream($in, $fp);
    fclose($in);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    clearstatcache(true, $tempAbs);
    $received = (int) filesize($tempAbs);
    $complete = $received >= $totalSize;
    db()->execute(
        'UPDATE recorded_session_upload_jobs SET received_bytes = ?, status = ? WHERE id = ?',
        [$received, $complete ? 'complete' : 'pending', (int) $job['id']]
    );

    return [
        'ok'             => true,
        'received_bytes' => $received,
        'total_size'     => $totalSize,
        'complete'       => $complete,
        'error'          => '',
    ];
}

/**
 * @return array{ok: bool, path: string, original_name: string, mime: string, size: int, upload_token: string, error: string}
 */
function recordedSessionUploadFinalize(int $adminId, string $token): array
{
    ensureRecordedSessionsSchema();
    $fail = static fn (string $msg): array => [
        'ok' => false, 'path' => '', 'original_name' => '', 'mime' => '', 'size' => 0, 'upload_token' => '', 'error' => $msg,
    ];

    $job = db()->fetchOne(
        "SELECT * FROM recorded_session_upload_jobs WHERE upload_token = ? AND admin_id = ? AND status IN ('complete','pending')",
        [$token, $adminId]
    );
    if (!$job) {
        return $fail('Upload not ready or not found.');
    }

    $totalSize = (int) $job['total_size'];
    $received = (int) $job['received_bytes'];
    $tempAbs = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $job['temp_path']);

    if (!is_file($tempAbs)) {
        return $fail('Upload file missing.');
    }

    $actualSize = (int) filesize($tempAbs);
    if ($actualSize !== $totalSize || $received < $totalSize) {
        return $fail('Upload incomplete. Received ' . recordedSessionFormatBytes($actualSize) . ' of ' . recordedSessionFormatBytes($totalSize) . '.');
    }

    $ext = (string) $job['file_ext'];
    $mime = recordedSessionDetectMime($tempAbs, $ext);
    $base = slugify(pathinfo((string) $job['original_name'], PATHINFO_FILENAME)) ?: 'session';
    $stored = $base . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $finalRel = 'uploads/recorded-sessions/' . $stored;
    $finalAbs = recordedSessionUploadsDir() . DIRECTORY_SEPARATOR . $stored;

    if (!rename($tempAbs, $finalAbs)) {
        return $fail('Could not finalize upload file.');
    }

    db()->execute(
        "UPDATE recorded_session_upload_jobs SET status = 'complete', final_path = ?, mime = ?, received_bytes = ? WHERE id = ?",
        [$finalRel, $mime, $totalSize, (int) $job['id']]
    );

    return [
        'ok'            => true,
        'path'          => $finalRel,
        'original_name' => (string) $job['original_name'],
        'mime'          => $mime,
        'size'          => $totalSize,
        'upload_token'  => $token,
        'error'         => '',
    ];
}

/**
 * @return array{ok: bool, path: string, original_name: string, mime: string, size: int, error: string}
 */
function recordedSessionClaimUploadToken(int $adminId, string $token): array
{
    ensureRecordedSessionsSchema();
    $fail = static fn (string $msg): array => [
        'ok' => false, 'path' => '', 'original_name' => '', 'mime' => '', 'size' => 0, 'error' => $msg,
    ];

    $token = trim($token);
    if ($token === '') {
        return $fail('No upload token provided.');
    }

    $job = db()->fetchOne(
        "SELECT * FROM recorded_session_upload_jobs WHERE upload_token = ? AND admin_id = ? AND status = 'complete' AND final_path IS NOT NULL",
        [$token, $adminId]
    );
    if (!$job) {
        $finalized = recordedSessionUploadFinalize($adminId, $token);
        if (!$finalized['ok']) {
            return $fail($finalized['error']);
        }
        $job = db()->fetchOne(
            "SELECT * FROM recorded_session_upload_jobs WHERE upload_token = ? AND admin_id = ?",
            [$token, $adminId]
        );
    }

    if (!$job || empty($job['final_path'])) {
        return $fail('Upload could not be attached.');
    }

    db()->execute(
        "UPDATE recorded_session_upload_jobs SET status = 'claimed' WHERE id = ?",
        [(int) $job['id']]
    );

    return [
        'ok'            => true,
        'path'          => (string) $job['final_path'],
        'original_name' => (string) $job['original_name'],
        'mime'          => (string) ($job['mime'] ?? 'video/mp4'),
        'size'          => (int) $job['total_size'],
        'error'         => '',
    ];
}

function recordedSessionUploadCancel(int $adminId, string $token): bool
{
    $job = db()->fetchOne(
        'SELECT * FROM recorded_session_upload_jobs WHERE upload_token = ? AND admin_id = ?',
        [$token, $adminId]
    );
    if (!$job) {
        return false;
    }
    $tempAbs = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $job['temp_path']);
    if (is_file($tempAbs)) {
        @unlink($tempAbs);
    }
    if (!empty($job['final_path']) && ($job['status'] ?? '') !== 'claimed') {
        recordedSessionDeleteVideoFile((string) $job['final_path']);
    }
    db()->execute(
        "UPDATE recorded_session_upload_jobs SET status = 'cancelled' WHERE id = ?",
        [(int) $job['id']]
    );
    return true;
}

function recordedSessionHandleUploadApiRequest(): never
{
    $adminId = recordedSessionRequireAdminUpload();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        recordedSessionUploadJsonResponse(['ok' => false, 'error' => 'POST required.'], 405);
    }

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        recordedSessionUploadJsonResponse(['ok' => false, 'error' => 'Invalid CSRF token.'], 403);
    }

    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    switch ($action) {
        case 'init':
            $result = recordedSessionUploadInit(
                $adminId,
                (string) ($_POST['original_name'] ?? ''),
                (int) ($_POST['total_size'] ?? 0)
            );
            recordedSessionUploadJsonResponse($result, $result['ok'] ? 200 : 400);

        case 'chunk':
            $result = recordedSessionUploadAppendChunk(
                $adminId,
                trim((string) ($_POST['upload_token'] ?? '')),
                (int) ($_POST['offset'] ?? 0),
                $_FILES['chunk'] ?? []
            );
            recordedSessionUploadJsonResponse($result, $result['ok'] ? 200 : 400);

        case 'complete':
            $result = recordedSessionUploadFinalize(
                $adminId,
                trim((string) ($_POST['upload_token'] ?? ''))
            );
            recordedSessionUploadJsonResponse($result, $result['ok'] ? 200 : 400);

        case 'cancel':
            $ok = recordedSessionUploadCancel($adminId, trim((string) ($_POST['upload_token'] ?? '')));
            recordedSessionUploadJsonResponse(['ok' => $ok, 'error' => $ok ? '' : 'Upload not found.'], $ok ? 200 : 404);

        default:
            recordedSessionUploadJsonResponse(['ok' => false, 'error' => 'Unknown action.'], 400);
    }
}
