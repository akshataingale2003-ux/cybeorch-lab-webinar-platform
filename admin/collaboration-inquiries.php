<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/collaboration-inquiries.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

$viewId = (int) ($_GET['view'] ?? 0);
$status = sanitize($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'deleted'], true)) {
    $status = 'active';
}
$searchQ = trim((string) ($_GET['q'] ?? ''));

$viewRow = $viewId > 0 ? getCollaborationInquiryById($viewId) : null;
$rows = adminFetchCollaborationInquiries(['status' => $status, 'q' => $searchQ, 'limit' => 300]);

renderAdminPageStart('Collaboration Inquiries', 'collaboration', 'fa-handshake');
?>

<?php if ($viewRow):
    $inTrash = adminUserRecordStatus($viewRow) === 'deleted';
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>Inquiry #<?= (int) $viewRow['id'] ?> — <?= htmlspecialchars($viewRow['project_title'] ?: 'Project') ?></span>
    <?php adminRenderUserStatusBadge($viewRow); ?>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Project</label><span><?= htmlspecialchars($viewRow['project_title'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Category</label><span><?= htmlspecialchars($viewRow['project_category'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Collaboration type</label><span><?= htmlspecialchars($viewRow['collaboration_type'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Budget</label><span><?= htmlspecialchars($viewRow['budget_range'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Timeline</label><span><?= htmlspecialchars($viewRow['timeline'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Company</label><span><?= htmlspecialchars($viewRow['company'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Name</label><span><?= htmlspecialchars($viewRow['full_name']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><a href="mailto:<?= htmlspecialchars($viewRow['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewRow['email']) ?></a></span></div>
      <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewRow['phone'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Submitted</label><span><?= date('d M Y, h:i A', strtotime($viewRow['created_at'])) ?></span></div>
    </div>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin:1rem 0 0.35rem">Description</p>
    <div class="msg-body mb-3"><?= nl2br(htmlspecialchars($viewRow['description'])) ?></div>
    <?php if (!empty($viewRow['requirement_file'])): ?>
    <p><a href="<?= htmlspecialchars(url($viewRow['requirement_file'])) ?>" class="btn-sm-link" target="_blank" rel="noopener"><i class="fas fa-paperclip me-1"></i><?= htmlspecialchars($viewRow['requirement_file_original'] ?: 'Download attachment') ?></a></p>
    <?php endif; ?>
    <div class="mt-3">
      <?php renderAdminRecordActions('collaboration_inquiry', (int) $viewRow['id'], false, $inTrash); ?>
      <a href="<?= adminUrl('admin/collaboration-inquiries.php?status=' . rawurlencode($status)) ?>" class="btn-cancel ms-2">← Back</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">All collaboration inquiries (<?= count($rows) ?> shown)</div>
  <div class="section-card-body">
    <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:1rem">Submissions from <code>collaborate-project.php</code>. A summary copy may also appear under Messages.</p>
    <?php renderAdminListToolbar('admin/collaboration-inquiries.php', $status, $searchQ, [
        'active'  => 'Active',
        'deleted' => 'Deleted',
    ]); ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Status</th><th>Date</th><th>Name</th><th>Email</th><th>Project</th><th>Type</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No collaboration inquiries yet.</td></tr>
          <?php else: foreach ($rows as $r):
            $inTrash = adminUserRecordStatus($r) === 'deleted';
          ?>
          <tr<?= renderAdminRecordRowAttrs('collaboration_inquiry', (int) $r['id'], false) ?>>
            <td><?php adminRenderUserStatusBadge($r); ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['project_title'] ?: '—') ?></td>
            <td><?= htmlspecialchars($r['collaboration_type'] ?: '—') ?></td>
            <td>
              <a href="<?= adminUrl('admin/collaboration-inquiries.php?view=' . (int) $r['id']) ?>" class="btn-sm-link">View</a>
              <?php renderAdminRecordActions('collaboration_inquiry', (int) $r['id'], false, $inTrash); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
