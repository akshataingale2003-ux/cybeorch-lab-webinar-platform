<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureContactMessagesSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(15) DEFAULT NULL,
        subject VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        replied TINYINT(1) DEFAULT 0,
        admin_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cm_read (is_read),
        INDEX idx_cm_subject (subject(100))
    )");

    try {
        db()->execute('ALTER TABLE contact_messages ADD COLUMN admin_notes TEXT DEFAULT NULL AFTER replied');
    } catch (Throwable $e) {
        // column already exists
    }
    try {
        db()->execute('ALTER TABLE contact_messages ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
    } catch (Throwable $e) {
        // column already exists
    }
    try {
        db()->execute('ALTER TABLE contact_messages ADD COLUMN resume_path VARCHAR(500) DEFAULT NULL AFTER message');
    } catch (Throwable $e) {
        // column already exists
    }
    try {
        db()->execute('ALTER TABLE contact_messages ADD COLUMN resume_original_name VARCHAR(255) DEFAULT NULL AFTER resume_path');
    } catch (Throwable $e) {
        // column already exists
    }
}

function contactMessagesWhereActive(): string
{
    static $hasDeleted = null;
    if ($hasDeleted === null) {
        ensureContactMessagesSchema();
        $row = dbTry(
            static fn () => db()->fetchOne(
                "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'contact_messages' AND COLUMN_NAME = 'deleted_at'"
            ),
            null
        );
        $hasDeleted = ((int) ($row['c'] ?? 0)) > 0;
    }
    return $hasDeleted ? 'deleted_at IS NULL' : '1=1';
}

function insertContactMessage(
    string $name,
    string $email,
    string $phone,
    string $subject,
    string $message,
    ?string $sourcePage = null,
    ?string $formKey = null,
    ?string $resumePath = null,
    ?string $resumeOriginalName = null
): int {
    ensureContactMessagesSchema();
    $id = db()->insert(
        'INSERT INTO contact_messages (name, email, phone, subject, message, resume_path, resume_original_name) VALUES (?,?,?,?,?,?,?)',
        [$name, $email, $phone, $subject, $message, $resumePath, $resumeOriginalName]
    );

    require_once __DIR__ . '/form-submissions.php';
    recordFormSubmission([
        'form_key'          => $formKey ?? formSubmissionKeyFromLabel($subject),
        'form_label'        => $subject !== '' ? $subject : 'Contact',
        'source_page'       => $sourcePage,
        'full_name'         => $name,
        'email'             => $email,
        'phone'             => $phone,
        'summary'           => mb_substr($message, 0, 500),
        'storage_table'     => 'contact_messages',
        'storage_record_id' => $id,
    ]);

    return $id;
}

function getUnreadContactMessageCount(): int
{
    ensureContactMessagesSchema();
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = 0')['c'] ?? 0);
}

/** @return array<int, array<string, mixed>> */
function getContactMessages(?string $subjectFilter = null, ?string $readFilter = null, string $sort = 'newest'): array
{
    ensureContactMessagesSchema();
    $where = contactMessagesWhereActive();
    $params = [];
    $order = ($sort === 'oldest' ? 'ASC' : 'DESC');

    if ($subjectFilter !== null && $subjectFilter !== '') {
        $where .= ' AND subject = ?';
        $params[] = $subjectFilter;
    }
    if ($readFilter === 'unread') {
        $where .= ' AND is_read = 0';
    } elseif ($readFilter === 'read') {
        $where .= ' AND is_read = 1';
    }

    return db()->fetchAll(
        "SELECT * FROM contact_messages WHERE {$where} ORDER BY created_at {$order}",
        $params
    );
}

function getContactMessageById(int $id): ?array
{
    ensureContactMessagesSchema();
    $row = db()->fetchOne('SELECT * FROM contact_messages WHERE id = ?', [$id]);
    return $row ?: null;
}

function markContactMessageRead(int $id): void
{
    ensureContactMessagesSchema();
    db()->execute('UPDATE contact_messages SET is_read = 1 WHERE id = ?', [$id]);
}

function updateContactMessageStatus(int $id, bool $replied, string $adminNotes = ''): void
{
    ensureContactMessagesSchema();
    db()->execute(
        'UPDATE contact_messages SET is_read = 1, replied = ?, admin_notes = ? WHERE id = ?',
        [$replied ? 1 : 0, $adminNotes !== '' ? $adminNotes : null, $id]
    );
}

/** Map stored enquiry subjects to current public labels (includes legacy values). */
function contactMessageSubjectDisplay(?string $subject): string
{
    $subject = trim((string) $subject);
    $map = [
        'Assignment Registration' => 'Hands-on Projects Registration',
    ];

    return $map[$subject] ?? ($subject !== '' ? $subject : 'Contact');
}

/** @return array<string, string> */
function contactMessageSubjectTypes(): array
{
    return [
        ''                                       => 'All messages',
        'Contact'                                => 'Contact form',
        'Enquire & Enroll'                       => 'Enquire & Enroll',
        'Free Bootcamps & Webinars Registration' => 'Free enrollment (legacy)',
        'Pro Learner Trial Registration'         => 'Pro Learner trial (legacy)',
        'Assignment Registration'                => 'Hands-on Projects applications (legacy)',
        'Hands-on Projects Registration'         => 'Hands-on Projects applications',
        'Freelance Project Application'          => 'Freelance apply',
        'Start Your Project'                     => 'Start Your Project',
        'Book Consulting'                        => 'Book Consulting',
        'Project Collaboration'                  => 'Collaboration (copy)',
        'Secure Payment – Webinar / Bootcamp'    => 'Secure payment',
        'Bootcamp Enquire & Enroll'              => 'Bootcamp enquiry (legacy)',
    ];
}

