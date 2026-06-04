<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const GET_STARTED_STATUSES = ['new', 'contacted', 'closed'];

/** @return list<string> */
function getStartedServiceOptions(): array
{
    return [
        'Web Development',
        'Mobile App Development',
        'Cybersecurity Services',
        'Cloud & DevOps',
        'AI & Machine Learning',
        'Blockchain / Web3',
        'Software Development Company & Bootcamps',
        'Live Projects',
        'Freelance Talent',
        'IT Consulting',
        'Other',
    ];
}

/** @return list<string> */
function getStartedProjectTypeOptions(): array
{
    return [
        'New Build',
        'MVP / Prototype',
        'Redesign / Migration',
        'Maintenance & Support',
        'Security Audit',
        'Automation',
        'Software Development Company Program',
        'Other',
    ];
}

/** @return list<string> */
function getStartedBudgetOptions(): array
{
    return [
        'Under ₹50,000',
        '₹50,000 – ₹2,00,000',
        '₹2,00,000 – ₹10,00,000',
        '₹10,00,000+',
        'Not sure yet',
    ];
}

/** @return array<string, string> */
function getStartedContactMethods(): array
{
    return [
        'email' => 'Email',
        'phone' => 'Phone',
    ];
}

/** @return array<string, string> */
function getStartedInquiryStatusLabels(): array
{
    return [
        'new'       => 'New',
        'contacted' => 'Contacted',
        'closed'    => 'Closed',
    ];
}

function ensureGetStartedInquiriesSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS get_started_inquiries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        email VARCHAR(180) NOT NULL,
        phone VARCHAR(24) NOT NULL,
        company VARCHAR(180) DEFAULT NULL,
        service_interested VARCHAR(120) NOT NULL,
        project_type VARCHAR(120) DEFAULT NULL,
        budget_range VARCHAR(120) DEFAULT NULL,
        message TEXT NOT NULL,
        preferred_contact ENUM('email','phone') NOT NULL DEFAULT 'email',
        inquiry_status ENUM('new','contacted','closed') NOT NULL DEFAULT 'new',
        source_page VARCHAR(80) DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        admin_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_gsi_created (created_at),
        INDEX idx_gsi_status (inquiry_status),
        INDEX idx_gsi_email (email(120))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        db()->execute('ALTER TABLE get_started_inquiries ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
    } catch (Throwable $e) {
    }
}

/**
 * @return array{ok: bool, message: string, field?: string, data?: array<string, string|null>}
 */
function validateGetStartedInquiryInput(array $input): array
{
    $fullName = trim(sanitize((string) ($input['full_name'] ?? '')));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $phone = trim(sanitize((string) ($input['phone'] ?? '')));
    $company = trim(sanitize((string) ($input['company'] ?? '')));
    $service = trim(sanitize((string) ($input['service_interested'] ?? '')));
    $projectType = trim(sanitize((string) ($input['project_type'] ?? '')));
    $budget = trim(sanitize((string) ($input['budget_range'] ?? '')));
    $message = trim(sanitize((string) ($input['message'] ?? '')));
    $preferred = strtolower(trim((string) ($input['preferred_contact'] ?? 'email')));

    if (strlen($fullName) < 2) {
        return ['ok' => false, 'message' => 'Please enter your full name.', 'field' => 'full_name'];
    }
    if (!isValidEmail($email)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.', 'field' => 'email'];
    }
    if (strlen(preg_replace('/\D+/', '', $phone) ?? '') < 8) {
        return ['ok' => false, 'message' => 'Please enter a valid phone number.', 'field' => 'phone'];
    }
    if ($service === '' || !in_array($service, getStartedServiceOptions(), true)) {
        return ['ok' => false, 'message' => 'Please select a service.', 'field' => 'service_interested'];
    }
    if ($projectType !== '' && !in_array($projectType, getStartedProjectTypeOptions(), true)) {
        return ['ok' => false, 'message' => 'Please select a valid project type.', 'field' => 'project_type'];
    }
    if ($budget !== '' && !in_array($budget, getStartedBudgetOptions(), true)) {
        return ['ok' => false, 'message' => 'Please select a valid budget range.', 'field' => 'budget_range'];
    }
    if (strlen($message) < 10) {
        return ['ok' => false, 'message' => 'Please describe your requirements (at least 10 characters).', 'field' => 'message'];
    }
    if (!isset(getStartedContactMethods()[$preferred])) {
        return ['ok' => false, 'message' => 'Please choose a preferred contact method.', 'field' => 'preferred_contact'];
    }

    return [
        'ok'      => true,
        'message' => '',
        'data'    => [
            'full_name'          => $fullName,
            'email'              => $email,
            'phone'              => $phone,
            'company'            => $company !== '' ? $company : null,
            'service_interested' => $service,
            'project_type'       => $projectType !== '' ? $projectType : null,
            'budget_range'       => $budget !== '' ? $budget : null,
            'message'            => $message,
            'preferred_contact'  => $preferred,
        ],
    ];
}

/** @param array<string, string|null> $data */
function insertGetStartedInquiry(array $data, string $sourcePage = 'get-started.php'): int
{
    ensureGetStartedInquiriesSchema();

    $id = (int) db()->insert(
        "INSERT INTO get_started_inquiries
        (full_name, email, phone, company, service_interested, project_type, budget_range, message,
         preferred_contact, inquiry_status, source_page, ip_address, user_agent)
         VALUES (?,?,?,?,?,?,?,?,?,'new',?,?,?)",
        [
            $data['full_name'],
            $data['email'],
            $data['phone'],
            $data['company'],
            $data['service_interested'],
            $data['project_type'],
            $data['budget_range'],
            $data['message'],
            $data['preferred_contact'],
            $sourcePage,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]
    );

    require_once __DIR__ . '/form-submissions.php';
    recordFormSubmission([
        'form_key'          => 'get-started',
        'form_label'        => 'Get Started inquiry',
        'source_page'       => $sourcePage,
        'full_name'         => $data['full_name'],
        'email'             => $data['email'],
        'phone'             => $data['phone'],
        'summary'           => ($data['service_interested'] ?? '') . ' — ' . ($data['project_type'] ?? 'General'),
        'payload'           => $data,
        'storage_table'     => 'get_started_inquiries',
        'storage_record_id' => $id,
    ]);

    return $id;
}

function handleGetStartedInquiryPost(string $redirectPath = 'get-started.php'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        redirectWith($redirectPath, 'error', 'Invalid form submission. Please try again.');
    }

    if (!empty($_POST['website'])) {
        redirectWith($redirectPath, 'error', 'Could not submit your inquiry.');
    }

    $validated = validateGetStartedInquiryInput($_POST);
    if (!$validated['ok']) {
        redirectWith($redirectPath, 'error', $validated['message']);
    }

    try {
        insertGetStartedInquiry($validated['data'] ?? [], $redirectPath);
        redirectWith($redirectPath, 'success', 'Thank you! Your inquiry was submitted successfully. Our team will contact you soon.');
    } catch (Throwable $e) {
        redirectWith($redirectPath, 'error', 'Could not submit your inquiry. Please try again later.');
    }
}

/**
 * @param array{status?: string, stage?: string, q?: string, limit?: int} $filters
 * @return array<int, array<string, mixed>>
 */
function adminFetchGetStartedInquiries(array $filters = []): array
{
    ensureGetStartedInquiriesSchema();
    require_once __DIR__ . '/admin-actions.php';
    ensureAdminActionsSchema();

    $status = $filters['status'] ?? 'active';
    $stage = $filters['stage'] ?? 'all';
    $q = trim((string) ($filters['q'] ?? ''));
    $limit = max(1, min(500, (int) ($filters['limit'] ?? 500)));

    $where = [];
    $params = [];

    if ($status === 'deleted') {
        $where[] = 'g.deleted_at IS NOT NULL';
    } else {
        $where[] = adminSqlActive('g');
    }

    if ($stage !== 'all' && in_array($stage, GET_STARTED_STATUSES, true)) {
        $where[] = 'g.inquiry_status = ?';
        $params[] = $stage;
    }

    if ($q !== '') {
        $where[] = '(g.full_name LIKE ? OR g.email LIKE ? OR g.company LIKE ? OR g.service_interested LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    return db()->fetchAll(
        'SELECT g.* FROM get_started_inquiries g WHERE ' . implode(' AND ', $where)
        . ' ORDER BY g.created_at DESC LIMIT ' . $limit,
        $params
    );
}

function getGetStartedInquiryById(int $id): ?array
{
    ensureGetStartedInquiriesSchema();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM get_started_inquiries WHERE id = ?', [$id]);
    return $row ?: null;
}

function updateGetStartedInquiryStatus(int $id, string $status, string $adminNotes = ''): bool
{
    ensureGetStartedInquiriesSchema();
    if ($id < 1 || !in_array($status, GET_STARTED_STATUSES, true)) {
        return false;
    }

    $notes = trim($adminNotes);
    if ($notes !== '') {
        db()->execute(
            'UPDATE get_started_inquiries SET inquiry_status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?',
            [$status, $notes, $id]
        );
    } else {
        db()->execute(
            'UPDATE get_started_inquiries SET inquiry_status = ?, updated_at = NOW() WHERE id = ?',
            [$status, $id]
        );
    }

    return true;
}

function getGetStartedInquiryCount(string $stage = 'all'): int
{
    ensureGetStartedInquiriesSchema();
    require_once __DIR__ . '/admin-actions.php';
    ensureAdminActionsSchema();

    $sql = 'SELECT COUNT(*) AS c FROM get_started_inquiries g WHERE ' . adminSqlActive('g');
    $params = [];
    if ($stage !== 'all' && in_array($stage, GET_STARTED_STATUSES, true)) {
        $sql .= ' AND g.inquiry_status = ?';
        $params[] = $stage;
    }

    return (int) (db()->fetchOne($sql, $params)['c'] ?? 0);
}

function renderGetStartedStatusBadge(string $status): void
{
    $labels = getStartedInquiryStatusLabels();
    $label = $labels[$status] ?? ucfirst($status);
    $class = match ($status) {
        'contacted' => 'badge-refunded',
        'closed'    => 'badge-paid',
        default     => 'badge-pending',
    };
    echo '<span class="badge-status ' . $class . '">' . htmlspecialchars($label) . '</span>';
}
