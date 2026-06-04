<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/get-started-inquiries.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

$viewId = (int) ($_GET['view'] ?? 0);
$status = sanitize($_GET['status'] ?? 'active');
$stage = sanitize($_GET['stage'] ?? 'all');
$searchQ = trim((string) ($_GET['q'] ?? ''));

if (!in_array($status, ['active', 'deleted'], true)) {
    $status = 'active';
}
if (!in_array($stage, ['all', 'new', 'contacted', 'closed'], true)) {
    $stage = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inquiry_status'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/get-started-inquiries.php', 'error', 'Invalid security token.');
    }
    $inquiryId = (int) ($_POST['inquiry_id'] ?? 0);
    $newStatus = sanitize($_POST['inquiry_status'] ?? '');
    $notes = trim((string) ($_POST['admin_notes'] ?? ''));
    if ($inquiryId < 1 || !updateGetStartedInquiryStatus($inquiryId, $newStatus, $notes)) {
        adminFlashRedirect('admin/get-started-inquiries.php', 'error', 'Could not update inquiry status.');
    }
    adminFlashRedirect(
        'admin/get-started-inquiries.php?view=' . $inquiryId . '&status=' . rawurlencode($status) . '&stage=' . rawurlencode($stage),
        'success',
        'Inquiry status updated.'
    );
}

$viewRow = $viewId > 0 ? getGetStartedInquiryById($viewId) : null;
$rows = adminFetchGetStartedInquiries(['status' => $status, 'stage' => $stage, 'q' => $searchQ, 'limit' => 300]);
$newCount = getGetStartedInquiryCount('new');

renderAdminPageStart('Get Started Inquiries', 'get-started-inquiries', 'fa-rocket');
?>

<?php if ($viewRow):
    $inTrash = adminUserRecordStatus($viewRow) === 'deleted';
    $statusLabels = getStartedInquiryStatusLabels();
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>Get Started inquiry #<?= (int) $viewRow['id'] ?></span>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <?php renderGetStartedStatusBadge((string) ($viewRow['inquiry_status'] ?? 'new')); ?>
      <?php adminRenderUserStatusBadge($viewRow); ?>
    </div>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Name</label><span><?= htmlspecialchars($viewRow['full_name']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><a href="mailto:<?= htmlspecialchars($viewRow['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewRow['email']) ?></a></span></div>
      <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewRow['phone']) ?></span></div>
      <div class="detail-item"><label>Company</label><span><?= htmlspecialchars($viewRow['company'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Service</label><span><?= htmlspecialchars($viewRow['service_interested']) ?></span></div>
      <div class="detail-item"><label>Project type</label><span><?= htmlspecialchars($viewRow['project_type'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Budget</label><span><?= htmlspecialchars($viewRow['budget_range'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Preferred contact</label><span><?= htmlspecialchars(ucfirst((string) ($viewRow['preferred_contact'] ?? 'email'))) ?></span></div>
      <div class="detail-item"><label>Submitted</label><span><?= date('d M Y, h:i A', strtotime((string) $viewRow['created_at'])) ?></span></div>
    </div>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin:1rem 0 0.35rem">Requirements / Message</p>
    <div class="msg-body"><?= nl2br(htmlspecialchars($viewRow['message'])) ?></div>
    <?php if (!empty($viewRow['admin_notes'])): ?>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin:1rem 0 0.35rem">Admin notes</p>
    <div class="msg-body"><?= nl2br(htmlspecialchars((string) $viewRow['admin_notes'])) ?></div>
    <?php endif; ?>

    <?php if (!$inTrash): ?>
    <form method="POST" class="mt-3" style="max-width:480px">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="inquiry_id" value="<?= (int) $viewRow['id'] ?>">
      <input type="hidden" name="update_inquiry_status" value="1">
      <div class="mb-2">
        <label class="form-label">Status</label>
        <select name="inquiry_status" class="form-select">
          <?php foreach ($statusLabels as $key => $label): ?>
          <option value="<?= htmlspecialchars($key) ?>"<?= ($viewRow['inquiry_status'] ?? '') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-2">
        <label class="form-label">Admin notes (optional)</label>
        <textarea name="admin_notes" class="form-control" rows="3" placeholder="Internal notes about follow-up..."><?= htmlspecialchars((string) ($viewRow['admin_notes'] ?? '')) ?></textarea>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Update Status</button>
    </form>
    <?php endif; ?>

    <div class="mt-3">
      <?php renderAdminRecordActions('get_started_inquiry', (int) $viewRow['id'], false, $inTrash); ?>
      <a href="<?= adminUrl('admin/get-started-inquiries.php?status=' . rawurlencode($status) . '&stage=' . rawurlencode($stage)) ?>" class="btn-cancel ms-2">← Back</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">All Get Started inquiries (<?= count($rows) ?> shown<?= $newCount > 0 ? ' · ' . $newCount . ' new' : '' ?>)</div>
  <div class="section-card-body">
    <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:1rem">Submissions from the <code>get-started.php</code> inquiry form.</p>
    <div class="admin-toolbar">
      <div class="filter-bar" style="margin-bottom:0">
        <?php
        $qParam = $searchQ !== '' ? '&q=' . rawurlencode($searchQ) : '';
        foreach (['all' => 'All stages', 'new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'] as $key => $label):
            $href = adminUrl('admin/get-started-inquiries.php?status=' . rawurlencode($status) . '&stage=' . rawurlencode($key) . $qParam);
            $cls = $stage === $key ? 'active' : '';
        ?>
        <a href="<?= htmlspecialchars($href) ?>" class="<?= $cls ?>"><?= htmlspecialchars($label) ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" action="<?= adminUrl('admin/get-started-inquiries.php') ?>" class="admin-search-form">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <input type="hidden" name="stage" value="<?= htmlspecialchars($stage) ?>">
        <input type="search" name="q" class="form-control" placeholder="Search name, email, service..." value="<?= htmlspecialchars($searchQ) ?>">
        <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
      </form>
    </div>
    <div class="filter-bar mb-3">
      <?php foreach (['active' => 'Active', 'deleted' => 'Deleted'] as $key => $label):
          $href = adminUrl('admin/get-started-inquiries.php?status=' . rawurlencode($key) . '&stage=' . rawurlencode($stage) . $qParam);
          $cls = $status === $key ? 'active' : '';
      ?>
      <a href="<?= htmlspecialchars($href) ?>" class="<?= $cls ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Status</th><th>Date</th><th>Name</th><th>Email</th><th>Service</th><th>Contact</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No Get Started inquiries yet.</td></tr>
          <?php else: foreach ($rows as $r):
            $inTrash = adminUserRecordStatus($r) === 'deleted';
          ?>
          <tr<?= renderAdminRecordRowAttrs('get_started_inquiry', (int) $r['id'], false) ?>>
            <td><?php renderGetStartedStatusBadge((string) ($r['inquiry_status'] ?? 'new')); ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime((string) $r['created_at'])) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['service_interested']) ?></td>
            <td><?= htmlspecialchars(ucfirst((string) ($r['preferred_contact'] ?? 'email'))) ?></td>
            <td>
              <a href="<?= adminUrl('admin/get-started-inquiries.php?view=' . (int) $r['id'] . '&status=' . rawurlencode($status) . '&stage=' . rawurlencode($stage)) ?>" class="btn-sm-link">View</a>
              <?php renderAdminRecordActions('get_started_inquiry', (int) $r['id'], false, $inTrash); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
