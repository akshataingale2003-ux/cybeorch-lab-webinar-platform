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
}

function insertContactMessage(string $name, string $email, string $phone, string $subject, string $message): void
{
    ensureContactMessagesSchema();
    db()->execute(
        'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?,?,?,?,?)',
        [$name, $email, $phone, $subject, $message]
    );
}

function getUnreadContactMessageCount(): int
{
    ensureContactMessagesSchema();
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = 0')['c'] ?? 0);
}

/** @return array<int, array<string, mixed>> */
function getContactMessages(?string $subjectFilter = null, ?string $readFilter = null): array
{
    ensureContactMessagesSchema();
    $where = 'deleted_at IS NULL';
    $params = [];

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
        "SELECT * FROM contact_messages WHERE {$where} ORDER BY created_at DESC",
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

/** @return array<string, string> */
function contactMessageSubjectTypes(): array
{
    return [
        ''                                              => 'All messages',
        'Contact'                                       => 'Contact form',
        'Free Bootcamps & Webinars Registration'        => 'Free enrollment',
        'Assignment Registration'                       => 'Assignment applications',
        'Pro Learner Trial Registration'                => 'Pro Learner trial',
        'Bootcamp Enquire & Enroll'                     => 'Bootcamp enquiry (legacy)',
        'Start Your Project'                            => 'Start Your Project',
        'Book Consulting'                               => 'Book Consulting',
    ];
}
