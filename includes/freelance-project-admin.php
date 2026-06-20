<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/catalog-content.php';

/** @return list<string> */
function freelanceAdminValidStatuses(): array
{
    return ['Active', 'Inactive'];
}

function freelanceAdminNormalizeStatus(string $status): string
{
    $status = trim($status);
    if (strcasecmp($status, 'inactive') === 0 || in_array(strtolower($status), ['closed', 'filled', 'paused'], true)) {
        return 'Inactive';
    }
    if (strcasecmp($status, 'active') === 0 || in_array(strtolower($status), ['open', 'closing soon'], true)) {
        return 'Active';
    }

    return in_array($status, freelanceAdminValidStatuses(), true) ? $status : 'Active';
}

function freelanceAdminUploadsDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'freelance-projects';
}

function freelanceAdminEnsureUploadsDir(): void
{
    $dir = freelanceAdminUploadsDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/**
 * @param array<string, mixed> $file
 * @return array{ok: bool, path: string, error: string}
 */
function freelanceAdminStoreThumbnail(array $file): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'path' => '', 'error' => $message];

    $name = trim((string) ($file['name'] ?? ''));
    if ($name === '') {
        return $fail('Please choose an image file to upload.');
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $fail('No image file was uploaded.');
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        return $fail('Could not upload the image. Please try again.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $fail('The uploaded image is empty.');
    }
    if ($size > MAX_UPLOAD_SIZE) {
        return $fail('Image must be under ' . (int) (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid image upload.');
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return $fail('Image must be JPG, PNG, WEBP, or GIF.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        return $fail('Image file type is not allowed.');
    }

    freelanceAdminEnsureUploadsDir();
    $safeBase = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'freelance-project';
    $storedName = $safeBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destAbs = freelanceAdminUploadsDir() . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmp, $destAbs)) {
        return $fail('Could not save the image.');
    }

    return ['ok' => true, 'path' => 'uploads/freelance-projects/' . $storedName, 'error' => ''];
}

/**
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 * @param array<string, mixed>|null $existing
 * @return array{ok: bool, data: array<string, mixed>, error: string}
 */
function freelanceAdminBuildPayload(array $post, array $files, int $editId, ?array $existing = null): array
{
    $fail = static fn (string $message): array => ['ok' => false, 'data' => [], 'error' => $message];

    $title = sanitizePlainText((string) ($post['title'] ?? ''));
    if (strlen($title) < 3) {
        return $fail('Project title must be at least 3 characters.');
    }

    $imagePath = trim((string) ($existing['image_path'] ?? ''));
    if (!empty($post['clear_thumbnail'])) {
        $imagePath = '';
    }
    if (!empty($files['thumbnail']['name'])) {
        $upload = freelanceAdminStoreThumbnail($files['thumbnail']);
        if (!$upload['ok']) {
            return $fail($upload['error']);
        }
        $imagePath = $upload['path'];
    }

    $slug = '';
    if ($editId > 0 && $existing) {
        $slug = trim((string) ($existing['slug'] ?? ''));
        if ($slug === '') {
            $slug = slugify($title);
        }
    } else {
        $slug = slugify($title);
    }

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'title'        => $title,
            'slug'         => $slug,
            'short_desc'   => sanitizePlainText((string) ($post['short_desc'] ?? '')),
            'description'  => sanitizePlainText((string) ($post['description'] ?? '')),
            'category'     => sanitizePlainText((string) ($post['category'] ?? '')),
            'skills'       => sanitizePlainText((string) ($post['skills'] ?? '')),
            'duration'     => sanitizePlainText((string) ($post['duration'] ?? '')),
            'mode'         => sanitizePlainText((string) ($post['mode'] ?? 'Remote')),
            'budget_label' => sanitizePlainText((string) ($post['budget_label'] ?? '')),
            'client_name'  => sanitizePlainText((string) ($post['client_name'] ?? '')),
            'image_path'   => $imagePath,
            'status'           => freelanceAdminNormalizeStatus((string) ($post['status'] ?? 'Active')),
            'apply_route'      => sanitizePlainText((string) ($post['apply_route'] ?? 'apply-freelance.php')),
            'project_type'     => CATALOG_PROJECT_TYPE_FREELANCER,
            'show_on_homepage' => catalogParseShowOnHomepage($post['show_on_homepage'] ?? null),
            'sort_order'       => (int) ($post['sort_order'] ?? 0),
        ],
    ];
}

function freelanceAdminEnsureUniqueSlug(string $slug, int $excludeId = 0): string
{
    $base = $slug !== '' ? $slug : 'freelance-project';
    $candidate = $base;
    $suffix = 1;

    while (true) {
        $params = [$candidate];
        $sql = 'SELECT id FROM freelance_projects WHERE slug = ?';
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
