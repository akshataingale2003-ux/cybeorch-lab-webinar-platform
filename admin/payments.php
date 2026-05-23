<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$statusFilter = sanitize($_GET['status'] ?? '');
$where = adminSqlActive('p');
$params = [];
if ($statusFilter && in_array($statusFilter, ['created', 'paid', 'failed', 'refunded'], true)) {
    $where .= ' AND p.status = ?';
    $params[] = $statusFilter;
}

$payments = adminDb(
    fn () => db()->fetchAll(
        "SELECT p.*, u.full_name, u.email FROM payments p
         JOIN users u ON u.id = p.user_id
         WHERE {$where} ORDER BY p.created_at DESC LIMIT 200",
        $params
    ),
    []
);

$totalPaid = (float) adminDb(
    fn () => db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status='paid' AND " . adminSqlActive())['s'] ?? 0,
    0
);

renderAdminPageStart('Payments', 'payments', 'fa-credit-card');
?>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="section-card"><div class="section-card-body">
      <div style="font-size:0.8rem;color:var(--cyber-muted)">Total revenue (paid)</div>
      <div style="font-family:'Rajdhani',sans-serif;font-size:2rem;color:var(--cyber-green)">₹<?= number_format((float) $totalPaid, 2) ?></div>
    </div></div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">All payments (<?= count($payments) ?>)</div>
  <div class="section-card-body">
    <div class="filter-bar">
      <a href="<?= url('admin/payments.php') ?>" class="<?= $statusFilter === '' ? 'active' : '' ?>">All</a>
      <?php foreach (['paid', 'created', 'failed', 'refunded'] as $s): ?>
      <a href="<?= url('admin/payments.php?status=' . $s) ?>" class="<?= $statusFilter === $s ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Date</th><th>User Registration &amp; Trainee</th><th>Order</th><th>For</th><th>Amount</th><th>Status</th><th>Invoice</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$payments): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No payments yet.</td></tr>
          <?php else: foreach ($payments as $p):
            $blocked = adminRecordIsBlocked($p);
          ?>
          <tr<?= renderAdminRecordRowAttrs('payment', (int) $p['id'], $blocked) ?>>
            <td><?= date('d M Y', strtotime($p['created_at'])) ?></td>
            <td><?= htmlspecialchars($p['full_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($p['email']) ?></span></td>
            <td style="font-size:0.8rem"><?= htmlspecialchars($p['order_id']) ?></td>
            <td><?= htmlspecialchars($p['payment_for']) ?> #<?= (int) $p['reference_id'] ?></td>
            <td>₹<?= number_format((float) $p['amount'], 2) ?></td>
            <td><span class="badge-status badge-<?= $p['status'] === 'paid' ? 'paid' : ($p['status'] === 'failed' ? 'failed' : 'pending') ?>"><?= htmlspecialchars($p['status']) ?></span></td>
            <td><?= htmlspecialchars($p['invoice_no'] ?: '—') ?></td>
            <td><?php renderAdminRecordActions('payment', (int) $p['id'], $blocked); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
