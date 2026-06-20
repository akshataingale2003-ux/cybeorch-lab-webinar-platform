<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function ensureInvoiceAmcSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute('CREATE TABLE IF NOT EXISTS clients (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        client_code VARCHAR(20) NOT NULL,
        name VARCHAR(200) NOT NULL,
        email VARCHAR(180) NOT NULL,
        phone VARCHAR(30) DEFAULT NULL,
        company VARCHAR(200) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        gst_number VARCHAR(50) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_clients_code (client_code),
        INDEX idx_clients_email (email(120)),
        INDEX idx_clients_name (name(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_no VARCHAR(30) NOT NULL,
        client_id INT UNSIGNED NOT NULL,
        invoice_date DATE NOT NULL,
        project_type VARCHAR(100) NOT NULL DEFAULT \'Custom Projects\',
        project_name VARCHAR(255) NOT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
        subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
        discount DECIMAL(14,2) NOT NULL DEFAULT 0,
        tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        status ENUM(\'pending\',\'paid\',\'cancelled\') NOT NULL DEFAULT \'pending\',
        payment_terms VARCHAR(500) DEFAULT \'50% Advance | 30% Milestone | 20% On Delivery\',
        validity_days INT NOT NULL DEFAULT 30,
        payment_methods VARCHAR(500) DEFAULT \'Bank / Crypto / USDT / Wire Transfer (As per agreement)\',
        pdf_path VARCHAR(500) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_invoices_no (invoice_no),
        INDEX idx_invoices_client (client_id),
        INDEX idx_invoices_status (status),
        INDEX idx_invoices_date (invoice_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS invoice_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNSIGNED NOT NULL,
        description VARCHAR(500) NOT NULL,
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice_items_invoice (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS invoice_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNSIGNED NOT NULL,
        amount DECIMAL(14,2) NOT NULL,
        payment_method VARCHAR(100) DEFAULT NULL,
        payment_date DATE NOT NULL,
        reference_no VARCHAR(120) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_invoice_payments_invoice (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS amc_contracts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        amc_no VARCHAR(30) NOT NULL,
        client_id INT UNSIGNED NOT NULL,
        project_name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        renewal_date DATE NOT NULL,
        duration_months INT NOT NULL DEFAULT 12,
        duration_label VARCHAR(50) NOT NULL DEFAULT \'1 Year\',
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
        status ENUM(\'active\',\'expiring\',\'expired\',\'renewed\',\'cancelled\') NOT NULL DEFAULT \'active\',
        pdf_path VARCHAR(500) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        renewed_from_id INT UNSIGNED DEFAULT NULL,
        created_by INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_amc_no (amc_no),
        INDEX idx_amc_client (client_id),
        INDEX idx_amc_status (status),
        INDEX idx_amc_end_date (end_date),
        INDEX idx_amc_renewal_date (renewal_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS amc_renewals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        amc_contract_id INT UNSIGNED NOT NULL,
        reminder_days INT NOT NULL,
        sent_at DATETIME NOT NULL,
        email_log_id INT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_amc_reminder (amc_contract_id, reminder_days),
        INDEX idx_amc_renewals_contract (amc_contract_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS email_logs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT UNSIGNED NOT NULL,
        email_type VARCHAR(50) NOT NULL,
        recipient_email VARCHAR(180) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        status ENUM(\'sent\',\'failed\') NOT NULL DEFAULT \'sent\',
        error_message TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_logs_entity (entity_type, entity_id),
        INDEX idx_email_logs_type (email_type),
        INDEX idx_email_logs_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    invoiceAmcEnsureUploadDirs();

    if (function_exists('ensureAdminActionsSchema')) {
        ensureAdminActionsSchema();
    }
}

function invoiceAmcEnsureUploadDirs(): void
{
    foreach (['invoices', 'amc'] as $sub) {
        $dir = invoiceAmcUploadsRoot() . DIRECTORY_SEPARATOR . $sub;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents(
                $htaccess,
                "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps)$\">\n  Require all denied\n</FilesMatch>\n"
            );
        }
    }
}

function invoiceAmcUploadsRoot(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads';
}

/** @return array<string, string> */
function invoiceProjectTypeOptions(): array
{
    return [
        'Website Development'         => 'Website Development',
        'Web Application'             => 'Web Application',
        'Mobile App'                  => 'Mobile App',
        'Blockchain Development'      => 'Blockchain Development',
        'Smart Contract Development'  => 'Smart Contract Development',
        'Security Audit'              => 'Security Audit',
        'Digital Marketing'           => 'Digital Marketing',
        'Custom Projects'             => 'Custom Projects',
    ];
}

/** @return array<string, string> */
function invoiceAmcDurationOptions(): array
{
    return [
        '6'   => '6 Months',
        '12'  => '1 Year',
        '24'  => '2 Years',
        'custom' => 'Custom',
    ];
}

/** @return array<string, string> */
function invoiceCurrencyOptions(): array
{
    return ['USD' => 'USD', 'INR' => 'INR', 'EUR' => 'EUR'];
}

function invoiceAmcGenerateClientCode(): string
{
    ensureInvoiceAmcSchema();
    $row = db()->fetchOne('SELECT client_code FROM clients ORDER BY id DESC LIMIT 1');
    $next = 1;
    if ($row && preg_match('/CYB-CL-(\d+)/', (string) $row['client_code'], $m)) {
        $next = (int) $m[1] + 1;
    } elseif ($row) {
        $next = (int) db()->fetchOne('SELECT COUNT(*) AS c FROM clients')['c'] + 1;
    }
    return 'CYB-CL-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function invoiceAmcGenerateInvoiceNo(?int $year = null): string
{
    ensureInvoiceAmcSchema();
    $year = $year ?? (int) date('Y');
    $prefix = 'CYB/INV/' . $year . '/';
    $row = db()->fetchOne(
        'SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1',
        [$prefix . '%']
    );
    $next = 1;
    if ($row && preg_match('/\/(\d+)$/', (string) $row['invoice_no'], $m)) {
        $next = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function invoiceAmcGenerateAmcNo(?int $year = null): string
{
    ensureInvoiceAmcSchema();
    $year = $year ?? (int) date('Y');
    $prefix = 'CYB/AMC/' . $year . '/';
    $row = db()->fetchOne(
        'SELECT amc_no FROM amc_contracts WHERE amc_no LIKE ? ORDER BY id DESC LIMIT 1',
        [$prefix . '%']
    );
    $next = 1;
    if ($row && preg_match('/\/(\d+)$/', (string) $row['amc_no'], $m)) {
        $next = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function invoiceAmcCalculateEndDate(string $startDate, int $months): string
{
    $dt = new DateTimeImmutable($startDate);
    return $dt->modify('+' . $months . ' months')->modify('-1 day')->format('Y-m-d');
}

function invoiceAmcActiveClause(?string $alias = null, string $table = ''): string
{
    if (function_exists('adminSqlActive')) {
        return adminSqlActive($alias, false, $table !== '' ? $table : null);
    }
    $p = $alias !== null && $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return '(' . $p . "deleted_at IS NULL OR " . $p . "deleted_at = '0000-00-00 00:00:00')";
}

/**
 * @param array{
 *   client_mode:string, client_id?:int, name?:string, email?:string, phone?:string,
 *   company?:string, address?:string, gst_number?:string
 * } $data
 */
function invoiceAmcResolveClient(array $data): int
{
    ensureInvoiceAmcSchema();
    $mode = $data['client_mode'] ?? 'existing';

    if ($mode === 'existing') {
        $clientId = (int) ($data['client_id'] ?? 0);
        $client = $clientId > 0 ? invoiceAmcGetClient($clientId) : null;
        if (!$client || !empty($client['deleted_at'])) {
            throw new InvalidArgumentException('Please select a valid client.');
        }
        if (!empty($client['is_blocked'])) {
            throw new InvalidArgumentException('This client is blocked. Cannot create invoices or AMC contracts.');
        }
        return $clientId;
    }

    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Client name and valid email are required.');
    }

    return (int) db()->insert(
        'INSERT INTO clients (client_code, name, email, phone, company, address, gst_number) VALUES (?,?,?,?,?,?,?)',
        [
            invoiceAmcGenerateClientCode(),
            $name,
            $email,
            trim((string) ($data['phone'] ?? '')) ?: null,
            trim((string) ($data['company'] ?? '')) ?: null,
            trim((string) ($data['address'] ?? '')) ?: null,
            trim((string) ($data['gst_number'] ?? '')) ?: null,
        ]
    );
}

/** @return array<int, array<string, mixed>> */
function invoiceAmcListClients(string $q = ''): array
{
    ensureInvoiceAmcSchema();
    $params = [];
    $where = '1=1';
    if ($q !== '') {
        $where .= ' AND (name LIKE ? OR email LIKE ? OR client_code LIKE ? OR company LIKE ?)';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like];
    }
    $active = invoiceAmcActiveClause(null, 'clients');
    return db()->fetchAll(
        "SELECT * FROM clients WHERE {$where} AND {$active} ORDER BY name ASC, id DESC LIMIT 500",
        $params
    );
}

function invoiceAmcGetClient(int $id): ?array
{
    ensureInvoiceAmcSchema();
    $active = invoiceAmcActiveClause(null, 'clients');
    return db()->fetchOne('SELECT * FROM clients WHERE id = ? AND ' . $active, [$id]);
}

/** @return array<int, array<string, mixed>> */
function invoiceAmcListClientInvoices(int $clientId): array
{
    ensureInvoiceAmcSchema();
    if ($clientId < 1) {
        return [];
    }
    $active = invoiceAmcActiveClause('i', 'invoices');
    return db()->fetchAll(
        "SELECT i.* FROM invoices i WHERE i.client_id = ? AND {$active} ORDER BY i.invoice_date DESC, i.id DESC LIMIT 100",
        [$clientId]
    );
}

/** @return array<int, array<string, mixed>> */
function invoiceAmcListClientAmc(int $clientId): array
{
    ensureInvoiceAmcSchema();
    if ($clientId < 1) {
        return [];
    }
    $active = invoiceAmcActiveClause('a', 'amc_contracts');
    return db()->fetchAll(
        "SELECT a.* FROM amc_contracts a WHERE a.client_id = ? AND {$active} ORDER BY a.end_date DESC, a.id DESC LIMIT 100",
        [$clientId]
    );
}

/**
 * @param array<string, mixed> $post
 * @return array{ok:bool, error?:string}
 */
function invoiceAmcUpdateClient(int $clientId, array $post): array
{
    ensureInvoiceAmcSchema();
    $client = invoiceAmcGetClient($clientId);
    if (!$client || !empty($client['deleted_at'])) {
        return ['ok' => false, 'error' => 'Client not found.'];
    }
    $name = trim((string) ($post['name'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Valid name and email are required.'];
    }
    db()->execute(
        'UPDATE clients SET name=?, email=?, phone=?, company=?, address=?, gst_number=?, notes=?, updated_at=NOW() WHERE id=?',
        [
            $name,
            $email,
            trim((string) ($post['phone'] ?? '')) ?: null,
            trim((string) ($post['company'] ?? '')) ?: null,
            trim((string) ($post['address'] ?? '')) ?: null,
            trim((string) ($post['gst_number'] ?? '')) ?: null,
            trim((string) ($post['notes'] ?? '')) ?: null,
            $clientId,
        ]
    );
    return ['ok' => true];
}

/**
 * @param array<string, mixed> $post
 * @return array{ok:bool, error?:string}
 */
function invoiceAmcUpdateInvoice(int $invoiceId, array $post): array
{
    ensureInvoiceAmcSchema();
    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice || !empty($invoice['deleted_at'])) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }
    $projectName = trim((string) ($post['project_name'] ?? ''));
    if ($projectName === '') {
        return ['ok' => false, 'error' => 'Project name is required.'];
    }
    $status = strtolower(trim((string) ($post['status'] ?? $invoice['status'])));
    if (!in_array($status, ['pending', 'paid', 'cancelled'], true)) {
        $status = (string) $invoice['status'];
    }
    if ($invoice['status'] === 'paid' && $status !== 'paid') {
        $status = 'paid';
    }
    db()->execute(
        'UPDATE invoices SET project_name=?, project_type=?, payment_terms=?, validity_days=?, payment_methods=?, notes=?, status=?, updated_at=NOW() WHERE id=?',
        [
            $projectName,
            trim((string) ($post['project_type'] ?? $invoice['project_type'])) ?: 'Custom Projects',
            trim((string) ($post['payment_terms'] ?? '')) ?: null,
            max(1, (int) ($post['validity_days'] ?? $invoice['validity_days'])),
            trim((string) ($post['payment_methods'] ?? '')) ?: null,
            trim((string) ($post['notes'] ?? '')) ?: null,
            $status,
            $invoiceId,
        ]
    );
    return ['ok' => true];
}

/**
 * @param array<string, mixed> $post
 * @return array{ok:bool, error?:string}
 */
function invoiceAmcUpdateAmc(int $amcId, array $post): array
{
    ensureInvoiceAmcSchema();
    $amc = invoiceAmcGetAmc($amcId);
    if (!$amc || !empty($amc['deleted_at'])) {
        return ['ok' => false, 'error' => 'AMC contract not found.'];
    }
    $projectName = trim((string) ($post['project_name'] ?? ''));
    if ($projectName === '') {
        return ['ok' => false, 'error' => 'Project name is required.'];
    }
    $status = strtolower(trim((string) ($post['status'] ?? $amc['status'])));
    if (!in_array($status, ['active', 'expiring', 'expired', 'renewed', 'cancelled'], true)) {
        $status = (string) $amc['status'];
    }
    db()->execute(
        'UPDATE amc_contracts SET project_name=?, description=?, amount=?, currency=?, notes=?, status=?, updated_at=NOW() WHERE id=?',
        [
            $projectName,
            trim((string) ($post['description'] ?? '')) ?: null,
            max(0, (float) ($post['amount'] ?? $amc['amount'])),
            strtoupper(trim((string) ($post['currency'] ?? $amc['currency']))) ?: 'USD',
            trim((string) ($post['notes'] ?? '')) ?: null,
            $status,
            $amcId,
        ]
    );
    return ['ok' => true];
}

/**
 * @param array<string, mixed> $post
 * @return array{ok:bool, id?:int, error?:string}
 */
function invoiceAmcCreateInvoice(array $post, int $adminId): array
{
    ensureInvoiceAmcSchema();
    require_once __DIR__ . '/invoice-pdf.php';
    require_once __DIR__ . '/invoice-mailer.php';

    try {
        $clientId = invoiceAmcResolveClient($post);
        $projectType = trim((string) ($post['project_type'] ?? 'Custom Projects'));
        $projectName = trim((string) ($post['project_name'] ?? ''));
        if ($projectName === '') {
            throw new InvalidArgumentException('Project name is required.');
        }

        $items = invoiceAmcParseLineItems($post);
        if ($items === []) {
            throw new InvalidArgumentException('Add at least one line item.');
        }

        $subtotal = round(array_sum(array_column($items, 'amount')), 2);
        $discount = max(0, round((float) ($post['discount'] ?? 0), 2));
        $taxRate = max(0, round((float) ($post['tax_rate'] ?? 0), 2));
        $taxable = max(0, $subtotal - $discount);
        $taxAmount = round($taxable * ($taxRate / 100), 2);
        $total = round($taxable + $taxAmount, 2);

        $currency = strtoupper(trim((string) ($post['currency'] ?? 'USD')));
        if (!isset(invoiceCurrencyOptions()[$currency])) {
            $currency = 'USD';
        }

        $invoiceDate = trim((string) ($post['invoice_date'] ?? ''));
        if ($invoiceDate === '') {
            $invoiceDate = date('Y-m-d');
        }

        db()->beginTransaction();

        $invoiceNo = invoiceAmcGenerateInvoiceNo((int) date('Y', strtotime($invoiceDate)));
        $invoiceId = (int) db()->insert(
            'INSERT INTO invoices
            (invoice_no, client_id, invoice_date, project_type, project_name, currency,
             subtotal, discount, tax_rate, tax_amount, total_amount, status,
             payment_terms, validity_days, payment_methods, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $invoiceNo,
                $clientId,
                $invoiceDate,
                $projectType,
                $projectName,
                $currency,
                $subtotal,
                $discount,
                $taxRate,
                $taxAmount,
                $total,
                'pending',
                trim((string) ($post['payment_terms'] ?? '50% Advance | 30% Milestone | 20% On Delivery')),
                max(1, (int) ($post['validity_days'] ?? 30)),
                trim((string) ($post['payment_methods'] ?? 'Bank / Crypto / USDT / Wire Transfer (As per agreement)')),
                trim((string) ($post['notes'] ?? '')) ?: null,
                $adminId > 0 ? $adminId : null,
            ]
        );

        $sort = 0;
        foreach ($items as $item) {
            db()->insert(
                'INSERT INTO invoice_items (invoice_id, description, amount, sort_order) VALUES (?,?,?,?)',
                [$invoiceId, $item['description'], $item['amount'], ++$sort]
            );
        }

        db()->commit();

        $pdfResult = invoiceAmcEnsureInvoicePdf($invoiceId);
        $emailResult = invoiceAmcSendInvoiceCreatedEmail($invoiceId, $pdfResult['path'] ?? null);

        return [
            'ok'          => true,
            'id'          => $invoiceId,
            'pdf'         => !empty($pdfResult['ok']),
            'pdf_error'   => $pdfResult['error'] ?? null,
            'email_sent'  => !empty($emailResult['ok']),
            'email_error' => $emailResult['error'] ?? null,
        ];
    } catch (Throwable $e) {
        db()->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * @param array<string, mixed> $post
 * @return array<int, array{description:string, amount:float}>
 */
function invoiceAmcParseLineItems(array $post): array
{
    $descriptions = $post['item_description'] ?? [];
    $amounts = $post['item_amount'] ?? [];
    if (!is_array($descriptions)) {
        $descriptions = [$descriptions];
    }
    if (!is_array($amounts)) {
        $amounts = [$amounts];
    }

    $items = [];
    $count = max(count($descriptions), count($amounts));
    for ($i = 0; $i < $count; $i++) {
        $desc = trim((string) ($descriptions[$i] ?? ''));
        $amount = round((float) ($amounts[$i] ?? 0), 2);
        if ($desc === '' && $amount <= 0) {
            continue;
        }
        if ($desc === '') {
            $desc = 'Service';
        }
        $items[] = ['description' => $desc, 'amount' => $amount];
    }
    return $items;
}

/**
 * @param array<string, mixed> $post
 * @return array{ok:bool, id?:int, error?:string}
 */
function invoiceAmcCreateAmc(array $post, int $adminId): array
{
    ensureInvoiceAmcSchema();
    require_once __DIR__ . '/amc-pdf.php';
    require_once __DIR__ . '/invoice-mailer.php';

    try {
        $clientId = invoiceAmcResolveClient($post);
        $projectName = trim((string) ($post['project_name'] ?? ''));
        if ($projectName === '') {
            throw new InvalidArgumentException('Project name is required.');
        }

        $startDate = trim((string) ($post['start_date'] ?? ''));
        if ($startDate === '') {
            $startDate = date('Y-m-d');
        }

        $durationKey = (string) ($post['duration'] ?? '12');
        $durationMonths = match ($durationKey) {
            '6'      => 6,
            '12'     => 12,
            '24'     => 24,
            'custom' => max(1, (int) ($post['custom_months'] ?? 12)),
            default  => 12,
        };
        $durationLabel = invoiceAmcDurationOptions()[$durationKey] ?? ($durationMonths . ' Months');
        if ($durationKey === 'custom') {
            $durationLabel = $durationMonths . ' Months (Custom)';
        }

        $endDate = invoiceAmcCalculateEndDate($startDate, $durationMonths);
        $renewalDate = $endDate;
        $amount = round((float) ($post['amount'] ?? 0), 2);
        $currency = strtoupper(trim((string) ($post['currency'] ?? 'USD')));
        if (!isset(invoiceCurrencyOptions()[$currency])) {
            $currency = 'USD';
        }

        db()->beginTransaction();

        $amcNo = invoiceAmcGenerateAmcNo((int) date('Y', strtotime($startDate)));
        $amcId = (int) db()->insert(
            'INSERT INTO amc_contracts
            (amc_no, client_id, project_name, description, start_date, end_date, renewal_date,
             duration_months, duration_label, amount, currency, status, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $amcNo,
                $clientId,
                $projectName,
                trim((string) ($post['description'] ?? '')) ?: null,
                $startDate,
                $endDate,
                $renewalDate,
                $durationMonths,
                $durationLabel,
                $amount,
                $currency,
                'active',
                trim((string) ($post['notes'] ?? '')) ?: null,
                $adminId > 0 ? $adminId : null,
            ]
        );

        db()->commit();

        $pdfResult = invoiceAmcEnsureAmcPdf($amcId);
        $emailResult = invoiceAmcSendAmcCreatedEmail($amcId, $pdfResult['path'] ?? null);

        return [
            'ok'          => true,
            'id'          => $amcId,
            'pdf'         => !empty($pdfResult['ok']),
            'pdf_error'   => $pdfResult['error'] ?? null,
            'email_sent'  => !empty($emailResult['ok']),
            'email_error' => $emailResult['error'] ?? null,
        ];
    } catch (Throwable $e) {
        db()->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function invoiceAmcMarkInvoicePaid(int $invoiceId, array $payment = []): array
{
    ensureInvoiceAmcSchema();
    require_once __DIR__ . '/invoice-mailer.php';

    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }
    if (($invoice['status'] ?? '') === 'paid') {
        return ['ok' => false, 'error' => 'Invoice is already marked as paid.'];
    }

    $amount = round((float) ($payment['amount'] ?? $invoice['total_amount']), 2);
    $paymentDate = trim((string) ($payment['payment_date'] ?? date('Y-m-d')));

    db()->beginTransaction();
    try {
        db()->execute(
            'UPDATE invoices SET status = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ?',
            ['paid', $invoiceId]
        );
        db()->insert(
            'INSERT INTO invoice_payments (invoice_id, amount, payment_method, payment_date, reference_no, notes)
             VALUES (?,?,?,?,?,?)',
            [
                $invoiceId,
                $amount,
                trim((string) ($payment['payment_method'] ?? '')) ?: null,
                $paymentDate,
                trim((string) ($payment['reference_no'] ?? '')) ?: null,
                trim((string) ($payment['notes'] ?? '')) ?: null,
            ]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $client = invoiceAmcGetClient((int) $invoice['client_id']);
    $pdfPath = (string) ($invoice['pdf_path'] ?? '');
    if ($client && $pdfPath !== '' && is_file($pdfPath)) {
        invoiceMailerSendInvoicePaid($invoiceId, $client, $pdfPath);
    }

    return ['ok' => true];
}

function invoiceAmcGetInvoice(int $id): ?array
{
    ensureInvoiceAmcSchema();
    $active = invoiceAmcActiveClause('i', 'invoices');
    return db()->fetchOne(
        'SELECT i.*, c.client_code, c.name AS client_name, c.email AS client_email,
                c.phone AS client_phone, c.company AS client_company
         FROM invoices i
         JOIN clients c ON c.id = i.client_id
         WHERE i.id = ? AND ' . $active,
        [$id]
    );
}

/** @return array<int, array<string, mixed>> */
function invoiceAmcGetInvoiceItems(int $invoiceId): array
{
    ensureInvoiceAmcSchema();
    return db()->fetchAll(
        'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC, id ASC',
        [$invoiceId]
    );
}

/** @return array<int, array<string, mixed>> */
function invoiceAmcListInvoices(array $filters = []): array
{
    ensureInvoiceAmcSchema();
    $status = trim((string) ($filters['status'] ?? ''));
    $q = trim((string) ($filters['q'] ?? ''));
    $where = ['1=1'];
    $params = [];

    if ($status !== '' && $status !== 'all') {
        $where[] = 'i.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $where[] = '(i.invoice_no LIKE ? OR c.name LIKE ? OR c.client_code LIKE ? OR i.project_name LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $active = invoiceAmcActiveClause('i', 'invoices');

    return db()->fetchAll(
        'SELECT i.*, c.client_code, c.name AS client_name, c.email AS client_email
         FROM invoices i
         JOIN clients c ON c.id = i.client_id
         WHERE ' . implode(' AND ', $where) . ' AND ' . $active . '
         ORDER BY i.created_at DESC
         LIMIT 500',
        $params
    );
}

function invoiceAmcGetAmc(int $id): ?array
{
    ensureInvoiceAmcSchema();
    $active = invoiceAmcActiveClause('a', 'amc_contracts');
    return db()->fetchOne(
        'SELECT a.*, c.client_code, c.name AS client_name, c.email AS client_email,
                c.phone AS client_phone, c.company AS client_company
         FROM amc_contracts a
         JOIN clients c ON c.id = a.client_id
         WHERE a.id = ? AND ' . $active,
        [$id]
    );
}

/** @return array<int, array<string, mixed>> */
function invoiceAmcListAmc(array $filters = []): array
{
    ensureInvoiceAmcSchema();
    invoiceAmcRefreshAmcStatuses();

    $status = trim((string) ($filters['status'] ?? ''));
    $q = trim((string) ($filters['q'] ?? ''));
    $where = ['1=1'];
    $params = [];

    if ($status !== '' && $status !== 'all') {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $where[] = '(a.amc_no LIKE ? OR c.name LIKE ? OR c.client_code LIKE ? OR a.project_name LIKE ?)';
        $like = '%' . $q . '%';
        $params = array_merge($params, [$like, $like, $like, $like]);
    }

    $active = invoiceAmcActiveClause('a', 'amc_contracts');

    return db()->fetchAll(
        'SELECT a.*, c.client_code, c.name AS client_name, c.email AS client_email
         FROM amc_contracts a
         JOIN clients c ON c.id = a.client_id
         WHERE ' . implode(' AND ', $where) . ' AND ' . $active . '
         ORDER BY a.end_date ASC, a.created_at DESC
         LIMIT 500',
        $params
    );
}

function invoiceAmcRefreshAmcStatuses(): void
{
    ensureInvoiceAmcSchema();
    $active = invoiceAmcActiveClause(null, 'amc_contracts');
    try {
        db()->execute(
            "UPDATE amc_contracts SET status = 'expired', updated_at = NOW()
             WHERE {$active} AND status IN ('active','expiring') AND end_date < CURDATE()"
        );
        db()->execute(
            "UPDATE amc_contracts SET status = 'expiring', updated_at = NOW()
             WHERE {$active} AND status = 'active' AND end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
        );
    } catch (Throwable $e) {
        // table may not exist yet during first bootstrap
    }
}

function invoiceAmcRenewAmc(int $amcId, int $adminId): array
{
    ensureInvoiceAmcSchema();
    require_once __DIR__ . '/amc-pdf.php';
    require_once __DIR__ . '/invoice-mailer.php';

    $old = invoiceAmcGetAmc($amcId);
    if (!$old) {
        return ['ok' => false, 'error' => 'AMC contract not found.'];
    }

    $startDate = date('Y-m-d', strtotime((string) $old['end_date'] . ' +1 day'));
    $durationMonths = (int) $old['duration_months'];
    $endDate = invoiceAmcCalculateEndDate($startDate, $durationMonths);

    db()->beginTransaction();
    try {
        db()->execute(
            "UPDATE amc_contracts SET status = 'renewed', updated_at = NOW() WHERE id = ?",
            [$amcId]
        );

        $amcNo = invoiceAmcGenerateAmcNo((int) date('Y'));
        $newId = (int) db()->insert(
            'INSERT INTO amc_contracts
            (amc_no, client_id, project_name, description, start_date, end_date, renewal_date,
             duration_months, duration_label, amount, currency, status, notes, renewed_from_id, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $amcNo,
                (int) $old['client_id'],
                (string) $old['project_name'],
                (string) ($old['description'] ?? ''),
                $startDate,
                $endDate,
                $endDate,
                $durationMonths,
                (string) $old['duration_label'],
                (float) $old['amount'],
                (string) $old['currency'],
                'active',
                (string) ($old['notes'] ?? ''),
                $amcId,
                $adminId > 0 ? $adminId : null,
            ]
        );
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $pdfResult = invoiceAmcEnsureAmcPdf($newId);
    $emailResult = invoiceAmcSendAmcCreatedEmail($newId, $pdfResult['path'] ?? null);

    return [
        'ok'          => true,
        'id'          => $newId,
        'pdf'         => !empty($pdfResult['ok']),
        'pdf_error'   => $pdfResult['error'] ?? null,
        'email_sent'  => !empty($emailResult['ok']),
        'email_error' => $emailResult['error'] ?? null,
    ];
}

/**
 * @return array{ok:bool, path?:string, error?:string}
 */
function invoiceAmcEnsureInvoicePdf(int $invoiceId): array
{
    require_once __DIR__ . '/invoice-pdf.php';

    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    $path = (string) ($invoice['pdf_path'] ?? '');

    if (!extension_loaded('gd')) {
        return [
            'ok'    => false,
            'error' => 'PHP GD extension is required for PDF generation. Enable extension=gd in php.ini and restart Apache.',
        ];
    }

    try {
        // Always regenerate to ensure latest template/content changes are reflected
        // across download, print, preview, and email attachment flows.
        $path = invoicePdfGenerateAndSave($invoiceId);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()];
    }

    if ($path === null || !is_file($path)) {
        return ['ok' => false, 'error' => 'PDF could not be generated or saved to disk.'];
    }

    db()->execute('UPDATE invoices SET pdf_path = ? WHERE id = ?', [$path, $invoiceId]);

    return ['ok' => true, 'path' => $path];
}

/**
 * @return array{ok:bool, path?:string, error?:string}
 */
function invoiceAmcEnsureAmcPdf(int $amcId): array
{
    require_once __DIR__ . '/amc-pdf.php';

    $amc = invoiceAmcGetAmc($amcId);
    if (!$amc) {
        return ['ok' => false, 'error' => 'AMC contract not found.'];
    }

    $path = (string) ($amc['pdf_path'] ?? '');

    if (!extension_loaded('gd')) {
        return [
            'ok'    => false,
            'error' => 'PHP GD extension is required for PDF generation. Enable extension=gd in php.ini and restart Apache.',
        ];
    }

    try {
        // Always regenerate to ensure latest template/content changes are reflected
        // across download, print, preview, and email attachment flows.
        $path = amcPdfGenerateAndSave($amcId);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()];
    }

    if ($path === null || !is_file($path)) {
        return ['ok' => false, 'error' => 'PDF could not be generated or saved to disk.'];
    }

    db()->execute('UPDATE amc_contracts SET pdf_path = ? WHERE id = ?', [$path, $amcId]);

    return ['ok' => true, 'path' => $path];
}

function invoiceAmcValidateRecipientEmail(string $email): ?string
{
    $email = trim($email);
    if ($email === '') {
        return 'Client email address is empty.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Client email address is invalid: ' . $email;
    }

    return null;
}

/**
 * @return array{ok:bool, error?:string, attempted?:bool}
 */
function invoiceAmcSendInvoiceCreatedEmail(int $invoiceId, ?string $pdfPath = null): array
{
    require_once __DIR__ . '/invoice-mailer.php';

    if ($pdfPath === null || $pdfPath === '' || !is_file($pdfPath)) {
        $ensure = invoiceAmcEnsureInvoicePdf($invoiceId);
        if (empty($ensure['ok']) || empty($ensure['path'])) {
            return [
                'ok'        => false,
                'attempted' => false,
                'error'     => $ensure['error'] ?? 'PDF attachment missing; email was not sent.',
            ];
        }
        $pdfPath = $ensure['path'];
    }

    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        return ['ok' => false, 'attempted' => false, 'error' => 'Invoice not found for email delivery.'];
    }

    $client = invoiceAmcGetClient((int) $invoice['client_id']);
    if (!$client) {
        return ['ok' => false, 'attempted' => false, 'error' => 'Client record not found for email delivery.'];
    }

    $toEmail = (string) ($client['email'] ?? $invoice['client_email'] ?? '');
    $emailIssue = invoiceAmcValidateRecipientEmail($toEmail);
    if ($emailIssue !== null) {
        invoiceAmcLogEmail('invoice', $invoiceId, 'invoice_created', $toEmail, 'Invoice (not sent)', false, $emailIssue);

        return ['ok' => false, 'attempted' => true, 'error' => $emailIssue];
    }

    try {
        return array_merge(['attempted' => true], invoiceMailerSendInvoiceCreated($invoiceId, $client, $pdfPath));
    } catch (Throwable $e) {
        invoiceAmcLogEmail('invoice', $invoiceId, 'invoice_created', $toEmail, 'Invoice (exception)', false, $e->getMessage());

        return ['ok' => false, 'attempted' => true, 'error' => $e->getMessage()];
    }
}

/**
 * @return array{ok:bool, error?:string, attempted?:bool}
 */
function invoiceAmcSendAmcCreatedEmail(int $amcId, ?string $pdfPath = null): array
{
    require_once __DIR__ . '/invoice-mailer.php';

    if ($pdfPath === null || $pdfPath === '' || !is_file($pdfPath)) {
        $ensure = invoiceAmcEnsureAmcPdf($amcId);
        if (empty($ensure['ok']) || empty($ensure['path'])) {
            return [
                'ok'        => false,
                'attempted' => false,
                'error'     => $ensure['error'] ?? 'PDF attachment missing; email was not sent.',
            ];
        }
        $pdfPath = $ensure['path'];
    }

    $amc = invoiceAmcGetAmc($amcId);
    if (!$amc) {
        return ['ok' => false, 'attempted' => false, 'error' => 'AMC contract not found for email delivery.'];
    }

    $client = invoiceAmcGetClient((int) $amc['client_id']);
    if (!$client) {
        return ['ok' => false, 'attempted' => false, 'error' => 'Client record not found for email delivery.'];
    }

    $toEmail = (string) ($client['email'] ?? $amc['client_email'] ?? '');
    $emailIssue = invoiceAmcValidateRecipientEmail($toEmail);
    if ($emailIssue !== null) {
        invoiceAmcLogEmail('amc', $amcId, 'amc_created', $toEmail, 'AMC Contract (not sent)', false, $emailIssue);

        return ['ok' => false, 'attempted' => true, 'error' => $emailIssue];
    }

    try {
        return array_merge(['attempted' => true], invoiceMailerSendAmcCreated($amcId, $client, $pdfPath));
    } catch (Throwable $e) {
        invoiceAmcLogEmail('amc', $amcId, 'amc_created', $toEmail, 'AMC Contract (exception)', false, $e->getMessage());

        return ['ok' => false, 'attempted' => true, 'error' => $e->getMessage()];
    }
}

/**
 * @param array{ok?:bool, pdf?:bool, email_sent?:bool, email_error?:string|null} $result
 * @return array{type:string, message:string}
 */
function invoiceAmcAmcCreateFlashMessage(array $result): array
{
    if (!empty($result['email_sent'])) {
        return [
            'type'    => 'success',
            'message' => 'AMC contract created. PDF generated and email sent automatically.',
        ];
    }

    if (!empty($result['pdf'])) {
        $error = trim((string) ($result['email_error'] ?? 'Unknown mailer error.'));
        return [
            'type'    => 'warning',
            'message' => 'AMC contract created and PDF generated, but email sending failed. ' . $error,
        ];
    }

    $pdfError = trim((string) ($result['pdf_error'] ?? 'PDF could not be generated.'));
    return [
        'type'    => 'warning',
        'message' => 'AMC contract created, but PDF generation failed. Email was not sent. ' . $pdfError,
    ];
}

/**
 * @param array{ok?:bool, pdf?:bool, email_sent?:bool, email_error?:string|null} $result
 * @return array{type:string, message:string}
 */
function invoiceAmcAmcRenewFlashMessage(array $result): array
{
    if (!empty($result['email_sent'])) {
        return [
            'type'    => 'success',
            'message' => 'AMC contract renewed. New contract created and email sent automatically.',
        ];
    }

    if (!empty($result['pdf'])) {
        $error = trim((string) ($result['email_error'] ?? 'Unknown mailer error.'));
        return [
            'type'    => 'warning',
            'message' => 'AMC contract renewed and PDF generated, but email sending failed. ' . $error,
        ];
    }

    $pdfError = trim((string) ($result['pdf_error'] ?? 'PDF could not be generated.'));
    return [
        'type'    => 'warning',
        'message' => 'AMC contract renewed, but PDF generation failed. Email was not sent. ' . $pdfError,
    ];
}

/**
 * @param array{ok?:bool, pdf?:bool, email_sent?:bool, email_error?:string|null} $result
 * @return array{type:string, message:string}
 */
function invoiceAmcInvoiceCreateFlashMessage(array $result): array
{
    if (!empty($result['email_sent'])) {
        return [
            'type'    => 'success',
            'message' => 'Invoice created. PDF generated and email sent automatically.',
        ];
    }

    if (!empty($result['pdf'])) {
        $error = trim((string) ($result['email_error'] ?? 'Unknown mailer error.'));
        return [
            'type'    => 'warning',
            'message' => 'Invoice created and PDF generated, but email sending failed. ' . $error,
        ];
    }

    $pdfError = trim((string) ($result['pdf_error'] ?? 'PDF could not be generated.'));
    return [
        'type'    => 'warning',
        'message' => 'Invoice created, but PDF generation failed. Email was not sent. ' . $pdfError,
    ];
}

function invoiceAmcLogEmail(
    string $entityType,
    int $entityId,
    string $emailType,
    string $recipient,
    string $subject,
    bool $ok,
    ?string $error = null
): int {
    ensureInvoiceAmcSchema();
    return (int) db()->insert(
        'INSERT INTO email_logs (entity_type, entity_id, email_type, recipient_email, subject, status, error_message)
         VALUES (?,?,?,?,?,?,?)',
        [
            $entityType,
            $entityId,
            $emailType,
            $recipient,
            $subject,
            $ok ? 'sent' : 'failed',
            $ok ? null : ($error ?? 'Unknown error'),
        ]
    );
}

/** @return array{days:int, label:string}[] */
function invoiceAmcRenewalReminderSchedule(): array
{
    return [
        ['days' => 30, 'label' => '30 Days Before Expiry'],
        ['days' => 15, 'label' => '15 Days Before Expiry'],
        ['days' => 7,  'label' => '7 Days Before Expiry'],
        ['days' => 1,  'label' => '1 Day Before Expiry'],
    ];
}

function invoiceAmcProcessRenewalReminders(): array
{
    ensureInvoiceAmcSchema();
    require_once __DIR__ . '/invoice-mailer.php';

    invoiceAmcRefreshAmcStatuses();
    $sent = 0;
    $errors = [];
    $activeAmc = invoiceAmcActiveClause('a', 'amc_contracts');
    $activeClients = invoiceAmcActiveClause('c', 'clients');

    foreach (invoiceAmcRenewalReminderSchedule() as $reminder) {
        $days = (int) $reminder['days'];
        $contracts = db()->fetchAll(
            "SELECT a.*, c.name AS client_name, c.email AS client_email
             FROM amc_contracts a
             JOIN clients c ON c.id = a.client_id
             WHERE {$activeAmc}
               AND {$activeClients}
               AND a.status IN ('active','expiring')
               AND a.end_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND NOT EXISTS (
                   SELECT 1 FROM amc_renewals r
                   WHERE r.amc_contract_id = a.id AND r.reminder_days = ?
               )",
            [$days, $days]
        );

        foreach ($contracts as $contract) {
            $result = invoiceMailerSendAmcRenewalReminder((int) $contract['id'], $contract, $days);
            if ($result['ok']) {
                db()->insert(
                    'INSERT INTO amc_renewals (amc_contract_id, reminder_days, sent_at, email_log_id) VALUES (?,?,NOW(),?)',
                    [(int) $contract['id'], $days, (int) ($result['log_id'] ?? 0) ?: null]
                );
                $sent++;
            } else {
                $errors[] = 'AMC #' . $contract['amc_no'] . ': ' . ($result['error'] ?? 'Failed');
            }
        }
    }

    return ['sent' => $sent, 'errors' => $errors];
}

/** @return array<string, mixed> */
function invoiceAmcDashboardStats(): array
{
    ensureInvoiceAmcSchema();
    invoiceAmcRefreshAmcStatuses();
    $clientsActive = invoiceAmcActiveClause(null, 'clients');
    $clientsCountWhere = $clientsActive;
    if (function_exists('adminTableHasColumn') && adminTableHasColumn('clients', 'is_blocked')) {
        // Dashboard should reflect active/usable clients only.
        $clientsCountWhere .= ' AND (is_blocked = 0 OR is_blocked IS NULL)';
    }
    $invoicesActive = invoiceAmcActiveClause(null, 'invoices');
    $amcActive = invoiceAmcActiveClause(null, 'amc_contracts');

    return [
        'total_clients'    => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM clients WHERE {$clientsCountWhere}")['c'] ?? 0, 0),
        'total_invoices'   => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM invoices WHERE {$invoicesActive}")['c'] ?? 0, 0),
        'paid_invoices'    => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM invoices WHERE status='paid' AND {$invoicesActive}")['c'] ?? 0, 0),
        'pending_invoices' => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM invoices WHERE status='pending' AND {$invoicesActive}")['c'] ?? 0, 0),
        'total_revenue'    => (float) dbTry(fn () => db()->fetchOne("SELECT COALESCE(SUM(total_amount),0) AS s FROM invoices WHERE status='paid' AND {$invoicesActive}")['s'] ?? 0, 0),
        'total_amc'        => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM amc_contracts WHERE {$amcActive}")['c'] ?? 0, 0),
        'active_amc'       => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM amc_contracts WHERE status='active' AND {$amcActive}")['c'] ?? 0, 0),
        'expiring_amc'     => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM amc_contracts WHERE status='expiring' AND {$amcActive}")['c'] ?? 0, 0),
        'expired_amc'      => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM amc_contracts WHERE status='expired' AND {$amcActive}")['c'] ?? 0, 0),
        'renewed_amc'      => (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) AS c FROM amc_contracts WHERE status='renewed' AND {$amcActive}")['c'] ?? 0, 0),
    ];
}

function invoiceAmcFormatMoney(float $amount, string $currency = 'USD'): string
{
    $symbol = match (strtoupper($currency)) {
        'USD' => '$',
        'EUR' => '€',
        'INR' => '₹',
        default => $currency . ' ',
    };
    return $symbol . number_format($amount, 2);
}

function invoiceAmcDefaultLineItems(): array
{
    return [
        'Project Development (as per scope)',
        'Smart Contracts',
        'Frontend / Backend Development',
        'Testing & QA',
        'Deployment & Integration',
        'Documentation',
    ];
}
