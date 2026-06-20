<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/catalog-content.php';
require_once __DIR__ . '/../includes/freelance-project-admin.php';
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

    $routeEditId = (int) ($_GET['edit'] ?? 0);
    $postId = (int) ($_POST['freelance_project_id'] ?? 0);
    if ($postId <= 0 && $routeEditId > 0) {
        $postId = $routeEditId;
    }
    if ($routeEditId > 0 && $postId > 0 && $postId !== $routeEditId) {
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'Invalid update request. Please reload and try again.');
    }
    $existing = $postId > 0 ? catalogGetFreelanceProjectById($postId) : null;
    if ($postId > 0 && !$existing) {
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'Freelance project not found.');
    }

    $built = freelanceAdminBuildPayload($_POST, $_FILES, $postId, $existing);
    if (!$built['ok']) {
        $back = $postId > 0 ? 'admin/freelance-projects.php?edit=' . $postId : 'admin/freelance-projects.php?action=add';
        adminFlashRedirect($back, 'error', $built['error']);
    }

    $data = $built['data'];
    $data['slug'] = freelanceAdminEnsureUniqueSlug((string) $data['slug'], $postId);

    $nonceScope = $postId > 0 ? 'freelance_project_edit_' . $postId : 'freelance_project_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/freelance-projects.php', 'error', 'This freelance project was already saved. Refresh the page to add another.');
    }

    if ($postId > 0) {
        db()->execute(
            'UPDATE freelance_projects SET title=?,slug=?,short_desc=?,description=?,category=?,skills=?,duration=?,mode=?,budget_label=?,client_name=?,image_path=?,status=?,apply_route=?,project_type=?,show_on_homepage=?,sort_order=?,updated_at=NOW() WHERE id=?',
            [
                $data['title'],
                $data['slug'],
                $data['short_desc'],
                $data['description'],
                $data['category'],
                $data['skills'],
                $data['duration'],
                $data['mode'],
                $data['budget_label'],
                $data['client_name'],
                $data['image_path'],
                $data['status'],
                $data['apply_route'],
                $data['project_type'],
                $data['show_on_homepage'],
                $data['sort_order'],
                $postId,
            ]
        );
        adminFlashRedirect('admin/freelance-projects.php', 'success', 'Freelance project updated successfully!');
    }

    adminCatalogRejectCreateIfAtLimit('freelance_projects', 'admin/freelance-projects.php');

    db()->execute(
        'INSERT INTO freelance_projects (title,slug,short_desc,description,category,skills,duration,mode,budget_label,client_name,image_path,status,apply_route,project_type,show_on_homepage,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $data['title'],
            $data['slug'],
            $data['short_desc'],
            $data['description'],
            $data['category'],
            $data['skills'],
            $data['duration'],
            $data['mode'],
            $data['budget_label'],
            $data['client_name'],
            $data['image_path'],
            $data['status'],
            $data['apply_route'],
            $data['project_type'],
            $data['show_on_homepage'],
            $data['sort_order'],
        ]
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

$editStatus = freelanceAdminNormalizeStatus((string) ($editRow['status'] ?? 'Active'));
$viewThumb = $viewRow ? freelanceProjectImageUrl($viewRow) : '';
$editThumb = $editRow ? freelanceProjectImageUrl($editRow) : '';

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
    <?php if ($viewThumb !== ''): ?>
    <div class="mb-3"><img src="<?= htmlspecialchars($viewThumb) ?>" alt="" style="max-width:220px;border-radius:8px;border:1px solid var(--cyber-border)"></div>
    <?php endif; ?>
    <div class="detail-grid">
      <div class="detail-item"><label>Title</label><div><?= htmlspecialchars($viewRow['title']) ?></div></div>
      <div class="detail-item"><label>Slug</label><div><?= htmlspecialchars($viewRow['slug']) ?></div></div>
      <div class="detail-item"><label>Category</label><div><?= htmlspecialchars($viewRow['category'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Skills</label><div><?= htmlspecialchars($viewRow['skills'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Budget</label><div><?= htmlspecialchars($viewRow['budget_label'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Status</label><div><span class="badge-status <?= strtolower((string) $viewRow['status']) === 'active' || strtolower((string) $viewRow['status']) === 'open' ? 'badge-free' : 'badge-paid' ?>"><?= htmlspecialchars(freelanceAdminNormalizeStatus((string) $viewRow['status'])) ?></span></div></div>
      <div class="detail-item"><label>Duration</label><div><?= htmlspecialchars($viewRow['duration'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Mode</label><div><?= htmlspecialchars($viewRow['mode'] ?: '—') ?></div></div>
    </div>
    <div class="msg-body mt-2"><?= nl2br(htmlspecialchars($viewRow['description'] ?? '')) ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Freelance Project' : 'Create Freelance Project' ?></div>
  <div class="form-card-body">
    <form method="POST" enctype="multipart/form-data" id="freelanceProjectAdminForm" action="<?= adminUrl('admin/freelance-projects.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?><input type="hidden" name="freelance_project_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Project Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editRow['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Project Category</label>
          <input type="text" name="category" class="form-control" placeholder="e.g. Web Development" value="<?= htmlspecialchars($editRow['category'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Short Description</label>
          <input type="text" name="short_desc" class="form-control" maxlength="255" value="<?= htmlspecialchars($editRow['short_desc'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Project Description *</label>
          <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Skills Required</label>
          <input type="text" name="skills" class="form-control" placeholder="PHP, React, MySQL" value="<?= htmlspecialchars($editRow['skills'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Budget</label>
          <input type="text" name="budget_label" class="form-control" placeholder="₹25,000 – ₹60,000" value="<?= htmlspecialchars($editRow['budget_label'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration</label>
          <input type="text" name="duration" class="form-control" placeholder="4–6 weeks" value="<?= htmlspecialchars($editRow['duration'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Project Thumbnail / Image</label>
          <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">JPG, PNG, WEBP, or GIF. Max <?= (int) (MAX_UPLOAD_SIZE / 1024 / 1024) ?>MB.</p>
          <?php if ($editThumb !== ''): ?>
          <div class="mt-2">
            <img src="<?= htmlspecialchars($editThumb) ?>" alt="" style="max-width:180px;border-radius:8px;border:1px solid var(--cyber-border)">
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="clear_thumbnail" value="1" id="clearFreelanceThumb">
              <label class="form-check-label" for="clearFreelanceThumb">Remove current image</label>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <div class="col-md-4">
          <label class="form-label">Project Status</label>
          <select name="status" class="form-select">
            <?php foreach (catalogFreelanceProjectStatusOptions() as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $editStatus === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Only <strong>Active</strong> projects appear on the website.</p>
        </div>
        <div class="col-md-4">
          <label class="form-label">Work Mode</label>
          <input type="text" name="mode" class="form-control" placeholder="Remote / Hybrid" value="<?= htmlspecialchars($editRow['mode'] ?? 'Remote') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Client Name (optional)</label>
          <input type="text" name="client_name" class="form-control" value="<?= htmlspecialchars($editRow['client_name'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Apply page route</label>
          <input type="text" name="apply_route" class="form-control" value="<?= htmlspecialchars($editRow['apply_route'] ?? 'apply-freelance.php') ?>">
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="show_on_homepage" value="1" id="freelanceShowOnHomepage" <?= !empty($editRow['show_on_homepage']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="freelanceShowOnHomepage">Show on homepage / landing page</label>
          </div>
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Project type: <strong>Freelancer Project</strong>. Appears on the Freelancer Projects page when active. Homepage display is optional.</p>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit" id="freelanceProjectSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update Project' : 'Add Project' ?></button>
          <a href="<?= adminUrl('admin/freelance-projects.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<div class="section-card">
  <div class="section-card-header">All Freelancer Projects (<?= adminCatalogUsageLabel('freelance_projects') ?>)</div>
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
            $displayStatus = freelanceAdminNormalizeStatus((string) ($row['status'] ?? 'Active'));
            $statusClass = strtolower($displayStatus) === 'active' ? 'badge-free' : 'badge-paid';
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
            <td><span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars($displayStatus) ?></span></td>
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
