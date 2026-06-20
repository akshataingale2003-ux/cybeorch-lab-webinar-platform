<?php
declare(strict_types=1);

require_once __DIR__ . '/company-content.php';

/** Ensure team profile columns exist (uses company_team_members table). */
function ensureTeamProfileSchema(): void
{
    ensureCompanyContentSchema();

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (db()->fetchOne("SHOW COLUMNS FROM company_team_members LIKE 'degree'") === null) {
            db()->execute(
                'ALTER TABLE company_team_members ADD COLUMN degree VARCHAR(150) DEFAULT NULL AFTER role_title'
            );
        }
        teamProfileMigrateLegacyDegreeInRoleTitle();
    } catch (Throwable $e) {
        // DB offline
    }
}

/** Move qualification values stored in role_title into degree (pre-migration data). */
function teamProfileMigrateLegacyDegreeInRoleTitle(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $rows = db()->fetchAll(
            "SELECT id, role_title FROM company_team_members
             WHERE (degree IS NULL OR TRIM(degree) = '')
               AND role_title IS NOT NULL AND TRIM(role_title) != ''"
        );
        foreach ($rows as $row) {
            $role = trim((string) ($row['role_title']));
            if (!preg_match('/^(B\.?\s*Tech|M\.?\s*Tech|B\.?\s*E\.?|M\.?\s*E\.?|BCA|MCA|MBA|B\.?\s*Sc\.?|M\.?\s*Sc\.?|Ph\.?\s*D\.?)$/i', $role)) {
                continue;
            }
            db()->execute(
                'UPDATE company_team_members SET degree = ?, role_title = NULL WHERE id = ?',
                [$role, (int) $row['id']]
            );
        }
    } catch (Throwable $e) {
        // DB offline
    }
}

function teamProfileUploadDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'Team_Profile';
}

function teamProfileEnsureUploadDir(): void
{
    $dir = teamProfileUploadDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/** @return list<string> */
function teamProfileAllowedExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp'];
}

/**
 * @param array<string, mixed> $file
 * @return array{ok: bool, path: string, error: string}
 */
function teamProfileStorePhoto(array $file, string $memberName = ''): array
{
    teamProfileEnsureUploadDir();
    $fail = static fn (string $msg): array => ['ok' => false, 'path' => '', 'error' => $msg];

    $name = trim((string) ($file['name'] ?? ''));
    if ($name === '') {
        return $fail('Please choose a profile image to upload.');
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
    if (!in_array($ext, teamProfileAllowedExtensions(), true)) {
        return $fail('Allowed formats: JPG, JPEG, PNG, WEBP.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        return $fail('Image file type is not allowed.');
    }

    $safeBase = preg_replace('/[^a-z0-9._-]+/i', '_', trim($memberName)) ?: 'team-member';
    $safeBase = trim($safeBase, '_') ?: 'team-member';
    $storedName = $safeBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destAbs = teamProfileUploadDir() . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmp, $destAbs)) {
        return $fail('Could not save the profile image.');
    }

    return ['ok' => true, 'path' => 'assets/images/Team_Profile/' . $storedName, 'error' => ''];
}

/** @return list<array<string, mixed>> */
function teamProfileAdminFetchAll(): array
{
    ensureTeamProfileSchema();

    return dbTry(
        static fn () => db()->fetchAll(
            'SELECT * FROM company_team_members ORDER BY sort_order ASC, id ASC'
        ),
        []
    );
}

/** @return array<string, mixed>|null */
function teamProfileAdminFetchById(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    ensureTeamProfileSchema();
    $row = dbTry(static fn () => db()->fetchOne('SELECT * FROM company_team_members WHERE id = ?', [$id]), null);

    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 * @param array<string, mixed>|null $existing
 * @return array{ok: bool, error: string}
 */
function teamProfileAdminBuildPayload(array $post, array $files, int $editId, ?array $existing = null): array
{
    $fail = static fn (string $msg): array => ['ok' => false, 'error' => $msg];

    $name = sanitizePlainText((string) ($post['name'] ?? ''));
    if (strlen($name) < 2) {
        return $fail('Full name must be at least 2 characters.');
    }

    $designation = sanitizePlainText((string) ($post['designation'] ?? ''));
    $degree = sanitizePlainText((string) ($post['degree'] ?? ''));
    $bio = sanitizePlainText((string) ($post['bio'] ?? ''));

    $profileType = strtolower(trim((string) ($post['profile_type'] ?? 'team')));
    if (!in_array($profileType, ['founder', 'team'], true)) {
        $profileType = 'team';
    }

    $sortOrder = (int) ($post['sort_order'] ?? 0);
    $isActive = ((string) ($post['is_active'] ?? '1')) === '1' ? 1 : 0;

    $photoPath = trim((string) ($existing['photo_path'] ?? ''));
    if (!empty($files['photo']['name'])) {
        $upload = teamProfileStorePhoto($files['photo'], $name);
        if (!$upload['ok']) {
            return $fail($upload['error']);
        }
        $photoPath = $upload['path'];
    }

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'name'          => $name,
            'role_title'    => $designation,
            'degree'        => $degree,
            'bio'           => $bio,
            'profile_type'  => $profileType,
            'sort_order'    => $sortOrder,
            'is_active'     => $isActive,
            'photo_path'    => $photoPath,
        ],
    ];
}

/** @param array<string, mixed> $data */
function teamProfileAdminInsert(array $data): int
{
    return (int) db()->insert(
        'INSERT INTO company_team_members (name, role_title, degree, profile_type, bio, photo_path, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)',
        [
            $data['name'],
            $data['role_title'],
            $data['degree'],
            $data['profile_type'],
            $data['bio'],
            $data['photo_path'] !== '' ? $data['photo_path'] : null,
            $data['sort_order'],
            $data['is_active'],
        ]
    );
}

/** @param array<string, mixed> $data */
function teamProfileAdminUpdate(int $id, array $data): void
{
    db()->execute(
        'UPDATE company_team_members SET name=?, role_title=?, degree=?, profile_type=?, bio=?, photo_path=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?',
        [
            $data['name'],
            $data['role_title'],
            $data['degree'],
            $data['profile_type'],
            $data['bio'],
            $data['photo_path'] !== '' ? $data['photo_path'] : null,
            $data['sort_order'],
            $data['is_active'],
            $id,
        ]
    );
}

function teamProfileAdminDelete(int $id): bool
{
    if ($id < 1) {
        return false;
    }
    ensureTeamProfileSchema();
    db()->execute('DELETE FROM company_team_members WHERE id = ?', [$id]);

    return true;
}

function teamMemberDesignation(array $member): string
{
    return trim((string) ($member['role_title'] ?? ''));
}

function teamMemberDegree(array $member): string
{
    return trim((string) ($member['degree'] ?? ''));
}

function teamProfileStatusLabel(array $member): string
{
    return !empty($member['is_active']) ? 'Active' : 'Inactive';
}

function teamProfileSectionLabel(array $member): string
{
    return (($member['profile_type'] ?? 'team') === 'founder') ? 'Founder' : 'Team';
}

function teamProfileAdminPhotoUrl(?string $path): string
{
    return companyPublicImageUrl($path);
}
