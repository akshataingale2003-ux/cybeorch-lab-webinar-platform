<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/admin-payments.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

/** @return array<string, string> */
function adminPaymentsQuery(array $overrides = []): array
{
    global $statusFilter, $recordStatus, $searchQ, $page, $perPage;

    $q = [
        'status'   => $recordStatus,
        'page'     => (string) $page,
        'per_page' => (string) $perPage,
    ];
    if ($statusFilter !== '' && $statusFilter !== 'all') {
        $q['payment_status'] = $statusFilter;
    }
    if ($searchQ !== '') {
        $q['q'] = $searchQ;
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = (string) $v;
        }
    }
    return $q;
}

function adminPaymentsUrl(array $overrides = [], ?int $viewId = null): string
{
    $q = adminPaymentsQuery($overrides);
    if ($viewId !== null && $viewId > 0) {
        $q['view'] = (string) $viewId;
        unset($q['page']);
    }
    $qs = http_build_query($q);
    return adminUrl('admin/payments.php') . ($qs !== '' ? '?' . $qs : '');
}

function adminPaymentsHiddenFields(): void
{
    foreach (adminPaymentsQuery() as $k => $v) {
        if ($k === 'page') {
            continue;
        }
        echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
    }
}

$viewId = (int) ($_REQUEST['view'] ?? 0);

$recordStatus = 'active';
if (isset($_REQUEST['status']) && in_array($_REQUEST['status'], ['active', 'deleted'], true)) {
    $recordStatus = sanitize((string) $_REQUEST['status']);
}

$statusFilter = 'all';
if (isset($_REQUEST['payment_status']) && $_REQUEST['payment_status'] !== '') {
    $statusFilter = sanitize((string) $_REQUEST['payment_status']);
} elseif (isset($_GET['status']) && in_array($_GET['status'], ['created', 'paid', 'failed', 'refunded', 'all'], true)) {
    $statusFilter = sanitize((string) $_GET['status']);
}

$searchQ = trim((string) ($_REQUEST['q'] ?? ''));
$page = max(1, (int) ($_REQUEST['page'] ?? 1));
$perPage = max(10, min(100, (int) ($_REQUEST['per_page'] ?? 25)));

$flashMsg = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $postAction = sanitize($_POST['action'] ?? '');
    $postId = (int) ($_POST['id'] ?? 0);

    if ($postId > 0 && $postAction === 'refund') {
        $result = adminMarkPaymentRefunded($postId);
        $flashMsg = $result['message'];
        $flashType = $result['success'] ? 'success' : 'error';
    }

    if ($flashMsg !== '') {
        $redirect = !empty($_POST['stay_on_view']) && $postId > 0
            ? adminPaymentsUrl([], $postId)
            : adminPaymentsUrl();
        setFlash($flashType, $flashMsg);
        header('Location: ' . $redirect);
        exit;
    }
}

$flash = getFlash();
$viewPayment = $viewId > 0 ? getAdminPaymentById($viewId) : null;

$statusCounts = getAdminPaymentCountsByStatus();
$filterForQuery = ($statusFilter === 'all') ? null : $statusFilter;
$paginated = getAdminPaymentsPaginated($filterForQuery, $searchQ, $recordStatus, $page, $perPage);
$payments = $paginated['rows'];
$page = $paginated['page'];
$totalPages = $paginated['total_pages'];
$totalMatching = $paginated['total'];

$totalPaid = (float) adminDb(
    fn () => db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS s FROM payments p WHERE p.status='paid' AND " . adminSqlActive('p'))['s'] ?? 0,
    0
);

$paginationQuery = adminPaymentsQuery();

renderAdminPageStart('Payment Management', 'payments', 'fa-credit-card');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<?php if ($viewPayment):
    $inTrash = adminUserRecordStatus($viewPayment) === 'deleted';
    $blocked = adminRecordIsBlocked($viewPayment);
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>Payment #<?= (int) $viewPayment['id'] ?> — <?= htmlspecialchars($viewPayment['order_id']) ?></span>
    <?php adminRenderPaymentStatusBadge((string) $viewPayment['status']); ?>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>User name</label><span><?= htmlspecialchars($viewPayment['user_name'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Email</label><span><?php if ($viewPayment['user_email']): ?><a href="mailto:<?= htmlspecialchars($viewPayment['user_email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewPayment['user_email']) ?></a><?php else: ?>—<?php endif; ?></span></div>
      <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewPayment['user_phone'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Webinar / bootcamp</label><span><?= htmlspecialchars(adminPaymentProgramLabel($viewPayment)) ?></span></div>
      <div class="detail-item"><label>Order ID</label><span><code><?= htmlspecialchars($viewPayment['order_id']) ?></code></span></div>
      <div class="detail-item"><label>Transaction ID</label><span style="font-size:0.82rem;word-break:break-all"><?= htmlspecialchars(adminPaymentTransactionId($viewPayment)) ?></span></div>
      <div class="detail-item"><label>Amount</label><span style="color:var(--cyber-green);font-weight:700">₹<?= number_format((float) $viewPayment['amount'], 2) ?></span></div>
      <div class="detail-item"><label>Payment method</label><span><?= htmlspecialchars(adminPaymentDisplayMethod($viewPayment)) ?></span></div>
      <div class="detail-item"><label>Invoice</label><span><?= htmlspecialchars($viewPayment['invoice_no'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Date &amp; time</label><span><?= date('d M Y, h:i A', strtotime($viewPayment['created_at'])) ?><?php if (!empty($viewPayment['paid_at'])): ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)">Paid: <?= date('d M Y, h:i A', strtotime($viewPayment['paid_at'])) ?></span><?php endif; ?></span></div>
    </div>
    <?php if (!empty($viewPayment['notes'])): ?>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin:1rem 0 0.35rem">Notes</p>
    <div class="msg-body"><?= nl2br(htmlspecialchars($viewPayment['notes'])) ?></div>
    <?php endif; ?>
    <div class="submission-card-actions mt-3">
      <?php if (adminPaymentCanRefund($viewPayment)): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Mark this payment as refunded? Registration status will be updated.');">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" name="id" value="<?= (int) $viewPayment['id'] ?>">
        <input type="hidden" name="action" value="refund">
        <input type="hidden" name="stay_on_view" value="1">
        <?php adminPaymentsHiddenFields(); ?>
        <button type="submit" class="btn-sm-cyber btn-block"><i class="fas fa-undo"></i><span>Refund</span></button>
      </form>
      <?php endif; ?>
      <?php renderAdminRecordActions('payment', (int) $viewPayment['id'], $blocked, $inTrash); ?>
      <a href="<?= htmlspecialchars(adminPaymentsUrl()) ?>" class="btn-cancel">← Back to list</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-value" style="color:var(--cyber-green)">₹<?= number_format($totalPaid, 2) ?></div>
      <div class="stat-label">Total revenue (paid)</div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="stat-cards-row" style="margin-bottom:0">
      <?php foreach (['paid' => 'Paid', 'created' => 'Created', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $key => $label): ?>
      <div class="stat-card">
        <div class="stat-value" style="font-size:1.35rem"><?= number_format($statusCounts[$key] ?? 0) ?></div>
        <div class="stat-label"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">All payments (<?= number_format($totalMatching) ?> matching)</div>
  <div class="section-card-body">
    <div class="admin-toolbar" style="margin-bottom:0.75rem">
      <div class="filter-bar" style="margin-bottom:0">
        <a href="<?= htmlspecialchars(adminPaymentsUrl(['status' => 'active', 'payment_status' => null, 'page' => '1'])) ?>" class="<?= $recordStatus === 'active' ? 'active' : '' ?>">Active records</a>
        <a href="<?= htmlspecialchars(adminPaymentsUrl(['status' => 'deleted', 'page' => '1'])) ?>" class="<?= $recordStatus === 'deleted' ? 'active' : '' ?>">Deleted</a>
      </div>
      <form method="get" action="<?= adminUrl('admin/payments.php') ?>" class="admin-search-form">
        <input type="hidden" name="status" value="<?= htmlspecialchars($recordStatus) ?>">
        <?php if ($statusFilter !== 'all'): ?><input type="hidden" name="payment_status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
        <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
        <input type="search" name="q" class="form-control" placeholder="Search name, email, order, transaction…" value="<?= htmlspecialchars($searchQ) ?>">
        <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
        <?php if ($searchQ !== ''): ?>
        <a href="<?= htmlspecialchars(adminPaymentsUrl(['q' => null, 'page' => '1'])) ?>" class="btn-cancel" style="padding:0.5rem 0.75rem;font-size:0.8rem">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="filter-bar mb-2">
      <a href="<?= htmlspecialchars(adminPaymentsUrl(['payment_status' => 'all', 'page' => '1'])) ?>" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All payments<span class="filter-count">(<?= (int) ($statusCounts['all'] ?? 0) ?>)</span></a>
      <?php foreach (['paid' => 'Paid', 'created' => 'Created', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $key => $label): ?>
      <a href="<?= htmlspecialchars(adminPaymentsUrl(['payment_status' => $key, 'page' => '1'])) ?>" class="<?= $statusFilter === $key ? 'active' : '' ?>"><?= $label ?><span class="filter-count">(<?= (int) ($statusCounts[$key] ?? 0) ?>)</span></a>
      <?php endforeach; ?>
    </div>

    <?php
    $renderPaymentActions = static function (array $p) use ($page): void {
        $id = (int) $p['id'];
        $inTrash = adminUserRecordStatus($p) === 'deleted';
        $blocked = adminRecordIsBlocked($p);
        ?>
      <a href="<?= htmlspecialchars(adminPaymentsUrl([], $id)) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-eye"></i><span>View</span></a>
      <?php if (adminPaymentCanRefund($p)): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Mark payment #<?= $id ?> as refunded?');">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="action" value="refund">
        <?php adminPaymentsHiddenFields(); ?>
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <button type="submit" class="btn-sm-cyber btn-block"><i class="fas fa-undo"></i><span>Refund</span></button>
      </form>
      <?php endif; ?>
      <?php renderAdminRecordActions('payment', $id, $blocked, $inTrash);
    };
    ?>

    <div class="table-responsive submission-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date &amp; time</th>
            <th>User</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Webinar / bootcamp</th>
            <th>Order / transaction</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$payments): ?>
          <tr><td colspan="10" style="color:var(--cyber-muted);padding:1.5rem">No payments match this filter. <a href="<?= htmlspecialchars(adminPaymentsUrl(['payment_status' => 'all'])) ?>" class="btn-sm-link">Show all</a></td></tr>
          <?php else: foreach ($payments as $p): ?>
          <tr<?= renderAdminRecordRowAttrs('payment', (int) $p['id'], adminRecordIsBlocked($p)) ?>>
            <td style="white-space:nowrap;font-size:0.82rem"><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
            <td><?= htmlspecialchars($p['user_name'] ?: '—') ?></td>
            <td style="font-size:0.82rem"><?= htmlspecialchars($p['user_email'] ?: '—') ?></td>
            <td><?= htmlspecialchars($p['user_phone'] ?: '—') ?></td>
            <td style="font-size:0.82rem;max-width:180px"><?= htmlspecialchars(adminPaymentProgramLabel($p)) ?></td>
            <td style="font-size:0.75rem;max-width:140px;word-break:break-all">
              <div><?= htmlspecialchars($p['order_id']) ?></div>
              <?php if ($p['razorpay_payment_id'] || $p['razorpay_order_id']): ?>
              <div style="color:var(--cyber-muted);margin-top:0.2rem"><?= htmlspecialchars(adminPaymentTransactionId($p)) ?></div>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;font-weight:600;color:var(--cyber-green)">₹<?= number_format((float) $p['amount'], 2) ?></td>
            <td style="font-size:0.82rem"><?= htmlspecialchars(adminPaymentDisplayMethod($p)) ?></td>
            <td><?php adminRenderPaymentStatusBadge((string) $p['status']); ?></td>
            <td><div class="admin-action-btns d-inline-flex flex-wrap gap-1"><?php $renderPaymentActions($p); ?></div></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="submission-cards">
      <?php if (!$payments): ?>
      <p style="color:var(--cyber-muted)">No payments match this filter.</p>
      <?php else: foreach ($payments as $p): ?>
      <article class="submission-card">
        <div class="submission-card-head">
          <strong><?= htmlspecialchars($p['user_name'] ?: 'Unknown user') ?></strong>
          <?php adminRenderPaymentStatusBadge((string) $p['status']); ?>
        </div>
        <div class="submission-card-meta">
          <div><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars(adminPaymentProgramLabel($p)) ?></div>
          <div><i class="fas fa-clock"></i> <?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></div>
          <div><i class="fas fa-envelope"></i> <?= htmlspecialchars($p['user_email'] ?: '—') ?></div>
          <div><i class="fas fa-phone"></i> <?= htmlspecialchars($p['user_phone'] ?: '—') ?></div>
          <div><i class="fas fa-receipt"></i> <?= htmlspecialchars($p['order_id']) ?></div>
          <div><i class="fas fa-indian-rupee-sign"></i> ₹<?= number_format((float) $p['amount'], 2) ?> · <?= htmlspecialchars(adminPaymentDisplayMethod($p)) ?></div>
        </div>
        <div class="submission-card-actions"><?php $renderPaymentActions($p); ?></div>
      </article>
      <?php endforeach; endif; ?>
    </div>

    <?php renderAdminPagination($page, $totalPages, $totalMatching, 'admin/payments.php', $paginationQuery); ?>

    <form method="get" action="<?= adminUrl('admin/payments.php') ?>" class="mt-2" style="font-size:0.82rem;color:var(--cyber-muted)">
      <?php foreach (adminPaymentsQuery() as $k => $v): ?>
        <?php if ($k !== 'per_page' && $k !== 'page'): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      Per page:
      <select name="per_page" class="form-select d-inline-block" style="width:auto;display:inline-block;padding:0.25rem 0.5rem!important;font-size:0.82rem!important" onchange="this.form.submit()">
        <?php foreach ([10, 25, 50, 100] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
