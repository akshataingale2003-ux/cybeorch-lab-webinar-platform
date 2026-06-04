<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/program-pricing.php';
requireAdminLogin();

ensureBootcampPricingSchema();

$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['edit'] ?? 0);
$msg = '';
$msgType = 'success';

$flash = getFlash();
if ($flash) {
    $msg = (string) $flash['message'];
    $msgType = ($flash['type'] ?? '') === 'error' ? 'error' : 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/bootcamps.php', 'error', 'Invalid request. Please try again.');
    }

    $postId = (int) ($_POST['bootcamp_id'] ?? 0);
    $data = [
        'title'          => sanitize($_POST['title'] ?? ''),
        'slug'           => slugify($_POST['title'] ?? ''),
        'description'    => sanitize($_POST['description'] ?? ''),
        'short_desc'     => sanitize($_POST['short_desc'] ?? ''),
        'instructor'     => sanitize($_POST['instructor'] ?? ''),
        'category'       => sanitize($_POST['category'] ?? ''),
        'start_date'     => $_POST['start_date'] ?? '',
        'end_date'       => $_POST['end_date'] ?? '',
        'duration_weeks' => (int) ($_POST['duration_weeks'] ?? 4),
        'total_seats'    => (int) ($_POST['total_seats'] ?? 30),
        'original_fee'   => (float) ($_POST['original_fee'] ?? 0),
        'discounted_fee' => (float) ($_POST['discounted_fee'] ?? 0),
        'fee_usd'        => (float) ($_POST['fee_usd'] ?? 0),
        'duration_label' => sanitize($_POST['duration_label'] ?? ''),
        'certificate'    => isset($_POST['certificate']) ? 1 : 0,
        'status'         => sanitize($_POST['status'] ?? 'open'),
    ];

    if (strlen($data['title']) < 3 || empty($data['start_date']) || empty($data['end_date'])) {
        $back = $postId > 0 ? 'admin/bootcamps.php?edit=' . $postId : 'admin/bootcamps.php?action=add';
        adminFlashRedirect($back, 'error', 'Title, start date, and end date are required.');
    }

    $nonceScope = $postId > 0 ? 'bootcamp_edit_' . $postId : 'bootcamp_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/bootcamps.php', 'error', 'This bootcamp was already saved. Refresh the page to add another.');
    }

    if ($postId > 0) {
        db()->execute(
            'UPDATE bootcamps SET title=?,slug=?,description=?,short_desc=?,instructor=?,category=?,start_date=?,end_date=?,duration_weeks=?,duration_label=?,total_seats=?,original_fee=?,discounted_fee=?,fee_usd=?,certificate=?,status=?,updated_at=NOW() WHERE id=?',
            array_merge(array_values($data), [$postId])
        );
        adminFlashRedirect('admin/bootcamps.php', 'success', 'Bootcamp updated successfully!');
    }

    adminCatalogRejectCreateIfAtLimit('bootcamps', 'admin/bootcamps.php');

    $slug = $data['slug'];
    if (db()->fetchOne('SELECT id FROM bootcamps WHERE slug = ?', [$slug])) {
        $data['slug'] = $slug . '-' . time();
    }
    db()->execute(
        'INSERT INTO bootcamps (title,slug,description,short_desc,instructor,category,start_date,end_date,duration_weeks,duration_label,total_seats,original_fee,discounted_fee,fee_usd,certificate,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array_values($data)
    );
    adminFlashRedirect('admin/bootcamps.php', 'success', 'Bootcamp created successfully!');
}

$editBootcamp = null;
if ($editId > 0) {
    $editBootcamp = db()->fetchOne('SELECT * FROM bootcamps WHERE id = ?', [$editId]);
    if (!$editBootcamp) {
        $msg = 'Bootcamp not found.';
        $msgType = 'error';
        $editId = 0;
    } else {
        $action = 'add';
    }
}

adminCatalogRejectAddActionIfAtLimit('bootcamps', $action, $editId, 'admin/bootcamps.php');

$formNonce = adminIssueFormNonce($editId > 0 ? 'bootcamp_edit_' . $editId : 'bootcamp_create');

try {
    $bootcamps = db()->fetchAll(
        'SELECT b.*,
                (SELECT COUNT(*) FROM bootcamp_enrollments be WHERE be.bootcamp_id = b.id) AS enroll_count
         FROM bootcamps b
         WHERE ' . adminSqlActive('b') . '
         ORDER BY b.start_date DESC'
    );
} catch (Throwable $e) {
    $bootcamps = [];
    $msg = 'Could not load bootcamps: ' . $e->getMessage();
    $msgType = 'error';
}

$pageTitle = $action === 'add' ? ($editId ? 'Edit Bootcamp' : 'Add Bootcamp') : 'Bootcamps';
renderAdminPageStart($pageTitle, 'bootcamps', 'fa-graduation-cap');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add'): ?>
<?php adminCatalogRenderListToolbar('bootcamps', 'admin/bootcamps.php?action=add', 'Add Bootcamp'); ?>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Bootcamp' : 'Create Bootcamp' ?></div>
  <div class="form-card-body">
    <form method="POST" id="bootcampAdminForm" action="<?= adminUrl('admin/bootcamps.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?><input type="hidden" name="bootcamp_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editBootcamp['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($editBootcamp['category'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Short Description</label>
          <input type="text" name="short_desc" class="form-control" value="<?= htmlspecialchars($editBootcamp['short_desc'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Full Description</label>
          <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editBootcamp['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Instructor</label>
          <input type="text" name="instructor" class="form-control" value="<?= htmlspecialchars($editBootcamp['instructor'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Start Date *</label>
          <input type="date" name="start_date" class="form-control" required value="<?= htmlspecialchars($editBootcamp['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">End Date *</label>
          <input type="date" name="end_date" class="form-control" required value="<?= htmlspecialchars($editBootcamp['end_date'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration (weeks)</label>
          <input type="number" name="duration_weeks" class="form-control" min="1" value="<?= (int) ($editBootcamp['duration_weeks'] ?? 4) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Total Seats</label>
          <input type="number" name="total_seats" class="form-control" min="1" value="<?= (int) ($editBootcamp['total_seats'] ?? 30) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Original Fee (₹)</label>
          <input type="number" name="original_fee" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editBootcamp['original_fee'] ?? 0)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Discounted Fee (₹)</label>
          <input type="number" name="discounted_fee" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editBootcamp['discounted_fee'] ?? 0)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Fee (USD)</label>
          <input type="number" name="fee_usd" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editBootcamp['fee_usd'] ?? 0)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration label</label>
          <input type="text" name="duration_label" class="form-control" placeholder="e.g. 30 Days" value="<?= htmlspecialchars((string) ($editBootcamp['duration_label'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['open', 'closed', 'ongoing', 'completed'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editBootcamp['status'] ?? 'open') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Shown on the website when status is Open or Ongoing (not Closed/Completed) and the item is not blocked.</p>
        </div>
        <div class="col-md-8 d-flex align-items-end">
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
            <input type="checkbox" name="certificate" value="1" <?= ($editBootcamp['certificate'] ?? 1) ? 'checked' : '' ?>>
            Certificate of completion included
          </label>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit" id="bootcampSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update' : 'Create' ?></button>
          <a href="<?= adminUrl('admin/bootcamps.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else: ?>

<div class="section-card">
  <div class="section-card-header">All bootcamps (<?= adminCatalogUsageLabel('bootcamps') ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Title</th><th>Instructor</th><th>Dates</th><th>Seats</th><th>Fee</th><th>Enrolled</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$bootcamps): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No bootcamps. <?php if (adminCatalogCanCreate('bootcamps')): ?><a href="<?= adminUrl('admin/bootcamps.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a><?php endif; ?></td></tr>
          <?php else: foreach ($bootcamps as $b):
            $b = bootcampRowWithProgramDefaults($b);
            $blocked = adminRecordIsBlocked($b);
            $feeDecimals = bootcampPriceDecimals($b);
          ?>
          <tr<?= renderAdminRecordRowAttrs('bootcamp', (int) $b['id'], $blocked) ?>>
            <td>
              <strong><?= htmlspecialchars($b['title']) ?></strong><br>
              <a href="<?= url('bootcamps.php?slug=' . urlencode($b['slug'])) ?>" target="_blank" rel="noopener" class="btn-sm-link" style="font-size:0.75rem">View on site →</a>
            </td>
            <td><?= htmlspecialchars($b['instructor'] ?: '—') ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($b['start_date'])) ?> – <?= date('d M Y', strtotime($b['end_date'])) ?></td>
            <td><?= (int) ($b['enrolled_seats'] ?? 0) ?> / <?= (int) $b['total_seats'] ?></td>
            <td style="white-space:nowrap">
              <?php if (bootcampShowsDualCurrency($b)): ?>
              <?= htmlspecialchars(bootcampDualPriceLines($b)['inr']) ?><br>
              <span style="font-size:.8rem;color:var(--cyber-muted)">(<?= htmlspecialchars(bootcampDualPriceLines($b)['usd']) ?>)</span>
              <?php else: ?>
              ₹<?= number_format((float) $b['discounted_fee'], $feeDecimals) ?>
              <?php endif; ?>
            </td>
            <td><?= (int) $b['enroll_count'] ?></td>
            <td><span class="badge-status badge-<?= $b['status'] === 'open' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($b['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/bootcamps.php?edit=' . (int) $b['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <?php renderAdminRecordActions('bootcamp', (int) $b['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('bootcampAdminForm', 'bootcampSubmitBtn'); ?>
<?php renderAdminPageEnd(); ?>
