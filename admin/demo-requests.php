<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/demo-requests.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

$viewId = (int) ($_GET['view'] ?? 0);
$status = sanitize($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'deleted'], true)) {
    $status = 'active';
}
$searchQ = trim((string) ($_GET['q'] ?? ''));

$viewRow = $viewId > 0 ? getDemoRequestById($viewId) : null;
$rows = adminFetchDemoRequests(['status' => $status, 'q' => $searchQ, 'limit' => 300]);

renderAdminPageStart('Product Demo Requests', 'demo-requests', 'fa-desktop');
?>

<?php if ($viewRow):
    $inTrash = adminUserRecordStatus($viewRow) === 'deleted';
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>Demo request #<?= (int) $viewRow['id'] ?></span>
    <?php adminRenderUserStatusBadge($viewRow); ?>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Name</label><span><?= htmlspecialchars($viewRow['full_name']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><a href="mailto:<?= htmlspecialchars($viewRow['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewRow['email']) ?></a></span></div>
      <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewRow['phone'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Organization</label><span><?= htmlspecialchars($viewRow['org_name'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Product</label><span><?= htmlspecialchars($viewRow['product_name'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Technology</label><span><?= htmlspecialchars($viewRow['interested_technology'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Preferred date</label><span><?= htmlspecialchars($viewRow['preferred_date'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Preferred time</label><span><?= htmlspecialchars($viewRow['preferred_time'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Submitted</label><span><?= date('d M Y, h:i A', strtotime($viewRow['created_at'])) ?></span></div>
    </div>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin:1rem 0 0.35rem">Message</p>
    <div class="msg-body"><?= nl2br(htmlspecialchars($viewRow['message'] ?: '—')) ?></div>
    <div class="mt-3">
      <?php renderAdminRecordActions('demo_request', (int) $viewRow['id'], false, $inTrash); ?>
      <a href="<?= adminUrl('admin/demo-requests.php?status=' . rawurlencode($status)) ?>" class="btn-cancel ms-2">← Back</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">All demo requests (<?= count($rows) ?> shown)</div>
  <div class="section-card-body">
    <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:1rem">Submissions from the product demo modal on <code>products.php</code>.</p>
    <?php renderAdminListToolbar('admin/demo-requests.php', $status, $searchQ, [
        'active'  => 'Active',
        'deleted' => 'Deleted',
    ]); ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Status</th><th>Date</th><th>Name</th><th>Email</th><th>Product</th><th>Organization</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No demo requests yet.</td></tr>
          <?php else: foreach ($rows as $r):
            $inTrash = adminUserRecordStatus($r) === 'deleted';
          ?>
          <tr<?= renderAdminRecordRowAttrs('demo_request', (int) $r['id'], false) ?>>
            <td><?php adminRenderUserStatusBadge($r); ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['product_name'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['org_name'] ?: '—') ?></td>
            <td>
              <a href="<?= adminUrl('admin/demo-requests.php?view=' . (int) $r['id']) ?>" class="btn-sm-link">View</a>
              <?php renderAdminRecordActions('demo_request', (int) $r['id'], false, $inTrash); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
