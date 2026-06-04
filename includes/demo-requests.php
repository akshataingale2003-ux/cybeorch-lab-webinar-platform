<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureDemoRequestsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS demo_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_page VARCHAR(80) DEFAULT NULL,
        product_name VARCHAR(255) DEFAULT NULL,
        project_name VARCHAR(255) DEFAULT NULL,
        interested_technology VARCHAR(180) DEFAULT NULL,
        preferred_date DATE DEFAULT NULL,
        preferred_time TIME DEFAULT NULL,
        message TEXT DEFAULT NULL,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(180) NOT NULL,
        phone VARCHAR(24) DEFAULT NULL,
        org_name VARCHAR(180) DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_dr_created (created_at),
        INDEX idx_dr_email (email(120)),
        INDEX idx_dr_product (product_name(120))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * @param array{
 *  source_page?:string, product_name?:string, project_name?:string,
 *  interested_technology?:string, preferred_date?:string, preferred_time?:string,
 *  message?:string, full_name:string, email:string, phone?:string, org_name?:string,
 *  ip_address?:string, user_agent?:string
 * } $data
 */
function insertDemoRequest(array $data): int
{
    ensureDemoRequestsSchema();
    $id = (int) db()->insert(
        "INSERT INTO demo_requests
        (source_page, product_name, project_name, interested_technology, preferred_date, preferred_time, message,
         full_name, email, phone, org_name, ip_address, user_agent)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [
            $data['source_page'] ?? null,
            $data['product_name'] ?? null,
            $data['project_name'] ?? null,
            $data['interested_technology'] ?? null,
            $data['preferred_date'] ?? null,
            $data['preferred_time'] ?? null,
            $data['message'] ?? null,
            $data['full_name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['org_name'] ?? null,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
        ]
    );

    require_once __DIR__ . '/form-submissions.php';
    recordFormSubmission([
        'form_key'          => 'product-demo',
        'form_label'        => 'Product demo request',
        'source_page'       => $data['source_page'] ?? 'products.php',
        'full_name'         => $data['full_name'],
        'email'             => $data['email'],
        'phone'             => $data['phone'] ?? null,
        'summary'           => ($data['product_name'] ?? '') . ' — ' . ($data['interested_technology'] ?? ''),
        'payload'           => $data,
        'storage_table'     => 'demo_requests',
        'storage_record_id' => $id,
    ]);

    return $id;
}

/** @return array<int, array<string, mixed>> */
function getDemoRequests(int $limit = 500): array
{
    return adminFetchDemoRequests(['limit' => $limit, 'status' => 'all']);
}

/**
 * @param array{status?: string, q?: string, limit?: int} $filters
 * @return array<int, array<string, mixed>>
 */
function adminFetchDemoRequests(array $filters = []): array
{
    ensureDemoRequestsSchema();
    require_once __DIR__ . '/admin-actions.php';
    ensureAdminActionsSchema();

    $status = $filters['status'] ?? 'active';
    $q = trim((string) ($filters['q'] ?? ''));
    $limit = max(1, min(500, (int) ($filters['limit'] ?? 500)));

    $where = [];
    $params = [];

    if ($status === 'deleted') {
        $where[] = 'd.deleted_at IS NOT NULL';
    } else {
        $where[] = adminSqlActive('d');
    }

    if ($q !== '') {
        $where[] = '(d.full_name LIKE ? OR d.email LIKE ? OR d.product_name LIKE ? OR d.org_name LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    return db()->fetchAll(
        'SELECT d.* FROM demo_requests d WHERE ' . implode(' AND ', $where)
        . ' ORDER BY d.created_at DESC LIMIT ' . $limit,
        $params
    );
}

function getDemoRequestById(int $id): ?array
{
    ensureDemoRequestsSchema();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM demo_requests WHERE id = ?', [$id]);
    return $row ?: null;
}

function getDemoRequestCount(): int
{
    ensureDemoRequestsSchema();
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM demo_requests')['c'] ?? 0);
}

