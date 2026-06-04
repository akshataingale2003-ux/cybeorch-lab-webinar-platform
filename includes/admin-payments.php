<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin-actions.php';

/**
 * @return array{where: string, params: list<mixed>}
 */
function adminPaymentsFilterClause(
    ?string $statusFilter = null,
    string $searchQ = '',
    string $recordStatus = 'active'
): array {
    ensureAdminActionsSchema();

    $parts = [];
    $params = [];

    if ($recordStatus === 'deleted') {
        $parts[] = 'p.deleted_at IS NOT NULL';
    } else {
        $parts[] = adminSqlActive('p');
    }

    $statusFilter = $statusFilter !== null ? strtolower(trim($statusFilter)) : '';
    if ($statusFilter !== '' && $statusFilter !== 'all' && in_array($statusFilter, ['created', 'paid', 'failed', 'refunded'], true)) {
        $parts[] = 'p.status = ?';
        $params[] = $statusFilter;
    }

    $searchQ = trim($searchQ);
    if ($searchQ !== '') {
        $parts[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR p.order_id LIKE ? OR p.razorpay_order_id LIKE ? OR p.razorpay_payment_id LIKE ? OR p.invoice_no LIKE ? OR w.title LIKE ? OR bc.title LIKE ?)';
        $like = '%' . $searchQ . '%';
        $params = array_merge($params, array_fill(0, 9, $like));
    }

    return ['where' => implode(' AND ', $parts), 'params' => $params];
}

function adminPaymentsBaseSql(): string
{
    return "SELECT p.*,
            u.full_name AS user_name,
            u.email AS user_email,
            u.phone AS user_phone,
            CASE p.payment_for
                WHEN 'webinar' THEN w.title
                WHEN 'bootcamp' THEN bc.title
                ELSE 'Wallet top-up'
            END AS program_title
            FROM payments p
            LEFT JOIN users u ON u.id = p.user_id
            LEFT JOIN webinars w ON p.payment_for = 'webinar' AND w.id = p.reference_id
            LEFT JOIN bootcamps bc ON p.payment_for = 'bootcamp' AND bc.id = p.reference_id";
}

function countAdminPayments(
    ?string $statusFilter = null,
    string $searchQ = '',
    string $recordStatus = 'active'
): int {
    $filter = adminPaymentsFilterClause($statusFilter, $searchQ, $recordStatus);
    $row = db()->fetchOne(
        'SELECT COUNT(*) AS c FROM payments p
         LEFT JOIN users u ON u.id = p.user_id
         LEFT JOIN webinars w ON p.payment_for = \'webinar\' AND w.id = p.reference_id
         LEFT JOIN bootcamps bc ON p.payment_for = \'bootcamp\' AND bc.id = p.reference_id
         WHERE ' . $filter['where'],
        $filter['params']
    );
    return (int) ($row['c'] ?? 0);
}

/**
 * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
 */
function getAdminPaymentsPaginated(
    ?string $statusFilter = null,
    string $searchQ = '',
    string $recordStatus = 'active',
    int $page = 1,
    int $perPage = 25
): array {
    $perPage = max(10, min(100, $perPage));
    $page = max(1, $page);
    $total = countAdminPayments($statusFilter, $searchQ, $recordStatus);
    $totalPages = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $filter = adminPaymentsFilterClause($statusFilter, $searchQ, $recordStatus);

    $rows = db()->fetchAll(
        adminPaymentsBaseSql() . ' WHERE ' . $filter['where']
        . ' ORDER BY p.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
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

function getAdminPaymentById(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne(
        adminPaymentsBaseSql() . ' WHERE p.id = ? LIMIT 1',
        [$id]
    );
    return $row ?: null;
}

/** @return array<string, int> */
function getAdminPaymentCountsByStatus(): array
{
    ensureAdminActionsSchema();
    $base = adminSqlActive('p');
    $counts = ['all' => 0, 'paid' => 0, 'created' => 0, 'failed' => 0, 'refunded' => 0];

    $counts['all'] = (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM payments p WHERE {$base}")['c'] ?? 0);

    foreach (['paid', 'created', 'failed', 'refunded'] as $status) {
        $counts[$status] = (int) (db()->fetchOne(
            "SELECT COUNT(*) AS c FROM payments p WHERE {$base} AND p.status = ?",
            [$status]
        )['c'] ?? 0);
    }

    return $counts;
}

function adminPaymentDisplayMethod(array $row): string
{
    $method = trim((string) ($row['payment_method'] ?? ''));
    if ($method !== '') {
        return ucfirst($method);
    }
    if (!empty($row['razorpay_payment_id'])) {
        return 'Razorpay';
    }
    if (!empty($row['razorpay_order_id'])) {
        return 'Razorpay (pending)';
    }
    return '—';
}

function adminPaymentTransactionId(array $row): string
{
    if (!empty($row['razorpay_payment_id'])) {
        return (string) $row['razorpay_payment_id'];
    }
    if (!empty($row['razorpay_order_id'])) {
        return (string) $row['razorpay_order_id'];
    }
    return (string) ($row['order_id'] ?? '—');
}

function adminPaymentProgramLabel(array $row): string
{
    $title = trim((string) ($row['program_title'] ?? ''));
    $for = (string) ($row['payment_for'] ?? '');
    if ($title === '' || $title === 'Wallet top-up') {
        return match ($for) {
            'webinar'  => 'Webinar #' . (int) ($row['reference_id'] ?? 0),
            'bootcamp' => 'Bootcamp #' . (int) ($row['reference_id'] ?? 0),
            'wallet_topup' => 'Wallet top-up',
            default => ucfirst($for ?: 'Payment'),
        };
    }
    $type = match ($for) {
        'webinar'  => 'Webinar',
        'bootcamp' => 'Bootcamp',
        default    => 'Program',
    };
    return $type . ': ' . $title;
}

function adminRenderPaymentStatusBadge(string $status): void
{
    $status = strtolower($status);
    $class = match ($status) {
        'paid'      => 'badge-paid',
        'failed'    => 'badge-failed',
        'refunded'  => 'badge-refunded',
        'created'   => 'badge-pending',
        default     => 'badge-pending',
    };
    echo '<span class="badge-status ' . $class . '">' . htmlspecialchars(ucfirst($status)) . '</span>';
}

/**
 * @return array{success: bool, message: string}
 */
function adminMarkPaymentRefunded(int $paymentId): array
{
    ensureAdminActionsSchema();
    $payment = db()->fetchOne(
        'SELECT * FROM payments WHERE id = ? AND ' . adminSqlActive(),
        [$paymentId]
    );
    if (!$payment) {
        return ['success' => false, 'message' => 'Payment not found.'];
    }
    if ($payment['status'] === 'refunded') {
        return ['success' => false, 'message' => 'This payment is already refunded.'];
    }
    if ($payment['status'] !== 'paid') {
        return ['success' => false, 'message' => 'Only paid payments can be marked as refunded.'];
    }

    db()->execute("UPDATE payments SET status = 'refunded' WHERE id = ?", [$paymentId]);

    if ($payment['payment_for'] === 'webinar') {
        db()->execute(
            "UPDATE webinar_registrations SET payment_status = 'refunded' WHERE payment_id = ?",
            [$paymentId]
        );
    } elseif ($payment['payment_for'] === 'bootcamp') {
        db()->execute(
            "UPDATE bootcamp_enrollments SET payment_status = 'refunded' WHERE payment_id = ?",
            [$paymentId]
        );
    }

    return ['success' => true, 'message' => 'Payment marked as refunded.'];
}

function adminPaymentCanRefund(array $row): bool
{
    if (strtolower((string) ($row['status'] ?? '')) !== 'paid') {
        return false;
    }
    if (adminTableHasColumn('payments', 'deleted_at') && !empty($row['deleted_at'])) {
        return false;
    }
    return true;
}
