<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/** @return list<string> */
function resumeUploadAllowedExtensions(): array
{
    return ['pdf', 'doc', 'docx'];
}

/** @return array<string, list<string>> */
function resumeUploadAllowedMimeTypes(): array
{
    return [
        'pdf'  => ['application/pdf', 'application/x-pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-word'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
    ];
}

function resumeUploadsDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes';
}

function ensureResumeUploadsDir(): void
{
    $dir = resumeUploadsDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/**
 * Validate and store a Resume/CV upload.
 *
 * @param array<string, mixed> $file $_FILES['resume'] (or equivalent)
 * @return array{ok: bool, path: string, original_name: string, error: string}
 */
function validateAndStoreResumeUpload(array $file): array
{
    $fail = static fn (string $message): array => [
        'ok'            => false,
        'path'          => '',
        'original_name' => '',
        'error'         => $message,
    ];

    $name = trim((string) ($file['name'] ?? ''));
    if ($name === '') {
        return $fail('Please attach your Resume or CV (PDF, DOC, or DOCX).');
    }

    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return $fail('Please attach your Resume or CV (PDF, DOC, or DOCX).');
    }
    if ($errorCode !== UPLOAD_ERR_OK) {
        return $fail('Could not upload your Resume/CV. Please try again.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $fail('The uploaded Resume/CV file is empty.');
    }
    if ($size > MAX_UPLOAD_SIZE) {
        return $fail('Resume/CV must be under ' . (int) (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid Resume/CV upload. Please try again.');
    }

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, resumeUploadAllowedExtensions(), true)) {
        return $fail('Resume/CV must be a PDF, DOC, or DOCX file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = (string) $finfo->file($tmp);
    $allowedMimes = resumeUploadAllowedMimeTypes()[$ext] ?? [];
    $mimeOk = $allowedMimes !== [] && in_array($detectedMime, $allowedMimes, true);
    if (!$mimeOk && in_array($ext, ['doc', 'docx'], true) && $detectedMime === 'application/octet-stream') {
        $mimeOk = true;
    }
    if (!$mimeOk) {
        return $fail('Resume/CV file type is not allowed. Upload PDF, DOC, or DOCX only.');
    }

    ensureResumeUploadsDir();
    $safeBase = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($name, PATHINFO_FILENAME)) ?: 'resume';
    $storedName = $safeBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destAbs = resumeUploadsDir() . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmp, $destAbs)) {
        return $fail('Could not save your Resume/CV. Please try again.');
    }

    return [
        'ok'            => true,
        'path'          => 'uploads/resumes/' . $storedName,
        'original_name' => $name,
        'error'         => '',
    ];
}

function resumePublicUrl(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $parts = array_map('rawurlencode', explode('/', $relativePath));

    return url(implode('/', $parts));
}

function renderAdminResumeLink(?string $path, ?string $originalName = null): void
{
    $path = trim((string) $path);
    if ($path === '') {
        echo '<span style="color:var(--cyber-muted)">—</span>';
        return;
    }

    $label = trim((string) $originalName) !== '' ? (string) $originalName : basename($path);
    $href = resumePublicUrl($path);
    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="btn-sm-link" target="_blank" rel="noopener">';
    echo '<i class="fas fa-file-alt me-1"></i>' . htmlspecialchars($label);
    echo '</a>';
}
