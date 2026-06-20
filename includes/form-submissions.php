<?php
declare(strict_types=1);

/**
 * Universal form submission log — every public form should call recordFormSubmission().
 * Specialized tables (contact_messages, demo_requests, etc.) remain the source of truth;
 * this table powers the admin master log and future form auto-discovery.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function ensureFormSubmissionsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS form_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_key VARCHAR(80) NOT NULL,
        form_label VARCHAR(255) NOT NULL,
        source_page VARCHAR(120) DEFAULT NULL,
        full_name VARCHAR(120) DEFAULT NULL,
        email VARCHAR(180) DEFAULT NULL,
        phone VARCHAR(24) DEFAULT NULL,
        summary TEXT DEFAULT NULL,
        payload_json JSON DEFAULT NULL,
        storage_table VARCHAR(64) DEFAULT NULL,
        storage_record_id INT DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fs_form_key (form_key),
        INDEX idx_fs_created (created_at),
        INDEX idx_fs_read (is_read),
        INDEX idx_fs_email (email(120))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** @return list<string> */
function formSubmissionSensitiveFieldNames(): array
{
    return [
        'csrf_token', 'password', 'confirm_password', 'new_password', 'current_password',
        'otp', 'otp_code', 'razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature',
    ];
}

/**
 * Sanitized POST (or custom array) for JSON storage — never stores secrets.
 *
 * @param array<string, mixed>|null $data
 * @return array<string, mixed>
 */
function sanitizedFormPayload(?array $data = null): array
{
    $data = $data ?? $_POST;
    $out = [];
    $skip = array_flip(formSubmissionSensitiveFieldNames());

    foreach ($data as $key => $value) {
        if (!is_string($key) || isset($skip[$key])) {
            continue;
        }
        if (is_array($value)) {
            $out[$key] = array_map(static fn ($v) => is_scalar($v) ? sanitize((string) $v) : '[complex]', $value);
        } elseif (is_scalar($value)) {
            $out[$key] = sanitize((string) $value);
        }
    }

    return $out;
}

function formSubmissionKeyFromLabel(string $label): string
{
    $key = strtolower(trim($label));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? 'form';
    return trim($key, '-') ?: 'form';
}

function formSubmissionKeyFromPath(string $path): string
{
    $base = basename(str_replace('\\', '/', $path));
    return preg_replace('/\.php$/', '', $base) ?: 'form';
}

/** Canonical slug for DB filtering (hyphenated lowercase). */
function normalizeFormSubmissionKey(string $key): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';
    $key = trim($key, '-');

    static $aliases = [
        'project-collaboration'              => 'collaborate-project',
        'live-project-collaboration'         => 'collaborate-project',
        'secure-payment-webinar-bootcamp'    => 'secure-payment',
        'secure-payment-webinar'           => 'secure-payment',
        'enquire-and-enroll'               => 'enquire-enroll',
        'assignment-registration'          => 'assignment-register',
        'freelance-project-application'      => 'apply-freelance',
        'freelance-apply'                    => 'apply-freelance',
        'start-your-project'               => 'start-project',
        'book-consulting-session'            => 'book-consulting',
        'platform-signup-otp'                => 'platform-signup',
        'website-popup-registration'         => 'website-registration',
        'product-demo-request'               => 'product-demo',
        'support-desk'                       => 'support-ticket',
        'support'                              => 'support-ticket',
        'online-payment'                       => 'payment',
        'webinar-registration-form'          => 'webinar-registration',
        'bootcamp-registration-form'         => 'bootcamp-registration',
    ];

    return $aliases[$key] ?? ($key !== '' ? $key : 'form');
}

/**
 * All form_key values that belong to a canonical key (includes legacy slugs).
 *
 * @return list<string>
 */
function formSubmissionKeysForCanonical(string $canonicalKey): array
{
    $canonicalKey = normalizeFormSubmissionKey($canonicalKey);
    $keys = [$canonicalKey];

    foreach (getFormSubmissionRegistryDefinitions() as $def) {
        if ($def['form_key'] === $canonicalKey) {
            $keys[] = formSubmissionKeyFromLabel($def['form_label']);
        }
    }

    static $legacyByCanonical = [
        'contact'               => ['contact'],
        'enquire-enroll'        => ['enquire-and-enroll', 'enquire-enroll'],
        'assignment-register'   => ['assignment-registration', 'assignment-register'],
        'apply-freelance'       => ['freelance-project-application', 'freelance-apply', 'apply-freelance'],
        'start-project'         => ['start-your-project', 'start-project'],
        'book-consulting'       => ['book-consulting'],
        'secure-payment'        => ['secure-payment-webinar-bootcamp', 'secure-payment'],
        'collaborate-project'   => ['project-collaboration', 'collaborate-project'],
        'product-demo'          => ['product-demo'],
        'website-registration'  => ['website-registration'],
        'platform-signup'       => ['platform-signup'],
        'freelancer-registration' => ['freelancer-registration'],
        'webinar-registration'  => ['webinar-registration'],
        'bootcamp-registration' => ['bootcamp-registration'],
        'payment'               => ['payment', 'online-payment'],
        'support-ticket'        => ['support-ticket', 'support-desk', 'support'],
    ];

    if (isset($legacyByCanonical[$canonicalKey])) {
        $keys = array_merge($keys, $legacyByCanonical[$canonicalKey]);
    }

    return array_values(array_unique($keys));
}

/**
 * Admin filter categories — buttons map to these slugs via ?category=
 *
 * @return array<string, array{label: string, keys: list<string>, storage_tables: list<string>}>
 */
function getAdminFormSubmissionCategories(): array
{
    $messageKeys = [
        'contact', 'enquire-enroll', 'assignment-register', 'apply-freelance',
        'start-project', 'book-consulting', 'secure-payment',
    ];
    $expandedMessageKeys = [];
    foreach ($messageKeys as $k) {
        $expandedMessageKeys = array_merge($expandedMessageKeys, formSubmissionKeysForCanonical($k));
    }
    $expandedMessageKeys = array_values(array_unique($expandedMessageKeys));

    return [
        'all' => [
            'label'           => 'All form submissions',
            'keys'            => [],
            'storage_tables'  => [],
        ],
        'messages-enquiries' => [
            'label'           => 'Messages & Enquiries',
            'keys'            => array_values(array_diff(
                $expandedMessageKeys,
                formSubmissionKeysForCanonical('contact')
            )),
            'storage_tables'  => [],
        ],
        'contact' => [
            'label'           => 'Contact',
            'keys'            => formSubmissionKeysForCanonical('contact'),
            'storage_tables'  => [],
        ],
        'webinar-registration' => [
            'label'           => 'Webinar Registration',
            'keys'            => formSubmissionKeysForCanonical('webinar-registration'),
            'storage_tables'  => ['webinar_registrations'],
        ],
        'bootcamp-registration' => [
            'label'           => 'Bootcamp Registration',
            'keys'            => formSubmissionKeysForCanonical('bootcamp-registration'),
            'storage_tables'  => ['bootcamp_enrollments'],
        ],
        'payment' => [
            'label'           => 'Online Payment',
            'keys'            => formSubmissionKeysForCanonical('payment'),
            'storage_tables'  => ['payments'],
        ],
        'support-desk' => [
            'label'           => 'Support Desk',
            'keys'            => formSubmissionKeysForCanonical('support-ticket'),
            'storage_tables'  => ['support_tickets'],
        ],
        'product-demo' => [
            'label'           => 'Product demo',
            'keys'            => formSubmissionKeysForCanonical('product-demo'),
            'storage_tables'  => ['demo_requests'],
        ],
        'collaborate-project' => [
            'label'           => 'Collaboration',
            'keys'            => formSubmissionKeysForCanonical('collaborate-project'),
            'storage_tables'  => ['collaboration_inquiries'],
        ],
        'website-registration' => [
            'label'           => 'Website signup',
            'keys'            => formSubmissionKeysForCanonical('website-registration'),
            'storage_tables'  => ['website_users'],
        ],
        'platform-signup' => [
            'label'           => 'Platform signup',
            'keys'            => formSubmissionKeysForCanonical('platform-signup'),
            'storage_tables'  => ['users'],
        ],
        'freelancer-registration' => [
            'label'           => 'Freelancer',
            'keys'            => formSubmissionKeysForCanonical('freelancer-registration'),
            'storage_tables'  => ['freelancer_registrations'],
        ],
    ];
}

function adminFormSubmissionParam(string $value, int $maxLen = 80): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
    return substr($value, 0, $maxLen);
}

/**
 * @return array{keys: list<string>, storage_tables: list<string>}
 */
function resolveFormSubmissionAdminFilter(?string $category, ?string $formKey): array
{
    $formKey = $formKey !== null && $formKey !== '' ? normalizeFormSubmissionKey($formKey) : '';

    if ($formKey !== '') {
        return [
            'keys'           => formSubmissionKeysForCanonical($formKey),
            'storage_tables' => [],
        ];
    }

    $category = adminFormSubmissionParam($category ?? '');
    if ($category === '' || $category === 'all') {
        return ['keys' => [], 'storage_tables' => []];
    }

    $categories = getAdminFormSubmissionCategories();
    if (!isset($categories[$category])) {
        return ['keys' => [], 'storage_tables' => []];
    }

    return [
        'keys'           => $categories[$category]['keys'],
        'storage_tables' => $categories[$category]['storage_tables'],
    ];
}

function formSubmissionsWhereActive(string $alias = ''): string
{
    require_once __DIR__ . '/admin-actions.php';
    ensureAdminActionsSchema();
    if (!adminTableHasColumn('form_submissions', 'deleted_at')) {
        return '1=1';
    }
    return adminSqlActive($alias !== '' ? $alias : null);
}

/** One-time alignment of legacy form_key values in the database. */
function syncFormSubmissionKeysInDatabase(): void
{
    ensureFormSubmissionsSchema();
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    syncFormSubmissionReadFromSources();

    dbTry(static function () {
        $tableToKey = [
            'webinar_registrations'   => 'webinar-registration',
            'bootcamp_enrollments'    => 'bootcamp-registration',
            'payments'                => 'payment',
            'support_tickets'         => 'support-ticket',
            'demo_requests'           => 'product-demo',
            'collaboration_inquiries' => 'collaborate-project',
            'website_users'           => 'website-registration',
            'users'                   => 'platform-signup',
            'freelancer_registrations'=> 'freelancer-registration',
        ];

        $rows = db()->fetchAll('SELECT id, form_key, form_label, storage_table FROM form_submissions');
        foreach ($rows as $row) {
            $storageTable = (string) ($row['storage_table'] ?? '');
            $canonical = normalizeFormSubmissionKey((string) $row['form_key']);
            if ($storageTable !== '' && isset($tableToKey[$storageTable])) {
                $canonical = $tableToKey[$storageTable];
            }
            if ($canonical !== (string) $row['form_key']) {
                db()->execute('UPDATE form_submissions SET form_key = ? WHERE id = ?', [$canonical, (int) $row['id']]);
            }
        }

        $payments = db()->fetchAll(
            "SELECT p.*, u.full_name, u.email, u.phone FROM payments p
             LEFT JOIN users u ON u.id = p.user_id
             ORDER BY p.id DESC LIMIT 500"
        );
        foreach ($payments as $payment) {
            $exists = db()->fetchOne(
                'SELECT id FROM form_submissions WHERE storage_table = ? AND storage_record_id = ? LIMIT 1',
                ['payments', (int) $payment['id']]
            );
            if (!$exists) {
                recordPaymentFormSubmission($payment, $payment);
            }
        }
    }, null);
}

/**
 * @param array{
 *   form_key?: string,
 *   form_label: string,
 *   source_page?: string,
 *   full_name?: string,
 *   email?: string,
 *   phone?: string,
 *   summary?: string,
 *   payload?: array<string, mixed>|null,
 *   storage_table?: string,
 *   storage_record_id?: int|null
 * } $data
 */
function recordFormSubmission(array $data): int
{
    ensureFormSubmissionsSchema();

    $label = trim((string) ($data['form_label'] ?? 'Form'));
    $formKey = trim((string) ($data['form_key'] ?? ''));
    if ($formKey === '') {
        $formKey = formSubmissionKeyFromLabel($label);
    }
    $formKey = normalizeFormSubmissionKey($formKey);

    $payload = $data['payload'] ?? sanitizedFormPayload();
    $json = $payload === [] ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = null;
    }

    return db()->insert(
        'INSERT INTO form_submissions
        (form_key, form_label, source_page, full_name, email, phone, summary, payload_json,
         storage_table, storage_record_id, ip_address, user_agent)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $formKey,
            $label,
            $data['source_page'] ?? null,
            $data['full_name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['summary'] ?? null,
            $json,
            $data['storage_table'] ?? null,
            isset($data['storage_record_id']) ? (int) $data['storage_record_id'] : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]
    );
}

/** Alias for new forms — call after saving to any table. */
function cybeorchRecordFormSubmission(array $data): int
{
    return recordFormSubmission($data);
}

function getFormSubmissionCount(): int
{
    ensureFormSubmissionsSchema();
    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM form_submissions')['c'] ?? 0);
}

function getUnreadFormSubmissionCount(?array $typeFilter = null): int
{
    ensureFormSubmissionsSchema();
    $typeFilter = $typeFilter ?? ['keys' => [], 'storage_tables' => []];
    return countFormSubmissions($typeFilter, 'unread', '', 'active');
}

function getReadFormSubmissionCount(?array $typeFilter = null): int
{
    ensureFormSubmissionsSchema();
    $typeFilter = $typeFilter ?? ['keys' => [], 'storage_tables' => []];
    return countFormSubmissions($typeFilter, 'read', '', 'active');
}

/** Align is_read on form_submissions with linked contact_messages rows. */
function syncFormSubmissionReadFromSources(): void
{
    ensureFormSubmissionsSchema();
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    dbTry(static function () {
        db()->execute(
            'UPDATE form_submissions fs
             INNER JOIN contact_messages cm ON fs.storage_table = ? AND fs.storage_record_id = cm.id
             SET fs.is_read = cm.is_read
             WHERE ' . formSubmissionsWhereActive('fs'),
            ['contact_messages']
        );
    }, null);
}

/**
 * Parse admin read/unread filter from query string (avoids clashing with record status).
 */
function adminParseFormSubmissionReadFilter(): string
{
    $raw = (string) ($_GET['read_filter'] ?? $_GET['read'] ?? '');
    $filter = adminFormSubmissionParam($raw);
    if (in_array($filter, ['read', 'unread'], true)) {
        return $filter;
    }

    $legacyStatus = adminFormSubmissionParam((string) ($_GET['status'] ?? ''));
    if (in_array($legacyStatus, ['read', 'unread'], true)) {
        return $legacyStatus;
    }

    return '';
}

/**
 * Active vs deleted archive filter for form_submissions list.
 */
function adminParseFormSubmissionRecordStatus(): string
{
    $raw = (string) ($_GET['record_status'] ?? $_GET['status'] ?? 'active');
    $status = adminFormSubmissionParam($raw);
    if (in_array($status, ['read', 'unread'], true)) {
        return 'active';
    }
    return in_array($status, ['active', 'deleted'], true) ? $status : 'active';
}

/**
 * @param array{keys: list<string>, storage_tables: list<string>} $typeFilter
 * @return array{where: string, params: list<mixed>, order: string}
 */
function formSubmissionsFilterClause(
    array $typeFilter,
    ?string $readFilter = null,
    string $searchQ = '',
    string $recordStatus = 'active',
    string $sort = 'newest'
): array {
    ensureFormSubmissionsSchema();
    syncFormSubmissionKeysInDatabase();

    $parts = [];
    $params = [];

    if ($recordStatus === 'deleted') {
        $parts[] = 'deleted_at IS NOT NULL';
    } else {
        $parts[] = formSubmissionsWhereActive();
    }

    $keys = $typeFilter['keys'] ?? [];
    $tables = $typeFilter['storage_tables'] ?? [];
    if ($keys !== [] || $tables !== []) {
        $orParts = [];
        if ($keys !== []) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $orParts[] = 'form_key IN (' . $placeholders . ')';
            $params = array_merge($params, $keys);
        }
        if ($tables !== []) {
            $placeholders = implode(',', array_fill(0, count($tables), '?'));
            $orParts[] = 'storage_table IN (' . $placeholders . ')';
            $params = array_merge($params, $tables);
        }
        $parts[] = '(' . implode(' OR ', $orParts) . ')';
    }

    if ($readFilter === 'unread') {
        $parts[] = 'is_read = 0';
    } elseif ($readFilter === 'read') {
        $parts[] = 'is_read = 1';
    }

    $searchQ = trim($searchQ);
    if ($searchQ !== '') {
        $parts[] = '(full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR form_label LIKE ? OR summary LIKE ? OR payload_json LIKE ?)';
        $like = '%' . $searchQ . '%';
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    }

    $order = match ($sort) {
        'oldest'  => 'created_at ASC',
        'name'    => 'full_name ASC, created_at DESC',
        'name-desc' => 'full_name DESC, created_at DESC',
        default   => 'created_at DESC',
    };

    return ['where' => implode(' AND ', $parts), 'params' => $params, 'order' => $order];
}

function countFormSubmissions(
    array $typeFilter,
    ?string $readFilter = null,
    string $searchQ = '',
    string $recordStatus = 'active'
): int {
    $filter = formSubmissionsFilterClause($typeFilter, $readFilter, $searchQ, $recordStatus);
    $row = db()->fetchOne(
        'SELECT COUNT(*) AS c FROM form_submissions WHERE ' . $filter['where'],
        $filter['params']
    );
    return (int) ($row['c'] ?? 0);
}

/** @return array<int, array<string, mixed>> */
function getFormSubmissions(
    array $typeFilter,
    ?string $readFilter = null,
    int $limit = 500,
    string $searchQ = '',
    string $recordStatus = 'active',
    string $sort = 'newest'
): array {
    $filter = formSubmissionsFilterClause($typeFilter, $readFilter, $searchQ, $recordStatus, $sort);
    $limit = max(1, min(2000, $limit));
    return db()->fetchAll(
        'SELECT * FROM form_submissions WHERE ' . $filter['where']
        . ' ORDER BY ' . $filter['order'] . ' LIMIT ' . $limit,
        $filter['params']
    );
}

/**
 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
 */
function getFormSubmissionsPaginated(
    array $typeFilter,
    ?string $readFilter = null,
    string $searchQ = '',
    string $recordStatus = 'active',
    int $page = 1,
    int $perPage = 25,
    string $sort = 'newest'
): array {
    $perPage = max(10, min(100, $perPage));
    $page = max(1, $page);
    $total = countFormSubmissions($typeFilter, $readFilter, $searchQ, $recordStatus);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $filter = formSubmissionsFilterClause($typeFilter, $readFilter, $searchQ, $recordStatus, $sort);
    $rows = db()->fetchAll(
        'SELECT * FROM form_submissions WHERE ' . $filter['where']
        . ' ORDER BY ' . $filter['order'] . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
        $filter['params']
    );

    return [
        'rows'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ];
}

function getFormSubmissionById(int $id): ?array
{
    ensureFormSubmissionsSchema();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM form_submissions WHERE id = ?', [$id]);
    return $row ?: null;
}

function markFormSubmissionRead(int $id): void
{
    ensureFormSubmissionsSchema();
    db()->execute('UPDATE form_submissions SET is_read = 1 WHERE id = ?', [$id]);

    $row = db()->fetchOne(
        'SELECT storage_table, storage_record_id FROM form_submissions WHERE id = ?',
        [$id]
    );
    if ($row && $row['storage_table'] === 'contact_messages' && !empty($row['storage_record_id'])) {
        dbTry(static fn () => db()->execute(
            'UPDATE contact_messages SET is_read = 1 WHERE id = ?',
            [(int) $row['storage_record_id']]
        ), null);
    }
}

function markFormSubmissionUnread(int $id): void
{
    ensureFormSubmissionsSchema();
    db()->execute('UPDATE form_submissions SET is_read = 0 WHERE id = ?', [$id]);

    $row = db()->fetchOne(
        'SELECT storage_table, storage_record_id FROM form_submissions WHERE id = ?',
        [$id]
    );
    if ($row && $row['storage_table'] === 'contact_messages' && !empty($row['storage_record_id'])) {
        dbTry(static fn () => db()->execute(
            'UPDATE contact_messages SET is_read = 0 WHERE id = ?',
            [(int) $row['storage_record_id']]
        ), null);
    }
}

function setFormSubmissionReadState(int $id, bool $read): bool
{
    ensureFormSubmissionsSchema();
    if ($id < 1) {
        return false;
    }
    db()->execute('UPDATE form_submissions SET is_read = ? WHERE id = ?', [$read ? 1 : 0, $id]);
    return true;
}

/** @return array<string, int> category slug => count */
function getFormSubmissionCountsByCategory(): array
{
    ensureFormSubmissionsSchema();
    syncFormSubmissionKeysInDatabase();

    $counts = ['all' => countFormSubmissions(['keys' => [], 'storage_tables' => []])];
    foreach (getAdminFormSubmissionCategories() as $slug => $cfg) {
        if ($slug === 'all') {
            continue;
        }
        $counts[$slug] = countFormSubmissions([
            'keys'           => $cfg['keys'],
            'storage_tables' => $cfg['storage_tables'],
        ]);
    }
    return $counts;
}

/** @return array<string, int> form_key => count (active records only) */
function getFormSubmissionCountsByKey(): array
{
    ensureFormSubmissionsSchema();
    syncFormSubmissionKeysInDatabase();

    $counts = [];
    $rows = dbTry(
        static fn () => db()->fetchAll(
            'SELECT form_key, COUNT(*) AS c FROM form_submissions WHERE ' . formSubmissionsWhereActive()
            . ' GROUP BY form_key'
        ),
        []
    );
    foreach ($rows as $row) {
        $key = (string) ($row['form_key'] ?? '');
        if ($key !== '') {
            $counts[$key] = (int) ($row['c'] ?? 0);
        }
    }
    return $counts;
}

function formSubmissionDisplayMessage(array $row): string
{
    $summary = trim((string) ($row['summary'] ?? ''));
    if ($summary !== '') {
        return $summary;
    }

    $json = $row['payload_json'] ?? null;
    if ($json === null || $json === '') {
        return '';
    }

    $payload = json_decode((string) $json, true);
    if (!is_array($payload)) {
        return '';
    }

    foreach (['message', 'description', 'details', 'notes', 'enquiry', 'comments'] as $field) {
        if (!empty($payload[$field]) && is_scalar($payload[$field])) {
            return trim((string) $payload[$field]);
        }
    }

    foreach ($payload as $value) {
        if (is_string($value) && strlen($value) > 12) {
            return $value;
        }
    }

    return '';
}

function formSubmissionExcerpt(string $text, int $max = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if ($text === '') {
        return '—';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 1) . '…';
}

/** @return array<string, string> form_key => label */
function getKnownFormSubmissionTypes(): array
{
    $types = [];
    foreach (getFormSubmissionRegistryDefinitions() as $def) {
        $types[$def['form_key']] = $def['form_label'];
    }
    ensureFormSubmissionsSchema();
    $rows = dbTry(
        static fn () => db()->fetchAll(
            'SELECT DISTINCT form_key, form_label FROM form_submissions ORDER BY form_label ASC'
        ),
        []
    );
    foreach ($rows as $row) {
        $key = (string) ($row['form_key'] ?? '');
        if ($key !== '' && !isset($types[$key])) {
            $types[$key] = (string) ($row['form_label'] ?? $key);
        }
    }
    return $types;
}

/**
 * Static registry of forms (extend when adding new pages).
 *
 * @return list<array{form_key: string, form_label: string, page: string, table: string, admin: string}>
 */
function getFormSubmissionRegistryDefinitions(): array
{
    return [
        ['form_key' => 'contact', 'form_label' => 'Contact', 'page' => 'contact.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php'],
        ['form_key' => 'enquire-enroll', 'form_label' => 'Enquire & Enroll', 'page' => 'enquire-enroll.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php?subject=Enquire+%26+Enroll'],
        ['form_key' => 'assignment-register', 'form_label' => 'Hands-on Projects register', 'page' => 'assignment-register.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php?subject=Hands-on+Projects+Registration'],
        ['form_key' => 'apply-freelance', 'form_label' => 'Freelance apply', 'page' => 'apply-freelance.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php?subject=Freelance+Project+Application'],
        ['form_key' => 'start-project', 'form_label' => 'Start your project', 'page' => 'start-project.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php?subject=Start+Your+Project'],
        ['form_key' => 'book-consulting', 'form_label' => 'Book consulting', 'page' => 'book-consulting.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php?subject=Book+Consulting'],
        ['form_key' => 'secure-payment', 'form_label' => 'Secure payment', 'page' => 'secure-payment.php', 'table' => 'contact_messages', 'admin' => 'admin/messages.php?subject=Secure+Payment+%E2%80%93+Webinar+%2F+Bootcamp'],
        ['form_key' => 'product-demo', 'form_label' => 'Product demo', 'page' => 'products.php', 'table' => 'demo_requests', 'admin' => 'admin/demo-requests.php'],
        ['form_key' => 'collaborate-project', 'form_label' => 'Live project collaboration', 'page' => 'collaborate-project.php', 'table' => 'collaboration_inquiries', 'admin' => 'admin/collaboration-inquiries.php'],
        ['form_key' => 'website-registration', 'form_label' => 'Website popup registration', 'page' => 'index.php', 'table' => 'website_users', 'admin' => 'admin/website-registrations.php'],
        ['form_key' => 'platform-signup', 'form_label' => 'Platform signup (OTP)', 'page' => 'login.php', 'table' => 'users', 'admin' => 'admin/students.php'],
        ['form_key' => 'freelancer-registration', 'form_label' => 'Freelancer registration', 'page' => 'register-freelancer.php', 'table' => 'freelancer_registrations', 'admin' => 'admin/freelancers.php'],
        ['form_key' => 'webinar-registration', 'form_label' => 'Webinar registration', 'page' => 'checkout.php', 'table' => 'webinar_registrations', 'admin' => 'admin/registrations.php'],
        ['form_key' => 'bootcamp-registration', 'form_label' => 'Bootcamp registration', 'page' => 'secure-payment.php', 'table' => 'bootcamp_enrollments', 'admin' => 'admin/registrations.php'],
        ['form_key' => 'payment', 'form_label' => 'Online payment', 'page' => 'checkout.php', 'table' => 'payments', 'admin' => 'admin/payments.php'],
        ['form_key' => 'support-ticket', 'form_label' => 'Support desk', 'page' => 'supportdesk.php', 'table' => 'support_tickets', 'admin' => 'admin/tickets.php'],
    ];
}

/** @return array<int, array{form: string, page: string, table: string, admin: string}> */
function getFormSubmissionRegistry(): array
{
    $rows = [];
    foreach (getFormSubmissionRegistryDefinitions() as $def) {
        $rows[] = [
            'form'  => $def['form_label'],
            'page'  => $def['page'],
            'table' => $def['table'],
            'admin' => $def['admin'],
        ];
    }
    return $rows;
}

/**
 * @param array<string, mixed> $context
 */
function recordWebinarRegistrationForm(int $registrationId, array $context): void
{
    recordFormSubmission([
        'form_key'          => 'webinar-registration',
        'form_label'        => 'Webinar registration',
        'source_page'       => 'checkout.php',
        'full_name'           => $context['full_name'] ?? null,
        'email'             => $context['email'] ?? null,
        'phone'             => $context['phone'] ?? null,
        'summary'           => ($context['title'] ?? 'Webinar') . ' — ' . ($context['registration_no'] ?? ''),
        'payload'           => $context,
        'storage_table'     => 'webinar_registrations',
        'storage_record_id' => $registrationId,
    ]);
}

/**
 * @param array<string, mixed> $context
 */
function recordBootcampRegistrationForm(int $enrollmentId, array $context): void
{
    recordFormSubmission([
        'form_key'          => 'bootcamp-registration',
        'form_label'        => 'Bootcamp registration',
        'source_page'       => 'checkout.php',
        'full_name'         => $context['full_name'] ?? null,
        'email'             => $context['email'] ?? null,
        'phone'             => $context['phone'] ?? null,
        'summary'           => ($context['title'] ?? 'Bootcamp') . ' — ' . ($context['enrollment_no'] ?? ''),
        'payload'           => $context,
        'storage_table'     => 'bootcamp_enrollments',
        'storage_record_id' => $enrollmentId,
    ]);
}

/**
 * @param array<string, mixed> $payment payments row
 * @param array<string, mixed>|null $user users row (full_name, email, phone)
 */
function recordPaymentFormSubmission(array $payment, ?array $user = null): void
{
    $user = $user ?? dbTry(
        static fn () => db()->fetchOne(
            'SELECT full_name, email, phone FROM users WHERE id = ?',
            [(int) ($payment['user_id'] ?? 0)]
        ),
        null
    );

    $payFor = (string) ($payment['payment_for'] ?? '');
    $refId = (int) ($payment['reference_id'] ?? 0);
    $program = 'Payment';
    if ($payFor === 'webinar' && $refId > 0) {
        $program = (string) (dbTry(
            static fn () => db()->fetchOne('SELECT title FROM webinars WHERE id = ?', [$refId])['title'] ?? '',
            ''
        ) ?: 'Webinar');
    } elseif ($payFor === 'bootcamp' && $refId > 0) {
        $program = (string) (dbTry(
            static fn () => db()->fetchOne('SELECT title FROM bootcamps WHERE id = ?', [$refId])['title'] ?? '',
            ''
        ) ?: 'Bootcamp');
    }

    recordFormSubmission([
        'form_key'          => 'payment',
        'form_label'        => 'Online payment',
        'source_page'       => 'checkout.php',
        'full_name'         => $user['full_name'] ?? null,
        'email'             => $user['email'] ?? null,
        'phone'             => $user['phone'] ?? null,
        'summary'           => $program . ' — ₹' . number_format((float) ($payment['amount'] ?? 0), 2)
            . ' — ' . strtoupper((string) ($payment['status'] ?? ''))
            . ' — ' . ($payment['order_id'] ?? ''),
        'payload'           => [
            'order_id'            => $payment['order_id'] ?? null,
            'razorpay_order_id'   => $payment['razorpay_order_id'] ?? null,
            'razorpay_payment_id' => $payment['razorpay_payment_id'] ?? null,
            'amount'              => $payment['amount'] ?? null,
            'status'              => $payment['status'] ?? null,
            'payment_for'         => $payFor,
            'invoice_no'          => $payment['invoice_no'] ?? null,
        ],
        'storage_table'     => 'payments',
        'storage_record_id' => (int) ($payment['id'] ?? 0),
    ]);
}
