<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureCollaborationInquiriesSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS collaboration_inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_slug VARCHAR(255) DEFAULT NULL,
        project_title VARCHAR(255) DEFAULT NULL,
        project_category VARCHAR(120) DEFAULT NULL,
        collaboration_type VARCHAR(80) DEFAULT NULL,
        budget_range VARCHAR(120) DEFAULT NULL,
        timeline VARCHAR(120) DEFAULT NULL,
        company VARCHAR(180) DEFAULT NULL,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(180) NOT NULL,
        phone VARCHAR(24) DEFAULT NULL,
        description TEXT NOT NULL,
        requirement_file VARCHAR(255) DEFAULT NULL,
        requirement_file_original VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ci_created (created_at),
        INDEX idx_ci_project (project_slug(120)),
        INDEX idx_ci_email (email(120))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * @param array{
 *   project_slug?:string,project_title?:string,project_category?:string,
 *   collaboration_type?:string,budget_range?:string,timeline?:string,company?:string,
 *   full_name:string,email:string,phone?:string,description:string,
 *   requirement_file?:string,requirement_file_original?:string,
 *   ip_address?:string,user_agent?:string
 * } $data
 */
function insertCollaborationInquiry(array $data): int
{
    ensureCollaborationInquiriesSchema();
    $id = (int) db()->insert(
        "INSERT INTO collaboration_inquiries
        (project_slug, project_title, project_category, collaboration_type, budget_range, timeline, company,
         full_name, email, phone, description, requirement_file, requirement_file_original, ip_address, user_agent)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [
            $data['project_slug'] ?? null,
            $data['project_title'] ?? null,
            $data['project_category'] ?? null,
            $data['collaboration_type'] ?? null,
            $data['budget_range'] ?? null,
            $data['timeline'] ?? null,
            $data['company'] ?? null,
            $data['full_name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['description'],
            $data['requirement_file'] ?? null,
            $data['requirement_file_original'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
        ]
    );

    require_once __DIR__ . '/form-submissions.php';
    recordFormSubmission([
        'form_key'          => 'collaborate-project',
        'form_label'        => 'Live project collaboration',
        'source_page'       => 'collaborate-project.php',
        'full_name'         => $data['full_name'],
        'email'             => $data['email'],
        'phone'             => $data['phone'] ?? null,
        'summary'           => ($data['project_title'] ?? 'Project') . ' — ' . ($data['collaboration_type'] ?? ''),
        'payload'           => $data,
        'storage_table'     => 'collaboration_inquiries',
        'storage_record_id' => $id,
    ]);

    return $id;
}

/** @return array<int, array<string, mixed>> */
function getCollaborationInquiries(int $limit = 500): array
{
    return adminFetchCollaborationInquiries(['limit' => $limit, 'status' => 'all']);
}

/**
 * @param array{status?: string, q?: string, limit?: int} $filters
 * @return array<int, array<string, mixed>>
 */
function adminFetchCollaborationInquiries(array $filters = []): array
{
    ensureCollaborationInquiriesSchema();
    require_once __DIR__ . '/admin-actions.php';
    ensureAdminActionsSchema();

    $status = $filters['status'] ?? 'active';
    $q = trim((string) ($filters['q'] ?? ''));
    $limit = max(1, min(500, (int) ($filters['limit'] ?? 500)));

    $where = [];
    $params = [];

    if ($status === 'deleted') {
        $where[] = 'c.deleted_at IS NOT NULL';
    } else {
        $where[] = adminSqlActive('c');
    }

    if ($q !== '') {
        $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.project_title LIKE ? OR c.company LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    return db()->fetchAll(
        'SELECT c.* FROM collaboration_inquiries c WHERE ' . implode(' AND ', $where)
        . ' ORDER BY c.created_at DESC LIMIT ' . $limit,
        $params
    );
}

function getCollaborationInquiryById(int $id): ?array
{
    ensureCollaborationInquiriesSchema();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM collaboration_inquiries WHERE id = ?', [$id]);
    return $row ?: null;
}

function getCollaborationInquiryCount(): int
{
    ensureCollaborationInquiriesSchema();
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM collaboration_inquiries')['c'] ?? 0);
}

