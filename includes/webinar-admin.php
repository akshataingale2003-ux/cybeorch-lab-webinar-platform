<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

/** @return list<string> */
function webinarAdminValidStatuses(): array
{
    return ['upcoming', 'live', 'completed', 'cancelled'];
}

/** Default status for new admin webinars (Live Webinars section). */
function webinarAdminDefaultStatus(int $editId, ?array $existing = null): string
{
    if ($editId > 0 && is_array($existing)) {
        $current = strtolower(trim((string) ($existing['status'] ?? '')));
        if (in_array($current, webinarAdminValidStatuses(), true)) {
            return $current;
        }
    }

    return 'live';
}

/** @return list<string> */
function webinarAdminValidPlatforms(): array
{
    return ['zoom', 'google_meet', 'teams', 'other'];
}

/** Trim and decode legacy HTML entities before storing webinar text. */
function webinarSanitizeStoredText($input): string
{
    return sanitizeStoredText($input);
}

function webinarAdminNormalizeDatetime(string $value): ?string
{
    $value = trim(str_replace('T', ' ', $value));
    if ($value === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

function webinarAdminEnsureUniqueSlug(string $slug, int $excludeId = 0): string
{
    $base = $slug !== '' ? $slug : 'webinar';
    $candidate = $base;
    $suffix = 1;

    while (true) {
        $params = [$candidate];
        $sql = 'SELECT id FROM webinars WHERE slug = ?';
        if ($excludeId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $exists = db()->fetchOne($sql, $params);
        if (!$exists) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

function webinarAdminUploadsDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'webinars';
}

function webinarAdminEnsureUploadsDir(): void
{
    $dir = webinarAdminUploadsDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/**
 * @param array<string, mixed> $file
 * @return array{ok: bool, path: string, error: string}
 */
function webinarAdminStoreThumbnail(array $file): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'path' => '', 'error' => $message];

    $name = trim((string) ($file['name'] ?? ''));
    if ($name === '') {
        return $fail('Please choose an image file to upload.');
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $fail('No thumbnail file was uploaded.');
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        return $fail('Could not upload the thumbnail image. Please try again.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $fail('The uploaded image is empty.');
    }
    if ($size > MAX_UPLOAD_SIZE) {
        return $fail('Thumbnail must be under ' . (int) (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid thumbnail upload.');
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return $fail('Thumbnail must be JPG, PNG, WEBP, or GIF.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        return $fail('Thumbnail file type is not allowed.');
    }

    webinarAdminEnsureUploadsDir();
    $safeBase = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'webinar';
    $storedName = $safeBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destAbs = webinarAdminUploadsDir() . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmp, $destAbs)) {
        return $fail('Could not save the thumbnail image.');
    }

    return ['ok' => true, 'path' => 'uploads/webinars/' . $storedName, 'error' => ''];
}

/**
 * Build validated webinar row for insert/update.
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 * @param array<string, mixed>|null $existing
 * @return array{ok: bool, data: array<string, mixed>, error: string}
 */
function webinarAdminBuildPayload(array $post, array $files, int $editId, ?array $existing = null): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'data' => [], 'error' => $message];

    $title = webinarSanitizeStoredText($post['title'] ?? '');
    if (strlen($title) < 3) {
        return $fail('Webinar title must be at least 3 characters.');
    }

    $scheduledAt = webinarAdminNormalizeDatetime((string) ($post['scheduled_at'] ?? ''));
    if ($scheduledAt === null) {
        return $fail('Please enter a valid scheduled date and time.');
    }

    $registrationCloses = trim((string) ($post['registration_closes_at'] ?? ''));
    $registrationClosesAt = $registrationCloses !== '' ? webinarAdminNormalizeDatetime($registrationCloses) : null;
    if ($registrationCloses !== '' && $registrationClosesAt === null) {
        return $fail('Registration close date is not valid.');
    }

    $defaultStatus = webinarAdminDefaultStatus($editId, $existing);
    $postedStatus = trim((string) ($post['status'] ?? ''));
    $status = strtolower(sanitizePlainText($postedStatus !== '' ? $postedStatus : $defaultStatus));
    if (!in_array($status, webinarAdminValidStatuses(), true)) {
        $status = $defaultStatus;
    }

    $platform = sanitizePlainText((string) ($post['meeting_platform'] ?? 'zoom'));
    if (!in_array($platform, webinarAdminValidPlatforms(), true)) {
        $platform = 'zoom';
    }

    $duration = (int) ($post['duration_mins'] ?? 60);
    if ($duration < 15) {
        $duration = 15;
    } elseif ($duration > 480) {
        $duration = 480;
    }

    $maxSeats = max(1, (int) ($post['max_seats'] ?? 100));
    $isFree = !empty($post['is_free']) ? 1 : 0;
    $fee = $isFree ? 0.0 : max(0.0, (float) ($post['fee'] ?? 0));
    if (!$isFree && $fee <= 0) {
        return $fail('Please enter a fee greater than 0 for paid webinars, or mark the webinar as free.');
    }

    $meetingLink = trim((string) ($post['meeting_link'] ?? ''));
    if ($meetingLink !== '' && !filter_var($meetingLink, FILTER_VALIDATE_URL)) {
        return $fail('Please enter a valid meeting link URL.');
    }

    $description = webinarSanitizeStoredText($post['description'] ?? '');
    $shortDesc = webinarSanitizeStoredText($post['short_desc'] ?? '');
    if ($shortDesc === '' && $description !== '') {
        $shortDesc = mb_substr($description, 0, 500);
    }

    if ($editId > 0 && $existing) {
        $slug = trim((string) ($existing['slug'] ?? ''));
        if ($slug === '') {
            $slug = slugify($title);
        }
    } else {
        $slug = webinarAdminEnsureUniqueSlug(slugify($title));
    }

    $thumbnail = trim((string) ($existing['thumbnail'] ?? ''));
    if (!empty($files['thumbnail']['name'])) {
        $upload = webinarAdminStoreThumbnail($files['thumbnail']);
        if (!$upload['ok']) {
            return $fail($upload['error']);
        }
        $thumbnail = $upload['path'];
    } elseif ($thumbnail === '') {
        require_once __DIR__ . '/training-public.php';
        $thumbnail = webinarThumbPathForWebinar([
            'title'     => $title,
            'status'    => $status,
            'thumbnail' => '',
        ]);
    }

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'title'                  => $title,
            'slug'                   => $slug,
            'description'            => $description,
            'short_desc'             => $shortDesc,
            'instructor'             => webinarSanitizeStoredText($post['instructor'] ?? ''),
            'category'               => webinarSanitizeStoredText($post['category'] ?? ''),
            'tags'                   => webinarSanitizeStoredText($post['tags'] ?? ''),
            'scheduled_at'           => $scheduledAt,
            'registration_closes_at' => $registrationClosesAt,
            'closing_soon_enabled'   => !empty($post['closing_soon_enabled']) ? 1 : 0,
            'duration_mins'          => $duration,
            'max_seats'              => $maxSeats,
            'fee'                    => $fee,
            'is_free'                => $isFree,
            'meeting_link'           => $meetingLink !== '' ? webinarSanitizeStoredText($meetingLink) : '',
            'meeting_platform'       => $platform,
            'status'                 => $status,
            'thumbnail'              => $thumbnail,
        ],
    ];
}

/**
 * @param array<string, mixed> $data
 */
function webinarAdminInsert(array $data): int
{
    return db()->insert(
        'INSERT INTO webinars (title, slug, description, short_desc, instructor, category, tags, scheduled_at, registration_closes_at, closing_soon_enabled, duration_mins, max_seats, fee, is_free, meeting_link, meeting_platform, status, thumbnail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $data['title'],
            $data['slug'],
            $data['description'],
            $data['short_desc'],
            $data['instructor'],
            $data['category'],
            $data['tags'],
            $data['scheduled_at'],
            $data['registration_closes_at'],
            $data['closing_soon_enabled'],
            $data['duration_mins'],
            $data['max_seats'],
            $data['fee'],
            $data['is_free'],
            $data['meeting_link'],
            $data['meeting_platform'],
            $data['status'],
            $data['thumbnail'],
        ]
    );
}

/**
 * @param array<string, mixed> $data
 */
function webinarAdminUpdate(int $id, array $data): void
{
    db()->execute(
        'UPDATE webinars SET title=?, slug=?, description=?, short_desc=?, instructor=?, category=?, tags=?, scheduled_at=?, registration_closes_at=?, closing_soon_enabled=?, duration_mins=?, max_seats=?, fee=?, is_free=?, meeting_link=?, meeting_platform=?, status=?, thumbnail=?, updated_at=NOW() WHERE id=?',
        [
            $data['title'],
            $data['slug'],
            $data['description'],
            $data['short_desc'],
            $data['instructor'],
            $data['category'],
            $data['tags'],
            $data['scheduled_at'],
            $data['registration_closes_at'],
            $data['closing_soon_enabled'],
            $data['duration_mins'],
            $data['max_seats'],
            $data['fee'],
            $data['is_free'],
            $data['meeting_link'],
            $data['meeting_platform'],
            $data['status'],
            $data['thumbnail'],
            $id,
        ]
    );
}

/** @return list<string> */
function webinarAdminTextFields(): array
{
    return ['title', 'description', 'short_desc', 'instructor', 'category', 'tags', 'meeting_link'];
}

/**
 * Decode legacy HTML entities in webinar text columns (one-time-safe, idempotent).
 *
 * @return int Number of rows updated
 */
function migrateWebinarEncodedText(): int
{
    static $ran = false;
    if ($ran) {
        return 0;
    }
    $ran = true;

    try {
        $fields = implode(', ', webinarAdminTextFields());
        $rows = db()->fetchAll("SELECT id, {$fields} FROM webinars");
    } catch (Throwable) {
        return 0;
    }

    $updated = 0;
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }

        $set = [];
        $params = [];
        foreach (webinarAdminTextFields() as $field) {
            $raw = (string) ($row[$field] ?? '');
            if ($raw === '' || !storedTextHasEncodedEntities($raw)) {
                continue;
            }
            $decoded = decodeStoredText($raw);
            if ($decoded !== $raw) {
                $set[] = "{$field} = ?";
                $params[] = $decoded;
            }
        }

        if ($set === []) {
            continue;
        }

        $params[] = $id;
        db()->execute('UPDATE webinars SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
        $updated++;
    }

    return $updated;
}

function ensureWebinarTextEncoding(): void
{
    migrateWebinarEncodedText();
}
