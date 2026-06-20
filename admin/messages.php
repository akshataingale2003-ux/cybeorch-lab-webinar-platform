<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/resume-upload.php';
requireAdminLogin();

$msg = '';
$msgType = 'success';
$viewId = (int) ($_GET['view'] ?? 0);
$subjectFilter = sanitize($_GET['subject'] ?? '');
$readFilter = sanitize($_GET['read'] ?? '');
$sort = adminParseListSortParam();
$messagesQueryExtra = [];
if ($subjectFilter !== '') {
    $messagesQueryExtra['subject'] = $subjectFilter;
}
if ($readFilter !== '') {
    $messagesQueryExtra['read'] = $readFilter;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id = (int) ($_POST['id'] ?? 0);
    $replied = !empty($_POST['replied']);
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($id > 0) {
        updateContactMessageStatus($id, $replied, $notes);
        $msg = 'Message updated.';
        $viewId = $id;
    } else {
        $msg = 'Invalid request.';
        $msgType = 'error';
    }
}

$viewMessage = $viewId > 0 ? getContactMessageById($viewId) : null;
if ($viewMessage && !(int) $viewMessage['is_read']) {
    markContactMessageRead($viewId);
    $viewMessage['is_read'] = 1;
}

$messages = getContactMessages(
    $subjectFilter !== '' ? $subjectFilter : null,
    in_array($readFilter, ['read', 'unread'], true) ? $readFilter : null,
    $sort
);

renderAdminPageStart('Messages & Enquiries', 'messages', 'fa-envelope');
?>

<?php if ($msg): ?><div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($viewMessage): ?>
<div class="section-card">
  <div class="section-card-header">Message #<?= (int) $viewMessage['id'] ?> — <?= htmlspecialchars(contactMessageSubjectDisplay($viewMessage['subject'] ?? '')) ?></div>
  <div class="section-card-body"<?= renderAdminRecordRowAttrs('contact_message', (int) $viewMessage['id'], adminRecordIsBlocked($viewMessage)) ?>>
    <p class="mb-3"><?php renderAdminRecordActions('contact_message', (int) $viewMessage['id'], adminRecordIsBlocked($viewMessage)); ?></p>
    <div class="detail-grid">
      <div class="detail-item"><label>Name</label><span><?= htmlspecialchars($viewMessage['name']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><a href="mailto:<?= htmlspecialchars($viewMessage['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewMessage['email']) ?></a></span></div>
      <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewMessage['phone'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Source</label><span><?= htmlspecialchars(contactMessageSubjectDisplay($viewMessage['subject'] ?? '')) ?></span></div>
      <div class="detail-item"><label>Received</label><span><?= date('d M Y, h:i A', strtotime($viewMessage['created_at'])) ?></span></div>
      <div class="detail-item"><label>Status</label>
        <span>
          <?php if ((int) $viewMessage['replied']): ?><span class="badge-status badge-paid">Replied</span>
          <?php elseif ((int) $viewMessage['is_read']): ?><span class="badge-status badge-read">Read</span>
          <?php else: ?><span class="badge-status badge-unread">New</span><?php endif; ?>
        </span>
      </div>
      <?php if (!empty($viewMessage['resume_path'])): ?>
      <div class="detail-item"><label>Resume / CV</label><span><?php renderAdminResumeLink($viewMessage['resume_path'] ?? null, $viewMessage['resume_original_name'] ?? null); ?></span></div>
      <?php endif; ?>
    </div>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin-bottom:0.5rem">Message</p>
    <div class="msg-body mb-3"><?= nl2br(htmlspecialchars($viewMessage['message'])) ?></div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="id" value="<?= (int) $viewMessage['id'] ?>">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label d-flex align-items-center gap-2">
            <input type="checkbox" name="replied" value="1" <?= (int) $viewMessage['replied'] ? 'checked' : '' ?>> Mark as replied
          </label>
        </div>
        <div class="col-md-6">
          <label class="form-label">Admin notes (internal)</label>
          <input type="text" name="admin_notes" class="form-control" value="<?= htmlspecialchars($viewMessage['admin_notes'] ?? '') ?>" placeholder="Follow-up notes">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn-submit w-100"><i class="fas fa-save me-1"></i>Save</button>
        </div>
      </div>
    </form>
    <a href="<?= adminUrl('messages.php') ?>" class="btn-sm-link d-inline-block mt-3">← Back to all messages</a>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">All form submissions (<?= count($messages) ?>)</div>
  <div class="section-card-body">
    <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      Enquiries saved here: contact, Enquire &amp; Enroll, hands-on projects, freelance apply, start project, book consulting, secure payment, and collaboration summaries.
      Product demos and full collaboration records are under <a href="<?= adminUrl('admin/form-submissions.php') ?>" class="btn-sm-link">Form submissions</a>.
    </p>
    <?php renderAdminSortBar('admin/messages.php', $sort, $messagesQueryExtra); ?>
    <div class="filter-bar">
      <?php foreach (contactMessageSubjectTypes() as $val => $label):
        $q = array_merge($messagesQueryExtra, ['sort' => $sort]);
        if ($val !== '') {
            $q['subject'] = $val;
        } else {
            unset($q['subject']);
        }
        unset($q['read']);
      ?>
      <a href="<?= adminUrl('admin/messages.php?' . http_build_query($q)) ?>"
         class="<?= $subjectFilter === $val ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
      <?php endforeach; ?>
      <?php
      $unreadQ = array_merge($messagesQueryExtra, ['sort' => $sort, 'read' => 'unread']);
      $readQ = array_merge($messagesQueryExtra, ['sort' => $sort, 'read' => 'read']);
      ?>
      <a href="<?= adminUrl('admin/messages.php?' . http_build_query($unreadQ)) ?>"
         class="<?= $readFilter === 'unread' ? 'active' : '' ?>">Unread only</a>
      <a href="<?= adminUrl('admin/messages.php?' . http_build_query($readQ)) ?>"
         class="<?= $readFilter === 'read' ? 'active' : '' ?>">Read only</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Date</th><th>Name</th><th>Email</th><th>Source</th><th>Preview</th><th>Resume</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$messages): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No messages yet.</td></tr>
          <?php else: foreach ($messages as $m):
            $blocked = adminRecordIsBlocked($m);
          ?>
          <tr<?= renderAdminRecordRowAttrs('contact_message', (int) $m['id'], $blocked) ?>>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= htmlspecialchars($m['email']) ?></td>
            <td><?= htmlspecialchars(contactMessageSubjectDisplay($m['subject'] ?? '')) ?></td>
            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--cyber-muted)">
              <?= htmlspecialchars(substr($m['message'], 0, 60)) ?>…
            </td>
            <td><?php if (!empty($m['resume_path'])): ?><i class="fas fa-file-alt" style="color:var(--cyber-accent)" title="Resume attached"></i><?php else: ?>—<?php endif; ?></td>
            <td>
              <?php if ((int) $m['replied']): ?><span class="badge-status badge-paid">Replied</span>
              <?php elseif (!(int) $m['is_read']): ?><span class="badge-status badge-unread">New</span>
              <?php else: ?><span class="badge-status badge-read">Read</span><?php endif; ?>
            </td>
            <td>
              <a href="<?= adminUrl('messages.php?view=' . (int) $m['id']) ?>" class="btn-sm-link">View</a>
              <?php renderAdminRecordActions('contact_message', (int) $m['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
