<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/catalog-content.php';
requireAdminLogin();

$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['edit'] ?? 0);
$viewId = (int) ($_GET['view'] ?? 0);
$msg = '';
$msgType = 'success';

$flash = getFlash();
if ($flash) {
    $msg = (string) $flash['message'];
    $msgType = ($flash['type'] ?? '') === 'error' ? 'error' : 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/assignments.php', 'error', 'Invalid request. Please try again.');
    }

    $postId = (int) ($_POST['assignment_id'] ?? 0);
    $data = [
        'title'       => sanitize($_POST['title'] ?? ''),
        'slug'        => slugify($_POST['title'] ?? ''),
        'short_desc'  => sanitize($_POST['short_desc'] ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'category'    => sanitize($_POST['category'] ?? ''),
        'type'        => sanitize($_POST['type'] ?? 'Project'),
        'skills'      => sanitize($_POST['skills'] ?? ''),
        'duration'    => sanitize($_POST['duration'] ?? ''),
        'mode'        => sanitize($_POST['mode'] ?? ''),
        'status'      => sanitize($_POST['status'] ?? 'Open'),
        'apply_route' => sanitize($_POST['apply_route'] ?? ''),
        'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
    ];

    if (strlen($data['title']) < 3) {
        $back = $postId > 0 ? 'admin/assignments.php?edit=' . $postId : 'admin/assignments.php?action=add';
        adminFlashRedirect($back, 'error', 'Title is required (min 3 characters).');
    }

    $nonceScope = $postId > 0 ? 'assignment_edit_' . $postId : 'assignment_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/assignments.php', 'error', 'This hands-on project was already saved. Refresh the page to add another.');
    }

    if ($postId > 0) {
        db()->execute(
            'UPDATE assignments SET title=?,slug=?,short_desc=?,description=?,category=?,type=?,skills=?,duration=?,mode=?,status=?,apply_route=?,sort_order=?,updated_at=NOW() WHERE id=?',
            array_merge(array_values($data), [$postId])
        );
        adminFlashRedirect('admin/assignments.php', 'success', 'Hands-on Projects entry updated successfully!');
    }

    adminCatalogRejectCreateIfAtLimit('assignments', 'admin/assignments.php');

    $slug = $data['slug'];
    if (db()->fetchOne('SELECT id FROM assignments WHERE slug = ?', [$slug])) {
        $data['slug'] = $slug . '-' . time();
    }
    db()->execute(
        'INSERT INTO assignments (title,slug,short_desc,description,category,type,skills,duration,mode,status,apply_route,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        array_values($data)
    );
    adminFlashRedirect('admin/assignments.php', 'success', 'Hands-on Projects entry created successfully!');
}

$editRow = null;
if ($editId > 0) {
    $editRow = catalogGetAssignmentById($editId);
    if (!$editRow) {
        adminFlashRedirect('admin/assignments.php', 'error', 'Hands-on Projects entry not found.');
    }
    $action = 'add';
}

$viewRow = null;
if ($viewId > 0 && $action !== 'add') {
    $viewRow = catalogGetAssignmentById($viewId);
    if (!$viewRow) {
        adminFlashRedirect('admin/assignments.php', 'error', 'Hands-on Projects entry not found.');
    }
}

adminCatalogRejectAddActionIfAtLimit('assignments', $action, $editId, 'admin/assignments.php');

$formNonce = adminIssueFormNonce($editId > 0 ? 'assignment_edit_' . $editId : 'assignment_create');

try {
    $items = catalogGetAssignments(true);
} catch (Throwable $e) {
    $items = [];
    $msg = 'Could not load hands-on projects: ' . $e->getMessage();
    $msgType = 'error';
}

$pageTitle = $action === 'add' ? ($editId ? 'Edit Hands-on Projects' : 'Add Hands-on Projects') : 'Hands-on Projects';
renderAdminPageStart($pageTitle, 'assignments', 'fa-briefcase');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<?php adminCatalogRenderListToolbar('assignments', 'admin/assignments.php?action=add', 'Add Hands-on Projects'); ?>
<?php endif; ?>

<?php if ($viewRow): ?>
<div class="section-card mb-3">
  <div class="section-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View Hands-on Projects</span>
    <div class="d-flex gap-2">
      <a href="<?= adminUrl('admin/assignments.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i> Edit</a>
      <a href="<?= url('assignment-register.php?assignment=' . urlencode($viewRow['slug'])) ?>" target="_blank" rel="noopener" class="btn-sm-link">View on site →</a>
      <a href="<?= adminUrl('admin/assignments.php') ?>" class="btn-cancel">Back to list</a>
    </div>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Title</label><div><?= htmlspecialchars($viewRow['title']) ?></div></div>
      <div class="detail-item"><label>Slug</label><div><?= htmlspecialchars($viewRow['slug']) ?></div></div>
      <div class="detail-item"><label>Type</label><div><?= htmlspecialchars($viewRow['type']) ?></div></div>
      <div class="detail-item"><label>Category</label><div><?= htmlspecialchars($viewRow['category'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Status</label><div><span class="badge-status badge-paid"><?= htmlspecialchars($viewRow['status']) ?></span></div></div>
      <div class="detail-item"><label>Mode</label><div><?= htmlspecialchars($viewRow['mode'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Duration</label><div><?= htmlspecialchars($viewRow['duration'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Skills</label><div><?= htmlspecialchars($viewRow['skills'] ?: '—') ?></div></div>
    </div>
    <div class="msg-body mt-2"><?= nl2br(htmlspecialchars($viewRow['description'] ?? '')) ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Hands-on Projects' : 'Create Hands-on Projects' ?></div>
  <div class="form-card-body">
    <form method="POST" id="assignmentAdminForm" action="<?= adminUrl('admin/assignments.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?><input type="hidden" name="assignment_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Role Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editRow['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select name="type" class="form-select">
            <?php foreach (catalogAssignmentTypeOptions() as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= ($editRow['type'] ?? 'Project') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($editRow['category'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (catalogAssignmentStatusOptions() as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= ($editRow['status'] ?? 'Open') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Short Description</label>
          <input type="text" name="short_desc" class="form-control" value="<?= htmlspecialchars($editRow['short_desc'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Full Description</label>
          <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skills</label>
          <input type="text" name="skills" class="form-control" value="<?= htmlspecialchars($editRow['skills'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration</label>
          <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($editRow['duration'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Mode</label>
          <input type="text" name="mode" class="form-control" placeholder="Remote, Hybrid" value="<?= htmlspecialchars($editRow['mode'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Apply route (optional)</label>
          <input type="text" name="apply_route" class="form-control" placeholder="index.php?register_required=1" value="<?= htmlspecialchars($editRow['apply_route'] ?? '') ?>">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit" id="assignmentSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update' : 'Create' ?></button>
          <a href="<?= adminUrl('admin/assignments.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<div class="section-card">
  <div class="section-card-header">All hands-on projects (<?= adminCatalogUsageLabel('assignments') ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Title</th><th>Type</th><th>Category</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$items): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No hands-on projects. <?php if (adminCatalogCanCreate('assignments')): ?><a href="<?= adminUrl('admin/assignments.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a><?php endif; ?></td></tr>
          <?php else: foreach ($items as $i => $row):
            $blocked = adminRecordIsBlocked($row);
          ?>
          <tr<?= renderAdminRecordRowAttrs('assignment', (int) $row['id'], $blocked) ?>>
            <td style="color:var(--cyber-muted);font-size:0.78rem"><?= $i + 1 ?></td>
            <td>
              <strong><?= htmlspecialchars($row['title']) ?></strong><br>
              <a href="<?= url('hands-on-projects.php') ?>" target="_blank" rel="noopener" class="btn-sm-link" style="font-size:0.75rem">View on site →</a>
            </td>
            <td><?= htmlspecialchars($row['type']) ?></td>
            <td><?= htmlspecialchars($row['category'] ?: '—') ?></td>
            <td><?= htmlspecialchars($row['duration'] ?: '—') ?></td>
            <td><span class="badge-status badge-paid"><?= htmlspecialchars($row['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/assignments.php?view=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="View"><i class="fas fa-eye"></i></a>
              <a href="<?= adminUrl('admin/assignments.php?edit=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <?php renderAdminRecordActions('assignment', (int) $row['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('assignmentAdminForm', 'assignmentSubmitBtn'); ?>
<?php renderAdminPageEnd(); ?>
