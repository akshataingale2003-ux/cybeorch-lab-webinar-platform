<?php
declare(strict_types=1);

/**
 * Admin record actions: delete (permanent), block/unblock, optional trash (soft-delete).
 */

function adminEntityRegistry(): array
{
    return [
        'user' => [
            'table' => 'users',
            'label' => 'User Registration & Trainee',
            'blockable' => true,
            'block_column' => 'is_blocked',
            'title_column' => 'full_name',
        ],
        'webinar' => [
            'table' => 'webinars',
            'label' => 'Webinar',
            'blockable' => true,
            'title_column' => 'title',
        ],
        'bootcamp' => [
            'table' => 'bootcamps',
            'label' => 'Bootcamp',
            'blockable' => true,
            'title_column' => 'title',
        ],
        'live_project' => [
            'table' => 'live_projects',
            'label' => 'Live project',
            'blockable' => true,
            'title_column' => 'title',
        ],
        'assignment' => [
            'table' => 'assignments',
            'label' => 'Hands-on Projects',
            'blockable' => true,
            'title_column' => 'title',
        ],
        'freelance_project' => [
            'table' => 'freelance_projects',
            'label' => 'Freelance project',
            'blockable' => true,
            'title_column' => 'title',
        ],
        'webinar_registration' => [
            'table' => 'webinar_registrations',
            'label' => 'Webinar registration',
            'blockable' => true,
            'title_column' => 'registration_no',
        ],
        'bootcamp_enrollment' => [
            'table' => 'bootcamp_enrollments',
            'label' => 'Bootcamp enrollment',
            'blockable' => true,
            'title_column' => 'enrollment_no',
        ],
        'payment' => [
            'table' => 'payments',
            'label' => 'Payment',
            'blockable' => true,
            'title_column' => 'order_id',
        ],
        'wallet' => [
            'table' => 'wallet',
            'label' => 'Wallet',
            'blockable' => true,
            'title_column' => 'id',
        ],
        'wallet_transaction' => [
            'table' => 'wallet_transactions',
            'label' => 'Wallet transaction',
            'blockable' => true,
            'title_column' => 'reason',
        ],
        'referral' => [
            'table' => 'referrals',
            'label' => 'Referral',
            'blockable' => true,
            'title_column' => 'id',
        ],
        'attendance' => [
            'table' => 'attendance',
            'label' => 'Attendance',
            'blockable' => true,
            'title_column' => 'id',
        ],
        'freelancer' => [
            'table' => 'freelancer_registrations',
            'label' => 'Freelancer application',
            'blockable' => true,
            'title_column' => 'full_name',
        ],
        'support_ticket' => [
            'table' => 'support_tickets',
            'label' => 'Support ticket',
            'blockable' => true,
            'title_column' => 'ticket_no',
        ],
        'contact_message' => [
            'table' => 'contact_messages',
            'label' => 'Contact message',
            'blockable' => true,
            'title_column' => 'name',
        ],
        'website_user' => [
            'table' => 'website_users',
            'label' => 'Website registration',
            'blockable' => true,
            'block_column' => 'is_blocked',
            'title_column' => 'full_name',
        ],
        'form_submission' => [
            'table' => 'form_submissions',
            'label' => 'Form submission',
            'blockable' => false,
            'title_column' => 'form_label',
        ],
        'demo_request' => [
            'table' => 'demo_requests',
            'label' => 'Demo request',
            'blockable' => false,
            'title_column' => 'full_name',
        ],
        'collaboration_inquiry' => [
            'table' => 'collaboration_inquiries',
            'label' => 'Collaboration inquiry',
            'blockable' => false,
            'title_column' => 'full_name',
        ],
        'get_started_inquiry' => [
            'table' => 'get_started_inquiries',
            'label' => 'Get Started inquiry',
            'blockable' => false,
            'title_column' => 'full_name',
        ],
        'notification' => [
            'table' => 'notifications',
            'label' => 'Notification',
            'blockable' => true,
            'title_column' => 'title',
        ],
    ];
}

function adminEntityConfig(string $entity): ?array
{
    $entity = strtolower(trim($entity));
    return adminEntityRegistry()[$entity] ?? null;
}

function adminTableHasColumn(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $row = db()->fetchOne(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $cache[$key] = ((int) ($row['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function ensureAdminActionsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    foreach (adminEntityRegistry() as $cfg) {
        $table = $cfg['table'];
        if (!adminTableHasColumn($table, 'deleted_at')) {
            try {
                db()->execute("ALTER TABLE `{$table}` ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
            } catch (Throwable $e) {
                // table may not exist yet
            }
        }

        if (($cfg['block_column'] ?? '') === 'is_blocked') {
            if (!adminTableHasColumn($table, 'is_blocked')) {
                try {
                    db()->execute("ALTER TABLE `{$table}` ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0");
                } catch (Throwable $e) {
                }
            }
        } elseif (!empty($cfg['blockable']) && !adminTableHasColumn($table, 'record_status')) {
            try {
                db()->execute(
                    "ALTER TABLE `{$table}` ADD COLUMN record_status ENUM('active','blocked') NOT NULL DEFAULT 'active'"
                );
            } catch (Throwable $e) {
            }
        }
    }
}

function adminSqlActive(?string $alias = null, bool $trashOnly = false): string
{
    ensureAdminActionsSchema();
    $p = $alias !== null && $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $parts = [];
    if ($trashOnly) {
        $parts[] = $p . 'deleted_at IS NOT NULL';
    } else {
        $parts[] = '(' . $p . 'deleted_at IS NULL OR ' . $p . 'deleted_at = \'0000-00-00 00:00:00\')';
    }
    return implode(' AND ', $parts);
}

function adminRecordIsBlocked(array $row): bool
{
    if (array_key_exists('is_blocked', $row)) {
        return (int) $row['is_blocked'] === 1;
    }
    if (array_key_exists('record_status', $row)) {
        return strtolower((string) $row['record_status']) === 'blocked';
    }
    return false;
}

function renderAdminRecordRowAttrs(string $entity, int $id, bool $blocked = false): string
{
    $attrs = sprintf(
        ' data-admin-row="1" data-admin-entity="%s" data-admin-id="%d"',
        htmlspecialchars($entity, ENT_QUOTES, 'UTF-8'),
        $id
    );
    if ($blocked) {
        $attrs .= ' class="admin-row-blocked"';
    }
    return $attrs;
}

function renderAdminRecordActions(string $entity, int $id, bool $blocked = false, bool $inTrash = false): void
{
    $cfg = adminEntityConfig($entity);
    if (!$cfg) {
        return;
    }
    $blockable = !empty($cfg['blockable']);
    echo '<div class="admin-action-btns d-inline-flex flex-wrap gap-1 align-items-center">';
    if ($inTrash) {
        echo '<button type="button" class="btn-sm-cyber btn-restore" data-admin-action="restore" data-admin-entity="'
            . htmlspecialchars($entity) . '" data-admin-id="' . $id . '"><i class="fas fa-undo"></i><span>Restore</span></button>';
        echo '<button type="button" class="btn-sm-cyber btn-delete" data-admin-action="purge" data-admin-entity="'
            . htmlspecialchars($entity) . '" data-admin-id="' . $id
            . '" data-admin-confirm="Permanently delete this item from the database? This cannot be undone.">'
            . '<i class="fas fa-trash"></i><span>Delete forever</span></button>';
    } else {
        if ($blockable) {
            if ($blocked) {
                echo '<button type="button" class="btn-sm-cyber btn-unblock" data-admin-action="unblock" data-admin-entity="'
                    . htmlspecialchars($entity) . '" data-admin-id="' . $id
                    . '"><i class="fas fa-unlock"></i><span>Unblock</span></button>';
            } else {
                echo '<button type="button" class="btn-sm-cyber btn-block" data-admin-action="block" data-admin-entity="'
                    . htmlspecialchars($entity) . '" data-admin-id="' . $id
                    . '" data-admin-confirm="Block this ' . htmlspecialchars($cfg['label']) . '?">'
                    . '<i class="fas fa-ban"></i><span>Block</span></button>';
            }
        }
        $deleteConfirm = $entity === 'user'
            ? 'Move this user to Deleted? They cannot log in until restored from Trash.'
            : ('Delete this ' . $cfg['label'] . '? It will be moved to trash when supported.');
        echo '<button type="button" class="btn-sm-cyber btn-delete" data-admin-action="delete" data-admin-entity="'
            . htmlspecialchars($entity) . '" data-admin-id="' . $id
            . '" data-admin-confirm="' . htmlspecialchars($deleteConfirm) . '">'
            . '<i class="fas fa-trash"></i><span>Delete</span></button>';
    }
    echo '</div>';
}

function renderAdminTrashRestoreAction(string $entity, int $id): void
{
    renderAdminRecordActions($entity, $id, false, true);
}

function adminBlockedUsersCount(): int
{
    ensureAdminActionsSchema();
    if (!adminTableHasColumn('users', 'is_blocked')) {
        return 0;
    }
    try {
        return (int) (db()->fetchOne(
            'SELECT COUNT(*) AS c FROM users WHERE ' . adminSqlActive() . ' AND is_blocked = 1'
        )['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function adminTrashCount(): int
{
    ensureAdminActionsSchema();
    $total = 0;
    foreach (adminEntityRegistry() as $cfg) {
        $table = $cfg['table'];
        if (!adminTableHasColumn($table, 'deleted_at')) {
            continue;
        }
        try {
            $total += (int) (db()->fetchOne(
                'SELECT COUNT(*) AS c FROM `' . $table . '` WHERE deleted_at IS NOT NULL'
            )['c'] ?? 0);
        } catch (Throwable $e) {
        }
    }
    return $total;
}

function adminParseListSortParam(string $default = 'newest'): string
{
    $raw = sanitize((string) ($_GET['sort'] ?? $default));
    return in_array($raw, ['newest', 'oldest'], true) ? $raw : $default;
}

function adminSortSqlDirection(string $sort): string
{
    return $sort === 'oldest' ? 'ASC' : 'DESC';
}

/**
 * @return list<array{entity:string,type_label:string,id:int,title:string,deleted_at:string}>
 */
function adminListTrashedItems(int $limit = 50, string $sort = 'newest'): array
{
    ensureAdminActionsSchema();
    if (!in_array($sort, ['newest', 'oldest'], true)) {
        $sort = 'newest';
    }
    $items = [];
    foreach (adminEntityRegistry() as $entity => $cfg) {
        $table = $cfg['table'];
        if (!adminTableHasColumn($table, 'deleted_at')) {
            continue;
        }
        $titleCol = $cfg['title_column'] ?? 'id';
        try {
            $rows = db()->fetchAll(
                "SELECT id, `{$titleCol}` AS title_label, deleted_at FROM `{$table}`
                 WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC LIMIT " . (int) max(1, $limit)
            );
            foreach ($rows as $row) {
                $title = (string) ($row['title_label'] ?? $row['id']);
                if ($title === '' || $titleCol === 'id') {
                    $title = $cfg['label'] . ' #' . $row['id'];
                }
                $items[] = [
                    'entity' => $entity,
                    'type_label' => $cfg['label'],
                    'id' => (int) $row['id'],
                    'title' => $title,
                    'deleted_at' => (string) $row['deleted_at'],
                ];
            }
        } catch (Throwable $e) {
        }
    }
    usort($items, static function (array $a, array $b) use ($sort): int {
        $cmp = strcmp($a['deleted_at'], $b['deleted_at']);
        return $sort === 'oldest' ? $cmp : -$cmp;
    });
    return array_slice($items, 0, $limit);
}

function adminFetchRecord(string $entity, int $id): ?array
{
    $cfg = adminEntityConfig($entity);
    if (!$cfg || $id < 1) {
        return null;
    }
    return db()->fetchOne('SELECT * FROM `' . $cfg['table'] . '` WHERE id = ?', [$id]);
}

function adminPerformRecordAction(string $action, string $entity, int $id): array
{
    ensureAdminActionsSchema();
    $cfg = adminEntityConfig($entity);
    if (!$cfg || $id < 1) {
        return ['success' => false, 'message' => 'Invalid record.'];
    }

    $table = $cfg['table'];
    $row = adminFetchRecord($entity, $id);
    if (!$row) {
        return ['success' => false, 'message' => 'Record not found.'];
    }

    try {
        switch ($action) {
            case 'block':
                if (empty($cfg['blockable'])) {
                    return ['success' => false, 'message' => 'This record cannot be blocked.'];
                }
                if (($cfg['block_column'] ?? '') === 'is_blocked') {
                    db()->execute('UPDATE `' . $table . '` SET is_blocked = 1 WHERE id = ?', [$id]);
                } else {
                    db()->execute(
                        "UPDATE `{$table}` SET record_status = 'blocked' WHERE id = ?",
                        [$id]
                    );
                }
                return [
                    'success' => true,
                    'message' => $cfg['label'] . ' blocked successfully.',
                    'blocked' => true,
                ];

            case 'unblock':
                if (empty($cfg['blockable'])) {
                    return ['success' => false, 'message' => 'This record cannot be unblocked.'];
                }
                if (($cfg['block_column'] ?? '') === 'is_blocked') {
                    db()->execute('UPDATE `' . $table . '` SET is_blocked = 0 WHERE id = ?', [$id]);
                } else {
                    db()->execute(
                        "UPDATE `{$table}` SET record_status = 'active' WHERE id = ?",
                        [$id]
                    );
                }
                return [
                    'success' => true,
                    'message' => $cfg['label'] . ' unblocked successfully.',
                    'blocked' => false,
                ];

            case 'delete':
                if ($entity === 'user' && adminTableHasColumn($table, 'deleted_at')) {
                    db()->execute('UPDATE `' . $table . '` SET deleted_at = NOW() WHERE id = ?', [$id]);
                    return [
                        'success' => true,
                        'message' => 'User moved to deleted. Restore from Trash or filter Deleted users.',
                        'removed' => true,
                    ];
                }
                if (adminTableHasColumn($table, 'deleted_at')) {
                    db()->execute('UPDATE `' . $table . '` SET deleted_at = NOW() WHERE id = ?', [$id]);
                    return [
                        'success' => true,
                        'message' => $cfg['label'] . ' moved to trash.',
                        'removed' => true,
                    ];
                }
                db()->execute('DELETE FROM `' . $table . '` WHERE id = ?', [$id]);
                return [
                    'success' => true,
                    'message' => $cfg['label'] . ' deleted permanently.',
                    'removed' => true,
                ];

            case 'trash':
                if (!adminTableHasColumn($table, 'deleted_at')) {
                    db()->execute('DELETE FROM `' . $table . '` WHERE id = ?', [$id]);
                    return ['success' => true, 'message' => $cfg['label'] . ' deleted.', 'removed' => true];
                }
                db()->execute('UPDATE `' . $table . '` SET deleted_at = NOW() WHERE id = ?', [$id]);
                return [
                    'success' => true,
                    'message' => $cfg['label'] . ' moved to trash.',
                    'removed' => true,
                ];

            case 'restore':
                if (!adminTableHasColumn($table, 'deleted_at')) {
                    return ['success' => false, 'message' => 'Restore is not available for this type.'];
                }
                db()->execute('UPDATE `' . $table . '` SET deleted_at = NULL WHERE id = ?', [$id]);
                return [
                    'success' => true,
                    'message' => $cfg['label'] . ' restored successfully.',
                    'restored' => true,
                ];

            case 'purge':
                db()->execute('DELETE FROM `' . $table . '` WHERE id = ?', [$id]);
                return [
                    'success' => true,
                    'message' => $cfg['label'] . ' permanently deleted.',
                    'removed' => true,
                ];

            default:
                return ['success' => false, 'message' => 'Unknown action.'];
        }
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * @param list<array{entity: string, id: int|string}> $items
 * @return array{success: bool, message: string, processed?: int, failed?: int}
 */
function adminPerformBulkRecordActions(string $action, array $items): array
{
    if (!in_array($action, ['restore', 'purge'], true)) {
        return ['success' => false, 'message' => 'Bulk action not supported.'];
    }

    $processed = 0;
    $failed = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            $failed++;
            continue;
        }
        $entity = strtolower(trim((string) ($item['entity'] ?? '')));
        $id = (int) ($item['id'] ?? 0);
        if ($entity === '' || $id < 1) {
            $failed++;
            continue;
        }
        $result = adminPerformRecordAction($action, $entity, $id);
        if (!empty($result['success'])) {
            $processed++;
        } else {
            $failed++;
        }
    }

    if ($processed === 0) {
        return [
            'success' => false,
            'message' => $failed > 0 ? 'No items could be processed.' : 'No items selected.',
            'processed' => 0,
            'failed' => $failed,
        ];
    }

    $verb = $action === 'restore' ? 'restored' : 'permanently deleted';
    $message = $processed . ' item(s) ' . $verb . ' successfully.';
    if ($failed > 0) {
        $message .= ' ' . $failed . ' item(s) failed.';
    }

    return [
        'success' => true,
        'message' => $message,
        'processed' => $processed,
        'failed' => $failed,
    ];
}

function adminHandleRecordActionRequest(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        return;
    }

    startSession();
    if (!isAdminLoggedIn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin login required.']);
        return;
    }

    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCSRF($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page.']);
        return;
    }

    $action = strtolower(trim((string) ($_POST['action'] ?? '')));
    $isBulk = !empty($_POST['bulk']);

    if ($isBulk) {
        $rawItems = $_POST['items'] ?? '[]';
        $items = is_string($rawItems) ? json_decode($rawItems, true) : $rawItems;
        if (!is_array($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid bulk selection.']);
            return;
        }
        $result = adminPerformBulkRecordActions($action, $items);
        if (!$result['success']) {
            http_response_code(400);
        }
        echo json_encode($result, JSON_THROW_ON_ERROR);
        return;
    }

    $entity = strtolower(trim((string) ($_POST['entity'] ?? '')));
    $id = (int) ($_POST['id'] ?? 0);

    $result = adminPerformRecordAction($action, $entity, $id);
    if (!$result['success']) {
        http_response_code(400);
    }
    echo json_encode($result, JSON_THROW_ON_ERROR);
}
