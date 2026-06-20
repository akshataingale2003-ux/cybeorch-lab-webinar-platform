<?php
declare(strict_types=1);

require_once __DIR__ . '/certificate-template.php';
require_once __DIR__ . '/certificate-generator.php';

function ensureCertificatesSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        certificate_id VARCHAR(40) NOT NULL,
        user_id INT NOT NULL,
        event_type ENUM('webinar','bootcamp') NOT NULL,
        event_id INT NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        event_name VARCHAR(255) NOT NULL,
        event_date DATE DEFAULT NULL,
        issue_date DATE NOT NULL,
        qr_code VARCHAR(500) DEFAULT NULL,
        pdf_path VARCHAR(500) DEFAULT NULL,
        png_path VARCHAR(500) DEFAULT NULL,
        jpg_path VARCHAR(500) DEFAULT NULL,
        email_sent TINYINT(1) NOT NULL DEFAULT 0,
        email_sent_at DATETIME DEFAULT NULL,
        email_error VARCHAR(500) DEFAULT NULL,
        email_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
        status ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
        source_type ENUM('webinar_registration','bootcamp_enrollment') NOT NULL,
        source_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_certificates_cert_id (certificate_id),
        UNIQUE KEY uq_certificates_source (source_type, source_id),
        INDEX idx_certificates_user (user_id),
        INDEX idx_certificates_event (event_type, event_id),
        INDEX idx_certificates_issue (issue_date)
    )");

    db()->execute('CREATE TABLE IF NOT EXISTS certificate_id_sequences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year SMALLINT NOT NULL,
        month TINYINT NOT NULL,
        event_type VARCHAR(16) NOT NULL DEFAULT \'webinar\',
        last_seq INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_cert_seq_year_month_type (year, month, event_type)
    )');

    try {
        db()->execute('ALTER TABLE certificate_id_sequences ADD COLUMN event_type VARCHAR(16) NOT NULL DEFAULT \'webinar\'');
    } catch (Throwable $e) {
    }
    try {
        db()->execute('ALTER TABLE certificate_id_sequences DROP INDEX uq_cert_seq_year_month');
    } catch (Throwable $e) {
    }
    try {
        db()->execute('ALTER TABLE certificate_id_sequences ADD UNIQUE KEY uq_cert_seq_year_month_type (year, month, event_type)');
    } catch (Throwable $e) {
    }

    ensureBootcampCompletionColumns();
    ensureCertificateStorageDirs();

    try {
        db()->execute('ALTER TABLE certificates MODIFY certificate_id VARCHAR(40) NOT NULL');
    } catch (Throwable $e) {
    }

    ensureCertificateEmailTrackingColumns();
}

function ensureCertificateEmailTrackingColumns(): void
{
    try {
        db()->execute('ALTER TABLE certificates ADD COLUMN email_sent TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
    }
    try {
        db()->execute('ALTER TABLE certificates ADD COLUMN email_sent_at DATETIME DEFAULT NULL');
    } catch (Throwable $e) {
    }
    try {
        db()->execute('ALTER TABLE certificates ADD COLUMN email_error VARCHAR(500) DEFAULT NULL');
    } catch (Throwable $e) {
    }
    try {
        db()->execute("ALTER TABLE certificates ADD COLUMN email_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending'");
    } catch (Throwable $e) {
    }

    try {
        db()->execute(
            "UPDATE certificates SET email_status = 'sent'
             WHERE email_sent = 1 AND (email_status IS NULL OR email_status = '' OR email_status = 'pending')"
        );
    } catch (Throwable $e) {
    }
}

function certificateRecipientEmailValid(string $email): bool
{
    $email = trim($email);

    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** @return array{status:string,label:string,class:string,sent_at:?string,error:?string} */
function certificateEmailStatusMeta(array $cert): array
{
    $status = strtolower(trim((string) ($cert['email_status'] ?? '')));
    if (!in_array($status, ['pending', 'sent', 'failed'], true)) {
        $status = !empty($cert['email_sent']) ? 'sent' : 'pending';
    }

    return [
        'status'  => $status,
        'label'   => match ($status) {
            'sent'   => 'Email Sent Successfully',
            'failed' => 'Email Failed',
            default  => 'Email Pending',
        },
        'class'   => match ($status) {
            'sent'   => 'badge-paid',
            'failed' => 'badge-pending',
            default  => 'badge-pending',
        },
        'sent_at' => !empty($cert['email_sent_at']) ? (string) $cert['email_sent_at'] : null,
        'error'   => !empty($cert['email_error']) ? (string) $cert['email_error'] : null,
    ];
}

function markCertificateEmailPending(int $certificateDbId): void
{
    if ($certificateDbId <= 0) {
        return;
    }
    db()->execute(
        'UPDATE certificates SET email_status = ?, email_error = NULL WHERE id = ?',
        ['pending', $certificateDbId]
    );
}

function markCertificateEmailSent(int $certificateDbId): void
{
    if ($certificateDbId <= 0) {
        return;
    }
    db()->execute(
        'UPDATE certificates SET email_sent = 1, email_sent_at = NOW(), email_status = ?, email_error = NULL WHERE id = ?',
        ['sent', $certificateDbId]
    );
}

function markCertificateEmailFailed(int $certificateDbId, string $error): void
{
    if ($certificateDbId <= 0) {
        return;
    }
    $error = mb_substr(trim($error), 0, 500);
    db()->execute(
        'UPDATE certificates SET email_sent = 0, email_status = ?, email_error = ? WHERE id = ?',
        ['failed', $error !== '' ? $error : 'Email delivery failed', $certificateDbId]
    );
}

function ensureBootcampCompletionColumns(): void
{
    try {
        db()->execute('ALTER TABLE bootcamp_enrollments ADD COLUMN completed TINYINT(1) NOT NULL DEFAULT 0');
    } catch (Throwable $e) {
    }
    try {
        db()->execute('ALTER TABLE bootcamp_enrollments ADD COLUMN completed_at DATETIME DEFAULT NULL');
    } catch (Throwable $e) {
    }
}

function ensureCertificateStorageDirs(): void
{
    foreach (['', '/pdf', '/png', '/jpg', '/qr', '/.tmp'] as $sub) {
        $dir = certificateStorageRoot() . $sub;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
    $htaccess = certificateStorageRoot() . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps)$\">\n  Require all denied\n</FilesMatch>\n");
    }
}

function generateNextCertificateId(string $eventType = 'webinar', ?DateTimeInterface $when = null): string
{
    ensureCertificatesSchema();
    $when = $when ?? new DateTimeImmutable('now');
    $year = (int) $when->format('Y');
    $month = (int) $when->format('n');
    $typeKey = strtolower(trim($eventType)) === 'bootcamp' ? 'BOOT' : 'WEB';
    $seqType = strtolower(trim($eventType)) === 'bootcamp' ? 'bootcamp' : 'webinar';
    $monthLabel = strtoupper($when->format('M')) . $when->format('Y');
    $prefix = 'CYB-' . $typeKey . '-' . $monthLabel . '-';

    $maxRow = db()->fetchOne(
        'SELECT certificate_id FROM certificates WHERE certificate_id LIKE ? ORDER BY certificate_id DESC LIMIT 1',
        [$prefix . '%']
    );
    $maxFromCerts = 0;
    if (is_array($maxRow) && preg_match('/-(\d+)$/', (string) ($maxRow['certificate_id'] ?? ''), $matches)) {
        $maxFromCerts = (int) $matches[1];
    }

    db()->execute(
        'INSERT INTO certificate_id_sequences (year, month, event_type, last_seq) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE last_seq = GREATEST(last_seq, VALUES(last_seq))',
        [$year, $month, $seqType, max(1, $maxFromCerts + 1)]
    );

    db()->execute(
        'UPDATE certificate_id_sequences SET last_seq = last_seq + 1 WHERE year = ? AND month = ? AND event_type = ?',
        [$year, $month, $seqType]
    );

    $row = db()->fetchOne(
        'SELECT last_seq FROM certificate_id_sequences WHERE year = ? AND month = ? AND event_type = ?',
        [$year, $month, $seqType]
    );
    $num = max((int) ($row['last_seq'] ?? 1), $maxFromCerts + 1);

    $candidate = $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    while (findCertificateByPublicId($candidate) !== null) {
        $num++;
        $candidate = $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }

    db()->execute(
        'UPDATE certificate_id_sequences SET last_seq = ? WHERE year = ? AND month = ? AND event_type = ?',
        [$num, $year, $month, $seqType]
    );

    return $candidate;
}

/** @return array<string,mixed>|null */
function findCertificateByPublicId(string $certificateId): ?array
{
    ensureCertificatesSchema();
    $certificateId = trim($certificateId);
    if ($certificateId === '') {
        return null;
    }

    return db()->fetchOne('SELECT * FROM certificates WHERE certificate_id = ? LIMIT 1', [$certificateId]) ?: null;
}

/** @return array<string,mixed>|null */
function findCertificateBySource(string $sourceType, int $sourceId): ?array
{
    ensureCertificatesSchema();
    if ($sourceId <= 0) {
        return null;
    }

    return db()->fetchOne(
        'SELECT * FROM certificates WHERE source_type = ? AND source_id = ? LIMIT 1',
        [$sourceType, $sourceId]
    ) ?: null;
}

/** @return array<string,mixed>|null */
function findCertificateForUserEvent(int $userId, string $eventType, int $eventId): ?array
{
    ensureCertificatesSchema();

    return db()->fetchOne(
        'SELECT * FROM certificates WHERE user_id = ? AND event_type = ? AND event_id = ? LIMIT 1',
        [$userId, $eventType, $eventId]
    ) ?: null;
}

/**
 * Issue certificate when attendance/completion criteria are met.
 *
 * @return array{success:bool, message:string, certificate?:array<string,mixed>, email_sent?:bool}
 */
function issueCertificateForWebinarRegistration(int $registrationId, bool $sendEmail = true): array
{
    ensureCertificatesSchema();

    $reg = db()->fetchOne(
        'SELECT wr.*, u.full_name, u.email, w.title AS event_name, w.scheduled_at
         FROM webinar_registrations wr
         JOIN users u ON u.id = wr.user_id
         JOIN webinars w ON w.id = wr.webinar_id
         WHERE wr.id = ?',
        [$registrationId]
    );
    if (!$reg) {
        return ['success' => false, 'message' => 'Registration not found.'];
    }
    if (!(int) ($reg['attended'] ?? 0)) {
        return ['success' => false, 'message' => 'Certificate blocked: attendance not marked as Attended.'];
    }

    $eventDate = !empty($reg['scheduled_at']) ? date('Y-m-d', strtotime((string) $reg['scheduled_at'])) : date('Y-m-d');

    return createOrRefreshCertificate([
        'user_id'     => (int) $reg['user_id'],
        'user_name'   => (string) ($reg['full_name'] ?? 'Participant'),
        'user_email'  => (string) ($reg['email'] ?? ''),
        'event_type'  => 'webinar',
        'event_id'    => (int) $reg['webinar_id'],
        'event_name'  => (string) ($reg['event_name'] ?? 'Webinar'),
        'event_date'  => $eventDate,
        'source_type' => 'webinar_registration',
        'source_id'   => $registrationId,
    ], $sendEmail);
}

/**
 * @return array{success:bool, message:string, certificate?:array<string,mixed>, email_sent?:bool}
 */
function issueCertificateForBootcampEnrollment(int $enrollmentId, bool $sendEmail = true): array
{
    ensureCertificatesSchema();

    $enroll = db()->fetchOne(
        'SELECT be.*, u.full_name, u.email, b.title AS event_name, b.start_date, b.end_date
         FROM bootcamp_enrollments be
         JOIN users u ON u.id = be.user_id
         JOIN bootcamps b ON b.id = be.bootcamp_id
         WHERE be.id = ?',
        [$enrollmentId]
    );
    if (!$enroll) {
        return ['success' => false, 'message' => 'Bootcamp enrollment not found.'];
    }
    if (!(int) ($enroll['completed'] ?? 0)) {
        return ['success' => false, 'message' => 'Certificate blocked: bootcamp not marked as completed/attended.'];
    }

    $eventDate = !empty($enroll['end_date'])
        ? date('Y-m-d', strtotime((string) $enroll['end_date']))
        : (!empty($enroll['start_date']) ? date('Y-m-d', strtotime((string) $enroll['start_date'])) : date('Y-m-d'));

    return createOrRefreshCertificate([
        'user_id'     => (int) $enroll['user_id'],
        'user_name'   => (string) ($enroll['full_name'] ?? 'Participant'),
        'user_email'  => (string) ($enroll['email'] ?? ''),
        'event_type'  => 'bootcamp',
        'event_id'    => (int) $enroll['bootcamp_id'],
        'event_name'  => (string) ($enroll['event_name'] ?? 'Bootcamp'),
        'event_date'  => $eventDate,
        'source_type' => 'bootcamp_enrollment',
        'source_id'   => $enrollmentId,
    ], $sendEmail);
}

/**
 * Build certificate payload from the original registration/enrollment record (no manual fields).
 *
 * @return array{user_id:int,user_name:string,user_email:string,event_type:string,event_id:int,event_name:string,event_date:string,source_type:string,source_id:int}|null
 */
function resolveCertificatePayloadFromSource(string $sourceType, int $sourceId): ?array
{
    ensureCertificatesSchema();
    if ($sourceId <= 0) {
        return null;
    }

    if ($sourceType === 'webinar_registration') {
        $reg = db()->fetchOne(
            'SELECT wr.*, u.full_name, u.email, w.title AS event_name, w.scheduled_at
             FROM webinar_registrations wr
             JOIN users u ON u.id = wr.user_id
             JOIN webinars w ON w.id = wr.webinar_id
             WHERE wr.id = ?',
            [$sourceId]
        );
        if (!$reg) {
            return null;
        }

        return [
            'user_id'     => (int) $reg['user_id'],
            'user_name'   => (string) ($reg['full_name'] ?? 'Participant'),
            'user_email'  => (string) ($reg['email'] ?? ''),
            'event_type'  => 'webinar',
            'event_id'    => (int) $reg['webinar_id'],
            'event_name'  => (string) ($reg['event_name'] ?? 'Webinar'),
            'event_date'  => !empty($reg['scheduled_at'])
                ? date('Y-m-d', strtotime((string) $reg['scheduled_at']))
                : date('Y-m-d'),
            'source_type' => 'webinar_registration',
            'source_id'   => $sourceId,
        ];
    }

    if ($sourceType === 'bootcamp_enrollment') {
        $enroll = db()->fetchOne(
            'SELECT be.*, u.full_name, u.email, b.title AS event_name, b.start_date, b.end_date
             FROM bootcamp_enrollments be
             JOIN users u ON u.id = be.user_id
             JOIN bootcamps b ON b.id = be.bootcamp_id
             WHERE be.id = ?',
            [$sourceId]
        );
        if (!$enroll) {
            return null;
        }

        return [
            'user_id'     => (int) $enroll['user_id'],
            'user_name'   => (string) ($enroll['full_name'] ?? 'Participant'),
            'user_email'  => (string) ($enroll['email'] ?? ''),
            'event_type'  => 'bootcamp',
            'event_id'    => (int) $enroll['bootcamp_id'],
            'event_name'  => (string) ($enroll['event_name'] ?? 'Bootcamp'),
            'event_date'  => !empty($enroll['end_date'])
                ? date('Y-m-d', strtotime((string) $enroll['end_date']))
                : (!empty($enroll['start_date'])
                    ? date('Y-m-d', strtotime((string) $enroll['start_date']))
                    : date('Y-m-d')),
            'source_type' => 'bootcamp_enrollment',
            'source_id'   => $sourceId,
        ];
    }

    return null;
}

/**
 * @param array{user_id:int,user_name:string,user_email:string,event_type:string,event_id:int,event_name:string,event_date:string,source_type:string,source_id:int} $payload
 * @return array{success:bool, message:string, certificate?:array<string,mixed>, email_sent?:bool}
 */
function createOrRefreshCertificate(array $payload, bool $sendEmail = true, bool $forceRegenerate = false): array
{
    ensureCertificatesSchema();

    $sourceType = (string) ($payload['source_type'] ?? '');
    $sourceId = (int) ($payload['source_id'] ?? 0);
    if ($sourceType !== '' && $sourceId > 0) {
        $resolved = resolveCertificatePayloadFromSource($sourceType, $sourceId);
        if ($resolved !== null) {
            $payload = array_merge($payload, $resolved);
        }
    }

    $existing = findCertificateBySource(
        $sourceType,
        $sourceId
    );
    if (!$existing) {
        $existing = findCertificateForUserEvent(
            (int) $payload['user_id'],
            (string) $payload['event_type'],
            (int) $payload['event_id']
        );
    }

    $certificateId = $existing['certificate_id'] ?? generateNextCertificateId((string) ($payload['event_type'] ?? 'webinar'));
    $issueDate = $existing
        ? (string) ($existing['issue_date'] ?? date('Y-m-d'))
        : date('Y-m-d');

    $record = [
        'certificate_id' => $certificateId,
        'user_id'        => (int) $payload['user_id'],
        'event_type'     => (string) $payload['event_type'],
        'event_id'       => (int) $payload['event_id'],
        'user_name'      => trim((string) $payload['user_name']),
        'event_name'     => trim((string) $payload['event_name']),
        'event_date'     => (string) $payload['event_date'],
        'issue_date'     => $issueDate,
    ];

    $wasNew = false;
    $assetError = null;

    if ($existing && !$forceRegenerate) {
        $cert = $existing;
        if ($sourceType !== '' && $sourceId > 0) {
            db()->execute(
                'UPDATE certificates SET user_name=?, event_name=?, event_date=?, source_type=?, source_id=? WHERE id=?',
                [
                    $record['user_name'],
                    $record['event_name'],
                    $record['event_date'],
                    $sourceType,
                    $sourceId,
                    (int) $existing['id'],
                ]
            );
            $cert = findCertificateByPublicId((string) $existing['certificate_id']) ?: $existing;
        }

        $needsAssets = !certificateAbsolutePath(isset($cert['pdf_path']) ? (string) $cert['pdf_path'] : null);
        if ($needsAssets) {
            $assets = generateCertificateAssets(array_merge($record, ['certificate_id' => (string) $existing['certificate_id']]));
            if (!$assets['ok']) {
                $assetError = (string) ($assets['error'] ?? 'Certificate file generation failed.');
                certificateLog('error', 'Certificate asset generation failed', [
                    'certificate_id' => $certificateId,
                    'source_type'    => $sourceType,
                    'source_id'      => $sourceId,
                    'error'          => $assetError,
                ]);
            } else {
                db()->execute(
                    'UPDATE certificates SET user_name=?, event_name=?, event_date=?, qr_code=?, pdf_path=?, png_path=?, jpg_path=? WHERE id=?',
                    [
                        $record['user_name'],
                        $record['event_name'],
                        $record['event_date'],
                        $assets['qr'] ?? null,
                        $assets['pdf'] ?? null,
                        $assets['png'] ?? null,
                        $assets['jpg'] ?? null,
                        (int) $existing['id'],
                    ]
                );
                $cert = findCertificateByPublicId((string) $existing['certificate_id']) ?: $existing;
            }
        }
    } elseif ($existing && $forceRegenerate) {
        deleteCertificateAssetFiles($existing);
        $assets = generateCertificateAssets($record);
        if (!$assets['ok']) {
            return ['success' => false, 'message' => $assets['error'] ?? 'Certificate regeneration failed.'];
        }
        db()->execute(
            'UPDATE certificates SET user_name=?, event_name=?, event_date=?, issue_date=?, qr_code=?, pdf_path=?, png_path=?, jpg_path=?, email_sent=0 WHERE id=?',
            [
                $record['user_name'],
                $record['event_name'],
                $record['event_date'],
                $record['issue_date'],
                $assets['qr'] ?? null,
                $assets['pdf'] ?? null,
                $assets['png'] ?? null,
                $assets['jpg'] ?? null,
                (int) $existing['id'],
            ]
        );
        $cert = findCertificateByPublicId($certificateId);
    } else {
        $assets = generateCertificateAssets($record);
        if (!$assets['ok']) {
            return ['success' => false, 'message' => $assets['error'] ?? 'Certificate generation failed.'];
        }
        db()->execute(
            'INSERT INTO certificates (certificate_id, user_id, event_type, event_id, user_name, event_name, event_date, issue_date, qr_code, pdf_path, png_path, jpg_path, source_type, source_id, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $certificateId,
                $record['user_id'],
                $record['event_type'],
                $record['event_id'],
                $record['user_name'],
                $record['event_name'],
                $record['event_date'],
                $record['issue_date'],
                $assets['qr'] ?? null,
                $assets['pdf'] ?? null,
                $assets['png'] ?? null,
                $assets['jpg'] ?? null,
                (string) $payload['source_type'],
                (int) $payload['source_id'],
                'valid',
            ]
        );
        certificateLog('info', 'Certificate created', ['certificate_id' => $certificateId, 'user_id' => $record['user_id']]);
        $cert = findCertificateByPublicId($certificateId);
        $wasNew = true;
    }

    if ($assetError !== null) {
        return [
            'success'     => false,
            'message'     => $assetError,
            'certificate' => $cert ?? null,
            'email_sent'  => false,
        ];
    }

    if (!is_array($cert ?? null) || empty($cert['certificate_id'])) {
        certificateLog('error', 'Certificate record missing after generation', [
            'source_type' => $sourceType,
            'source_id'   => $sourceId,
        ]);

        return [
            'success'    => false,
            'message'    => 'Certificate record could not be saved.',
            'email_sent' => false,
        ];
    }

    $emailSent = false;
    $emailStatus = 'pending';
    $recipientEmail = trim((string) ($payload['user_email'] ?? ''));

    $shouldSendEmail = $sendEmail
        && is_array($cert)
        && ($wasNew || $forceRegenerate || empty($cert['email_sent']));

    if ($shouldSendEmail && !certificateRecipientEmailValid($recipientEmail)) {
        $invalidMsg = $recipientEmail === ''
            ? 'User email address is missing.'
            : 'User email address is invalid.';
        markCertificateEmailFailed((int) ($cert['id'] ?? 0), $invalidMsg);
        certificateLog('error', 'Certificate email skipped — invalid recipient', [
            'certificate_id' => $certificateId,
            'email'            => $recipientEmail,
            'error'            => $invalidMsg,
        ]);
        $emailStatus = 'failed';
        $shouldSendEmail = false;
    }

    if ($shouldSendEmail) {
        $certDbId = (int) ($cert['id'] ?? 0);
        markCertificateEmailPending($certDbId);
        $cert = findCertificateByPublicId($certificateId) ?: $cert;
        $emailSent = sendCertificateDeliveryEmail($cert, $recipientEmail, $certDbId);
        $emailStatus = $emailSent ? 'sent' : 'failed';
    } elseif (!empty($cert['email_sent'])) {
        $emailStatus = 'sent';
    } elseif (($cert['email_status'] ?? '') === 'failed') {
        $emailStatus = 'failed';
    }

    return [
        'success'      => true,
        'message'      => 'Certificate generated successfully.',
        'certificate'  => $cert,
        'email_sent'   => $emailSent,
        'email_status' => $emailStatus,
    ];
}

/** @param array<string,mixed> $certificate */
function deleteCertificateAssetFiles(array $certificate): void
{
    foreach (['pdf_path', 'png_path', 'jpg_path', 'qr_code'] as $key) {
        $abs = certificateAbsolutePath(isset($certificate[$key]) ? (string) $certificate[$key] : null);
        if ($abs && is_file($abs)) {
            @unlink($abs);
        }
    }
}

/** @param array<string,mixed> $certificate */
function sendCertificateDeliveryEmail(array $certificate, string $toEmail, int $certificateDbId = 0): bool
{
    require_once __DIR__ . '/mailer.php';
    if (!function_exists('sendCertificateEmail')) {
        $error = 'Mailer module unavailable.';
        certificateLog('error', 'Certificate email skipped — mailer missing', [
            'certificate_id' => $certificate['certificate_id'] ?? '',
        ]);
        markCertificateEmailFailed($certificateDbId, $error);

        return false;
    }

    if (!certificateRecipientEmailValid($toEmail)) {
        $error = 'Invalid recipient email address.';
        markCertificateEmailFailed($certificateDbId, $error);
        certificateLog('error', 'Certificate email failed', [
            'certificate_id' => $certificate['certificate_id'] ?? '',
            'to'             => $toEmail,
            'error'          => $error,
        ]);

        return false;
    }

    $pdfPath = certificateAbsolutePath(isset($certificate['pdf_path']) ? (string) $certificate['pdf_path'] : null);
    if (!$pdfPath || !is_file($pdfPath)) {
        $error = 'Certificate PDF not found on disk.';
        markCertificateEmailFailed($certificateDbId, $error);
        certificateLog('error', 'Certificate email failed', [
            'certificate_id' => $certificate['certificate_id'] ?? '',
            'to'             => $toEmail,
            'error'          => $error,
        ]);

        return false;
    }

    $issueDate = (string) ($certificate['issue_date'] ?? date('Y-m-d'));

    $result = sendCertificateEmail(
        $toEmail,
        (string) ($certificate['user_name'] ?? 'Participant'),
        (string) ($certificate['certificate_id'] ?? ''),
        (string) ($certificate['event_name'] ?? ''),
        (string) ($certificate['event_date'] ?? ''),
        $issueDate,
        $pdfPath
    );

    if (!empty($result['ok'])) {
        markCertificateEmailSent($certificateDbId);
        certificateLog('info', 'Certificate email sent', [
            'certificate_id' => $certificate['certificate_id'] ?? '',
            'to'             => $toEmail,
        ]);

        return true;
    }

    $error = (string) ($result['error'] ?? 'Email delivery failed');
    markCertificateEmailFailed($certificateDbId, $error);
    certificateLog('error', 'Certificate email failed', [
        'certificate_id' => $certificate['certificate_id'] ?? '',
        'to'             => $toEmail,
        'error'          => $error,
    ]);

    return false;
}

/**
 * @return array{success:bool, message:string}
 */
function regenerateCertificate(int $certificateDbId): array
{
    $cert = db()->fetchOne('SELECT * FROM certificates WHERE id = ?', [$certificateDbId]);
    if (!$cert) {
        return ['success' => false, 'message' => 'Certificate not found.'];
    }

    $user = db()->fetchOne('SELECT full_name, email FROM users WHERE id = ?', [(int) $cert['user_id']]);
    $result = createOrRefreshCertificate([
        'user_id'     => (int) $cert['user_id'],
        'user_name'   => (string) ($user['full_name'] ?? $cert['user_name']),
        'user_email'  => (string) ($user['email'] ?? ''),
        'event_type'  => (string) $cert['event_type'],
        'event_id'    => (int) $cert['event_id'],
        'event_name'  => (string) $cert['event_name'],
        'event_date'  => (string) $cert['event_date'],
        'source_type' => (string) ($cert['source_type'] ?? ($cert['event_type'] === 'bootcamp' ? 'bootcamp_enrollment' : 'webinar_registration')),
        'source_id'   => (int) ($cert['source_id'] ?? 0),
    ], false, true);

    return ['success' => $result['success'], 'message' => $result['message']];
}

/**
 * @return array{success:bool, message:string}
 */
function resendCertificateEmail(int $certificateDbId): array
{
    $cert = db()->fetchOne('SELECT * FROM certificates WHERE id = ?', [$certificateDbId]);
    if (!$cert) {
        return ['success' => false, 'message' => 'Certificate not found.'];
    }
    $user = db()->fetchOne('SELECT email FROM users WHERE id = ?', [(int) $cert['user_id']]);
    $email = trim((string) ($user['email'] ?? ''));
    if (!certificateRecipientEmailValid($email)) {
        return ['success' => false, 'message' => 'User email not found or invalid.'];
    }
    if (!certificateAbsolutePath(isset($cert['pdf_path']) ? (string) $cert['pdf_path'] : null)) {
        $regen = regenerateCertificate($certificateDbId);
        if (!$regen['success']) {
            return $regen;
        }
        $cert = db()->fetchOne('SELECT * FROM certificates WHERE id = ?', [$certificateDbId]) ?: $cert;
    }
    markCertificateEmailPending($certificateDbId);
    $sent = sendCertificateDeliveryEmail($cert, $email, $certificateDbId);
    if ($sent) {
        return ['success' => true, 'message' => 'Certificate email sent successfully.'];
    }

    return ['success' => false, 'message' => 'Failed to send certificate email. Check SMTP settings.'];
}

/**
 * @return list<array<string,mixed>>
 */
function listCertificatesForUser(int $userId): array
{
    ensureCertificatesSchema();

    return db()->fetchAll(
        'SELECT * FROM certificates WHERE user_id = ? ORDER BY issue_date DESC, created_at DESC',
        [$userId]
    );
}

/**
 * @return array{rows:list<array<string,mixed>>, total:int}
 */
function searchCertificatesAdmin(?string $query = null, ?string $eventType = null, int $limit = 100, int $offset = 0): array
{
    ensureCertificatesSchema();
    $where = ['1=1'];
    $params = [];

    if ($eventType === 'webinar' || $eventType === 'bootcamp') {
        $where[] = 'c.event_type = ?';
        $params[] = $eventType;
    }
    $q = trim((string) $query);
    if ($q !== '') {
        $where[] = '(c.certificate_id LIKE ? OR c.user_name LIKE ? OR c.event_name LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }

    $sqlWhere = implode(' AND ', $where);
    $total = (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM certificates c WHERE {$sqlWhere}", $params)['c'] ?? 0);
    $params[] = $limit;
    $params[] = $offset;
    $rows = db()->fetchAll(
        "SELECT c.*, u.email AS user_email
         FROM certificates c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE {$sqlWhere}
         ORDER BY c.created_at DESC
         LIMIT ? OFFSET ?",
        $params
    );

    return ['rows' => $rows, 'total' => $total];
}

/** Serve a certificate file download with appropriate headers. */
function streamCertificateDownload(array $certificate, string $format, bool $inline = false): void
{
    $key = match ($format) {
        'pdf' => 'pdf_path',
        'png' => 'png_path',
        'jpg', 'jpeg' => 'jpg_path',
        default => '',
    };
    if ($key === '') {
        http_response_code(400);
        echo 'Invalid format.';
        exit;
    }
    $path = certificateAbsolutePath(isset($certificate[$key]) ? (string) $certificate[$key] : null);
    if (!$path || !is_file($path)) {
        $regen = regenerateCertificate((int) ($certificate['id'] ?? 0));
        if ($regen['success']) {
            $certificate = findCertificateByPublicId((string) ($certificate['certificate_id'] ?? '')) ?: $certificate;
            $path = certificateAbsolutePath(isset($certificate[$key]) ? (string) $certificate[$key] : null);
        }
    }
    if (!$path || !is_file($path)) {
        http_response_code(404);
        echo 'Certificate file not found.';
        exit;
    }

    $mime = match ($format) {
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        default => 'image/jpeg',
    };
    $filename = ($certificate['certificate_id'] ?? 'certificate') . '.' . ($format === 'jpeg' ? 'jpg' : $format);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    exit;
}

function tryAutoIssueCertificateAfterWebinarAttendance(int $registrationId): void
{
    $result = issueCertificateForWebinarRegistration($registrationId, true);
    if (!$result['success']) {
        certificateLog('info', 'Webinar certificate skipped', ['registration_id' => $registrationId, 'reason' => $result['message']]);
    } else {
        certificateLog('info', 'Webinar certificate issued', [
            'registration_id' => $registrationId,
            'certificate_id'  => $result['certificate']['certificate_id'] ?? '',
            'email_sent'      => !empty($result['email_sent']),
        ]);
    }
}

function tryAutoIssueCertificateAfterBootcampCompletion(int $enrollmentId): void
{
    $result = issueCertificateForBootcampEnrollment($enrollmentId, true);
    if (!$result['success']) {
        certificateLog('info', 'Bootcamp certificate skipped', ['enrollment_id' => $enrollmentId, 'reason' => $result['message']]);
    } else {
        certificateLog('info', 'Bootcamp certificate issued', [
            'enrollment_id'  => $enrollmentId,
            'certificate_id' => $result['certificate']['certificate_id'] ?? '',
            'email_sent'     => !empty($result['email_sent']),
        ]);
    }
}

/**
 * Issue certificates for attended/completed events that were missed (e.g. before attendance hook fix).
 */
function syncMissingCertificatesForUser(int $userId): int
{
    ensureCertificatesSchema();
    $issued = 0;

    $webinarRows = db()->fetchAll(
        "SELECT wr.id FROM webinar_registrations wr
         LEFT JOIN certificates c ON c.source_type = 'webinar_registration' AND c.source_id = wr.id
         WHERE wr.user_id = ? AND wr.attended = 1 AND c.id IS NULL",
        [$userId]
    );
    foreach ($webinarRows as $row) {
        $result = issueCertificateForWebinarRegistration((int) $row['id'], true);
        if ($result['success']) {
            $issued++;
        }
    }

    $bootcampRows = db()->fetchAll(
        "SELECT be.id FROM bootcamp_enrollments be
         LEFT JOIN certificates c ON c.source_type = 'bootcamp_enrollment' AND c.source_id = be.id
         WHERE be.user_id = ? AND be.completed = 1 AND c.id IS NULL",
        [$userId]
    );
    foreach ($bootcampRows as $row) {
        $result = issueCertificateForBootcampEnrollment((int) $row['id'], true);
        if ($result['success']) {
            $issued++;
        }
    }

    return $issued;
}

function certificateDownloadUrl(string $certificateId, string $format, bool $inline = false): string
{
    $params = [
        'action' => $inline ? 'view' : 'download',
        'format' => $format,
        'id'     => $certificateId,
    ];

    return url('api/certificate-action.php?' . http_build_query($params));
}
