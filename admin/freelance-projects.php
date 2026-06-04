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
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'Invalid request. Please try again.');
    }

    $postId = (int) ($_POST['freelance_project_id'] ?? 0);
    $data = [
        'title'        => sanitize($_POST['title'] ?? ''),
        'slug'         => slugify($_POST['title'] ?? ''),
        'short_desc'   => sanitize($_POST['short_desc'] ?? ''),
        'description'  => sanitize($_POST['description'] ?? ''),
        'category'     => sanitize($_POST['category'] ?? ''),
        'skills'       => sanitize($_POST['skills'] ?? ''),
        'duration'     => sanitize($_POST['duration'] ?? ''),
        'mode'         => sanitize($_POST['mode'] ?? ''),
        'budget_label' => sanitize($_POST['budget_label'] ?? ''),
        'client_name'  => sanitize($_POST['client_name'] ?? ''),
        'image_path'   => sanitize($_POST['image_path'] ?? ''),
        'status'       => sanitize($_POST['status'] ?? 'Open'),
        'apply_route'  => sanitize($_POST['apply_route'] ?? 'register-freelancer.php'),
        'sort_order'   => (int) ($_POST['sort_order'] ?? 0),
    ];

    if (strlen($data['title']) < 3) {
        $back = $postId > 0 ? 'admin/freelance-projects.php?edit=' . $postId : 'admin/freelance-projects.php?action=add';
        adminFlashRedirect($back, 'error', 'Title is required (min 3 characters).');
    }

    $nonceScope = $postId > 0 ? 'freelance_project_edit_' . $postId : 'freelance_project_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'This freelance project was already saved. Refresh the page to add another.');
    }

    if ($postId > 0) {
        db()->execute(
            'UPDATE freelance_projects SET title=?,slug=?,short_desc=?,description=?,category=?,skills=?,duration=?,mode=?,budget_label=?,client_name=?,image_path=?,status=?,apply_route=?,sort_order=?,updated_at=NOW() WHERE id=?',
            array_merge(array_values($data), [$postId])
        );
        adminFlashRedirect('admin/freelance-projects.php', 'success', 'Freelance project updated successfully!');
    }

    adminCatalogRejectCreateIfAtLimit('freelance_projects', 'admin/freelance-projects.php');

    $slug = $data['slug'];
    if (db()->fetchOne('SELECT id FROM freelance_projects WHERE slug = ?', [$slug])) {
        $data['slug'] = $slug . '-' . time();
    }
    db()->execute(
        'INSERT INTO freelance_projects (title,slug,short_desc,description,category,skills,duration,mode,budget_label,client_name,image_path,status,apply_route,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array_values($data)
    );
    adminFlashRedirect('admin/freelance-projects.php', 'success', 'Freelance project created successfully!');
}

$editRow = null;
if ($editId > 0) {
    $editRow = catalogGetFreelanceProjectById($editId);
    if (!$editRow) {
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'Freelance project not found.');
    }
    $action = 'add';
}

$viewRow = null;
if ($viewId > 0 && $action !== 'add') {
    $viewRow = catalogGetFreelanceProjectById($viewId);
    if (!$viewRow) {
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'Freelance project not found.');
    }
}

adminCatalogRejectAddActionIfAtLimit('freelance_projects', $action, $editId, 'admin/freelance-projects.php');

$formNonce = adminIssueFormNonce($editId > 0 ? 'freelance_project_edit_' . $editId : 'freelance_project_create');

try {
    $items = catalogGetFreelanceProjects(true);
} catch (Throwable $e) {
    $items = [];
    $msg = 'Could not load freelance projects: ' . $e->getMessage();
    $msgType = 'error';
}

$pageTitle = $action === 'add' ? ($editId ? 'Edit Freelance Project' : 'Add Freelance Project') : 'Freelance Projects';
renderAdminPageStart($pageTitle, 'freelance-projects', 'fa-laptop-code');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<?php adminCatalogRenderListToolbar('freelance_projects', 'admin/freelance-projects.php?action=add', 'Add Freelance Project'); ?>
<?php endif; ?>

<?php if ($viewRow): ?>
<div class="section-card mb-3">
  <div class="section-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View Freelance Project</span>
    <div class="d-flex gap-2">
      <a href="<?= adminUrl('admin/freelance-projects.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i> Edit</a>
      <a href="<?= url('freelance-projects.php' . (!empty($viewRow['slug']) ? '?slug=' . urlencode((string) $viewRow['slug']) : '')) ?>" target="_blank" rel="noopener" class="btn-sm-link">View on site →</a>
      <a href="<?= adminUrl('admin/freelance-projects.php') ?>" class="btn-cancel">Back to list</a>
    </div>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Title</label><div><?= htmlspecialchars($viewRow['title']) ?></div></div>
      <div class="detail-item"><label>Slug</label><div><?= htmlspecialchars($viewRow['slug']) ?></div></div>
      <div class="detail-item"><label>Category</label><div><?= htmlspecialchars($viewRow['category'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Client</label><div><?= htmlspecialchars($viewRow['client_name'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Budget</label><div><?= htmlspecialchars($viewRow['budget_label'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Status</label><div><span class="badge-status badge-paid"><?= htmlspecialchars($viewRow['status']) ?></span></div></div>
      <div class="detail-item"><label>Mode</label><div><?= htmlspecialchars($viewRow['mode'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Duration</label><div><?= htmlspecialchars($viewRow['duration'] ?: '—') ?></div></div>
    </div>
    <div class="msg-body mt-2"><?= nl2br(htmlspecialchars($viewRow['description'] ?? '')) ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Freelance Project' : 'Create Freelance Project' ?></div>
  <div class="form-card-body">
    <form method="POST" id="freelanceProjectAdminForm" action="<?= adminUrl('admin/freelance-projects.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?><input type="hidden" name="freelance_project_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Project Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editRow['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($editRow['category'] ?? '') ?>">
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
          <label class="form-label">Budget Label</label>
          <input type="text" name="budget_label" class="form-control" placeholder="₹25,000 – ₹60,000" value="<?= htmlspecialchars($editRow['budget_label'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Client Name</label>
          <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($editRow['client_name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Image path (optional)</label>
          <input type="text" name="image_path" class="form-control" placeholder="assets/images/..." value="<?= htmlspecialchars($editRow['image_path'] ?? '') ?>">
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Use `assets/images/...` (spaces allowed). Leave empty to use the default thumbnail.</p>
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration</label>
          <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($editRow['duration'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Mode</label>
          <input type="text" name="mode" class="form-control" value="<?= htmlspecialchars($editRow['mode'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (catalogFreelanceProjectStatusOptions() as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= ($editRow['status'] ?? 'Open') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Apply route</label>
          <input type="text" name="apply_route" class="form-control" value="<?= htmlspecialchars($editRow['apply_route'] ?? 'register-freelancer.php') ?>">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit" id="freelanceProjectSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update' : 'Create' ?></button>
          <a href="<?= adminUrl('admin/freelance-projects.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<div class="section-card">
  <div class="section-card-header">All freelance projects (<?= adminCatalogUsageLabel('freelance_projects') ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Title</th><th>Category</th><th>Budget</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$items): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No freelance projects. <?php if (adminCatalogCanCreate('freelance_projects')): ?><a href="<?= adminUrl('admin/freelance-projects.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a><?php endif; ?></td></tr>
          <?php else: foreach ($items as $i => $row):
            $blocked = adminRecordIsBlocked($row);
          ?>
          <tr<?= renderAdminRecordRowAttrs('freelance_project', (int) $row['id'], $blocked) ?>>
            <td style="color:var(--cyber-muted);font-size:0.78rem"><?= $i + 1 ?></td>
            <td>
              <strong><?= htmlspecialchars($row['title']) ?></strong><br>
              <a href="<?= url('freelance-projects.php' . (!empty($row['slug']) ? '?slug=' . urlencode((string) $row['slug']) : '')) ?>" target="_blank" rel="noopener" class="btn-sm-link" style="font-size:0.75rem">View on site →</a>
            </td>
            <td><?= htmlspecialchars($row['category'] ?: '—') ?></td>
            <td><?= htmlspecialchars($row['budget'] ?: '—') ?></td>
            <td><?= htmlspecialchars($row['duration'] ?: '—') ?></td>
            <td><span class="badge-status badge-paid"><?= htmlspecialchars($row['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/freelance-projects.php?view=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="View"><i class="fas fa-eye"></i></a>
              <a href="<?= adminUrl('admin/freelance-projects.php?edit=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <?php renderAdminRecordActions('freelance_project', (int) $row['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('freelanceProjectAdminForm', 'freelanceProjectSubmitBtn'); ?>
<?php renderAdminPageEnd(); ?>
