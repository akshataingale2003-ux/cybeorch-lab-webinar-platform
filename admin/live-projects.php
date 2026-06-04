<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/catalog-content.php';
require_once __DIR__ . '/../includes/projects-data.php';
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
        adminFlashRedirect('admin/live-projects.php', 'error', 'Invalid request. Please try again.');
    }

    $postId = (int) ($_POST['live_project_id'] ?? 0);
    $nonceScope = $postId > 0 ? 'live_project_edit_' . $postId : 'live_project_create';

    $featuresText = (string) ($_POST['features_text'] ?? '');
    $featuresJson = catalogEncodeLiveProjectFeatures(catalogParseFeaturesTextarea($featuresText));

    $imagePath = trim((string) ($_POST['image_path'] ?? ''));
    $imagePath = html_entity_decode($imagePath, ENT_QUOTES, 'UTF-8');
    $imagePath = str_replace('\\', '/', $imagePath);
    // Allow pasting a full Windows path; convert to relative web path.
    if ($imagePath !== '' && preg_match('/^[a-zA-Z]:\\//', $imagePath)) {
        $pos = stripos($imagePath, '/assets/images/');
        if ($pos !== false) {
            $imagePath = ltrim(substr($imagePath, $pos + 1), '/'); // "assets/images/..."
        }
    }
    if ($imagePath !== '' && !str_contains($imagePath, '/')) {
        // Allow pasting only a filename; normalize to assets folder
        $imagePath = 'assets/images/' . $imagePath;
    }
    if (!empty($_POST['clear_image_path'])) {
        $imagePath = '';
    }

    $data = [
        'title'         => trim((string) ($_POST['title'] ?? '')),
        'slug'          => slugify($_POST['title'] ?? ''),
        'short_desc'    => trim((string) ($_POST['short_desc'] ?? '')),
        'description'   => trim((string) ($_POST['description'] ?? '')),
        'category'      => trim((string) ($_POST['category'] ?? '')),
        'icon_class'    => trim((string) ($_POST['icon_class'] ?? 'fa-code-branch')),
        'card_theme'    => trim((string) ($_POST['card_theme'] ?? 'theme-default')),
        'image_path'    => $imagePath,
        'features_json' => $featuresJson,
        'stack'         => trim((string) ($_POST['stack'] ?? '')),
        'duration'      => trim((string) ($_POST['duration'] ?? '')),
        'team_size'     => trim((string) ($_POST['team_size'] ?? '')),
        'status'        => trim((string) ($_POST['status'] ?? 'Open for Collaboration')),
        'sort_order'    => (int) ($_POST['sort_order'] ?? 0),
    ];

    if (strlen($data['title']) < 3) {
        $back = $postId > 0 ? 'admin/live-projects.php?edit=' . $postId : 'admin/live-projects.php?action=add';
        adminFlashRedirect($back, 'error', 'Title is required (min 3 characters).');
    }

    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/live-projects.php', 'error', 'This project was already saved. Refresh to add another.');
    }

    if ($postId > 0) {
        db()->execute(
            'UPDATE live_projects SET title=?,slug=?,short_desc=?,description=?,category=?,icon_class=?,card_theme=?,image_path=?,features_json=?,stack=?,duration=?,team_size=?,status=?,sort_order=?,updated_at=NOW() WHERE id=?',
            array_merge(array_values($data), [$postId])
        );
        adminFlashRedirect('admin/live-projects.php', 'success', 'Live project updated successfully!');
    }

    adminCatalogRejectCreateIfAtLimit('live_projects', 'admin/live-projects.php');

    $slug = $data['slug'];
    if (db()->fetchOne('SELECT id FROM live_projects WHERE slug = ?', [$slug])) {
        $data['slug'] = $slug . '-' . time();
    }
    db()->execute(
        'INSERT INTO live_projects (title,slug,short_desc,description,category,icon_class,card_theme,image_path,features_json,stack,duration,team_size,status,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array_values($data)
    );
    adminFlashRedirect('admin/live-projects.php', 'success', 'Live project created successfully!');
}

$editRow = null;
if ($editId > 0) {
    $editRow = catalogGetLiveProjectById($editId);
    if (!$editRow) {
        adminFlashRedirect('admin/live-projects.php', 'error', 'Live project not found.');
    }
    $editRow['features'] = catalogDecodeLiveProjectFeatures((string) ($editRow['features_json'] ?? ''));
    $action = 'add';
}

$viewRow = null;
if ($viewId > 0 && $action !== 'add') {
    $viewRow = catalogGetLiveProjectById($viewId);
    if (!$viewRow) {
        adminFlashRedirect('admin/live-projects.php', 'error', 'Live project not found.');
    }
    $viewRow['features'] = catalogDecodeLiveProjectFeatures((string) ($viewRow['features_json'] ?? ''));
}

adminCatalogRejectAddActionIfAtLimit('live_projects', $action, $editId, 'admin/live-projects.php');

$formNonce = adminIssueFormNonce($editId > 0 ? 'live_project_edit_' . $editId : 'live_project_create');

try {
    $items = catalogGetLiveProjects(true);
} catch (Throwable $e) {
    $items = [];
    $msg = 'Could not load live projects: ' . $e->getMessage();
    $msgType = 'error';
}

$themeOptions = ['theme-default', 'theme-cyber', 'theme-java', 'theme-mern', 'theme-data', 'theme-ai', 'theme-fintech'];

$pageTitle = $action === 'add' ? ($editId ? 'Edit Live Project' : 'Add Live Project') : 'Live Projects';
renderAdminPageStart($pageTitle, 'live-projects', 'fa-code-branch');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<?php adminCatalogRenderListToolbar('live_projects', 'admin/live-projects.php?action=add', 'Add Live Project'); ?>
<?php endif; ?>

<?php if ($viewRow): ?>
<div class="section-card mb-3">
  <div class="section-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View Live Project</span>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= adminUrl('admin/live-projects.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i> Edit</a>
      <a href="<?= url('live-projects.php?slug=' . urlencode((string) $viewRow['slug'])) ?>" target="_blank" rel="noopener" class="btn-sm-link">View on site →</a>
      <a href="<?= adminUrl('admin/live-projects.php') ?>" class="btn-cancel">Back</a>
    </div>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Title</label><div><?= htmlspecialchars($viewRow['title']) ?></div></div>
      <div class="detail-item"><label>Slug</label><div><?= htmlspecialchars($viewRow['slug']) ?></div></div>
      <div class="detail-item"><label>Icon</label><div><i class="<?= htmlspecialchars(liveProjectIconClasses((string) ($viewRow['icon_class'] ?? ''))) ?>"></i> <?= htmlspecialchars($viewRow['icon_class'] ?? '') ?></div></div>
      <div class="detail-item"><label>Theme</label><div><?= htmlspecialchars($viewRow['card_theme'] ?? '') ?></div></div>
      <div class="detail-item"><label>Status</label><div><?= htmlspecialchars($viewRow['status'] ?? '') ?></div></div>
      <div class="detail-item"><label>Stack</label><div><?= htmlspecialchars($viewRow['stack'] ?? '—') ?></div></div>
    </div>
    <?php if (!empty($viewRow['features'])): ?>
    <h4 class="mt-3 mb-2" style="font-size:.95rem">Modules / Features</h4>
    <ul class="feature-list">
      <?php foreach ($viewRow['features'] as $f): ?>
      <li><i class="<?= htmlspecialchars(liveProjectIconClasses((string) ($f['icon'] ?? 'fa-check'))) ?>"></i><?= htmlspecialchars($f['label'] ?? '') ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <div class="msg-body mt-2"><?= nl2br(htmlspecialchars($viewRow['description'] ?? '')) ?></div>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Live Project' : 'Create Live Project' ?></div>
  <div class="form-card-body">
    <form method="POST" id="liveProjectAdminForm" action="<?= adminUrl('admin/live-projects.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?><input type="hidden" name="live_project_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Project Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editRow['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category Label</label>
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
        <div class="col-md-4">
          <label class="form-label">Icon class</label>
          <input type="text" name="icon_class" class="form-control" placeholder="fa-shield-halved or fa-brands fa-java" value="<?= htmlspecialchars($editRow['icon_class'] ?? 'fa-code-branch') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Card theme</label>
          <select name="card_theme" class="form-select">
            <?php foreach ($themeOptions as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= ($editRow['card_theme'] ?? 'theme-default') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Image path (optional)</label>
          <input type="text" name="image_path" class="form-control" placeholder="assets/images/..." value="<?= htmlspecialchars($editRow['image_path'] ?? '') ?>">
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" value="1" id="clearImagePath" name="clear_image_path">
            <label class="form-check-label" for="clearImagePath">Clear / delete saved image path</label>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Tech stack</label>
          <input type="text" name="stack" class="form-control" value="<?= htmlspecialchars($editRow['stack'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Duration</label>
          <input type="text" name="duration" class="form-control" value="<?= htmlspecialchars($editRow['duration'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Team size</label>
          <input type="text" name="team_size" class="form-control" value="<?= htmlspecialchars($editRow['team_size'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (catalogLiveProjectStatusOptions() as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= ($editRow['status'] ?? 'Open for Collaboration') === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Sort order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Modules / features (one per line)</label>
          <textarea name="features_text" class="form-control" rows="8" placeholder="fa-credit-card|Payment Gateway Integration&#10;Blockchain Technology"><?= htmlspecialchars(catalogFormatFeaturesForTextarea($editRow['features'] ?? [])) ?></textarea>
          <p style="font-size:.75rem;color:var(--cyber-muted);margin-top:.35rem">
            Optional icon prefix: <code>fa-wallet|Wallet Creation</code>.
            For <strong>FinTech sub-modules</strong> (blockchain, wallet, NxL credits, etc.), edit the <strong>FinTech</strong> project and list them here — do not create separate top-level live projects unless each should be its own category card.
          </p>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit" id="liveProjectSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update' : 'Create' ?></button>
          <a href="<?= adminUrl('admin/live-projects.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php elseif (!$viewId): ?>

<div class="section-card">
  <div class="section-card-header">All live projects (<?= adminCatalogUsageLabel('live_projects') ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Title</th><th>Category</th><th>Stack</th><th>Features</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$items): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No live projects. <?php if (adminCatalogCanCreate('live_projects')): ?><a href="<?= adminUrl('admin/live-projects.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a><?php endif; ?></td></tr>
          <?php else: foreach ($items as $i => $row):
            $blocked = adminRecordIsBlocked($row);
            $featCount = count($row['features'] ?? []);
          ?>
          <tr<?= renderAdminRecordRowAttrs('live_project', (int) $row['id'], $blocked) ?>>
            <td style="color:var(--cyber-muted);font-size:.78rem"><?= $i + 1 ?></td>
            <td>
              <strong><?= htmlspecialchars($row['title']) ?></strong><br>
              <a href="<?= url('live-projects.php?slug=' . urlencode((string) $row['slug'])) ?>" target="_blank" rel="noopener" class="btn-sm-link" style="font-size:.75rem">View on site →</a>
            </td>
            <td><?= htmlspecialchars($row['category'] ?: '—') ?></td>
            <td style="font-size:.82rem"><?= htmlspecialchars($row['stack'] ?: '—') ?></td>
            <td><?= $featCount > 0 ? (int) $featCount . ' modules' : '—' ?></td>
            <td><span class="badge-status badge-paid"><?= htmlspecialchars($row['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/live-projects.php?view=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="View"><i class="fas fa-eye"></i></a>
              <a href="<?= adminUrl('admin/live-projects.php?edit=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <?php renderAdminRecordActions('live_project', (int) $row['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('liveProjectAdminForm', 'liveProjectSubmitBtn'); ?>
<?php renderAdminPageEnd(); ?>
