<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

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
        adminFlashRedirect('admin/webinars.php', 'error', 'Invalid request. Please try again.');
    }

    $postId = (int) ($_POST['webinar_id'] ?? 0);

    $data = [
        'title'            => sanitize($_POST['title']),
        'slug'             => slugify($_POST['title']),
        'description'      => sanitize($_POST['description']),
        'short_desc'       => sanitize($_POST['short_desc']),
        'instructor'       => sanitize($_POST['instructor']),
        'category'         => sanitize($_POST['category']),
        'tags'             => sanitize($_POST['tags'] ?? ''),
        'scheduled_at'     => $_POST['scheduled_at'],
        'duration_mins'    => (int) $_POST['duration_mins'],
        'max_seats'        => (int) $_POST['max_seats'],
        'fee'              => (float) $_POST['fee'],
        'is_free'          => isset($_POST['is_free']) ? 1 : 0,
        'meeting_link'     => sanitize($_POST['meeting_link'] ?? ''),
        'meeting_platform' => sanitize($_POST['meeting_platform']),
        'status'           => sanitize($_POST['status']),
    ];

    if (empty($data['title']) || empty($data['scheduled_at'])) {
        $back = $postId > 0 ? 'admin/webinars.php?edit=' . $postId : 'admin/webinars.php?action=add';
        adminFlashRedirect($back, 'error', 'Title and schedule date are required.');
    }

    $nonceScope = $postId > 0 ? 'webinar_edit_' . $postId : 'webinar_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/webinars.php', 'error', 'This webinar was already saved. Refresh the page to add another.');
    }

    if ($postId > 0) {
        db()->execute(
            'UPDATE webinars SET title=?,slug=?,description=?,short_desc=?,instructor=?,category=?,tags=?,scheduled_at=?,duration_mins=?,max_seats=?,fee=?,is_free=?,meeting_link=?,meeting_platform=?,status=?,updated_at=NOW() WHERE id=?',
            array_merge(array_values($data), [$postId])
        );
        adminFlashRedirect('admin/webinars.php', 'success', 'Webinar updated successfully!');
    }

    adminCatalogRejectCreateIfAtLimit('webinars', 'admin/webinars.php');

    $slug = $data['slug'];
    if (db()->fetchOne('SELECT id FROM webinars WHERE slug = ?', [$slug])) {
        $data['slug'] = $slug . '-' . time();
    }
    db()->execute(
        'INSERT INTO webinars (title,slug,description,short_desc,instructor,category,tags,scheduled_at,duration_mins,max_seats,fee,is_free,meeting_link,meeting_platform,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array_values($data)
    );
    adminFlashRedirect('admin/webinars.php', 'success', 'Webinar created successfully!');
}

$editWebinar = null;
if ($editId > 0) {
    $editWebinar = db()->fetchOne('SELECT * FROM webinars WHERE id = ?', [$editId]);
    if (!$editWebinar) {
        $msg = 'Webinar not found.';
        $msgType = 'error';
        $editId = 0;
    } else {
        $action = 'add';
    }
}

adminCatalogRejectAddActionIfAtLimit('webinars', $action, $editId, 'admin/webinars.php');

$formNonce = adminIssueFormNonce($editId > 0 ? 'webinar_edit_' . $editId : 'webinar_create');

$webinars = db()->fetchAll(
    'SELECT w.*,
            (SELECT COUNT(*) FROM webinar_registrations wr WHERE wr.webinar_id = w.id) AS reg_count
     FROM webinars w
     WHERE ' . adminSqlActive('w') . '
     ORDER BY w.scheduled_at DESC'
);

$pageTitle = $action === 'add' ? ($editId ? 'Edit Webinar' : 'Add Webinar') : 'Webinars';
renderAdminPageStart($pageTitle, 'webinars', 'fa-video');
?>

<?php if ($action !== 'add'): ?>
<?php adminCatalogRenderListToolbar('webinars', 'admin/webinars.php?action=add', 'Add Webinar'); ?>
<?php endif; ?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>" style="color:var(--cyber-accent)"></i><?= $editId ? 'Edit Webinar' : 'Create New Webinar' ?></div>
  <div class="form-card-body">
    <form method="POST" id="webinarAdminForm" action="<?= adminUrl('admin/webinars.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?>
      <input type="hidden" name="webinar_id" value="<?= $editId ?>">
      <?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Webinar Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Ethical Hacking Masterclass" value="<?= htmlspecialchars($editWebinar['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" placeholder="e.g. Cybersecurity" value="<?= htmlspecialchars($editWebinar['category'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Short Description</label>
          <input type="text" name="short_desc" class="form-control" placeholder="One-line summary shown in listing" value="<?= htmlspecialchars($editWebinar['short_desc'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Full Description</label>
          <textarea name="description" class="form-control" placeholder="Detailed webinar description, agenda, takeaways..."><?= htmlspecialchars($editWebinar['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Instructor Name</label>
          <input type="text" name="instructor" class="form-control" placeholder="Speaker full name" value="<?= htmlspecialchars($editWebinar['instructor'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Scheduled Date & Time *</label>
          <input type="datetime-local" name="scheduled_at" class="form-control" required value="<?= $editWebinar ? date('Y-m-d\TH:i', strtotime($editWebinar['scheduled_at'])) : '' ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Duration (minutes)</label>
          <input type="number" name="duration_mins" class="form-control" min="15" max="480" value="<?= htmlspecialchars((string) ($editWebinar['duration_mins'] ?? 60)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Max Seats</label>
          <input type="number" name="max_seats" class="form-control" min="1" value="<?= htmlspecialchars((string) ($editWebinar['max_seats'] ?? 100)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Fee (₹)</label>
          <input type="number" name="fee" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editWebinar['fee'] ?? 0)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Meeting Platform</label>
          <select name="meeting_platform" class="form-select">
            <?php foreach (['zoom','google_meet','teams','other'] as $p): ?>
            <option value="<?= $p ?>" <?= ($editWebinar['meeting_platform'] ?? 'zoom') === $p ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $p)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['upcoming','live','completed','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editWebinar['status'] ?? 'upcoming') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Shown on the website when status is Upcoming, Live, or Completed (not Cancelled) and the item is not blocked.</p>
        </div>
        <div class="col-12">
          <label class="form-label">Meeting Link</label>
          <input type="url" name="meeting_link" class="form-control" placeholder="https://zoom.us/j/..." value="<?= htmlspecialchars($editWebinar['meeting_link'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Tags (comma separated)</label>
          <input type="text" name="tags" class="form-control" placeholder="ethical hacking, nmap, penetration testing" value="<?= htmlspecialchars($editWebinar['tags'] ?? '') ?>">
        </div>
        <div class="col-12">
          <div style="display:flex;align-items:center;gap:0.5rem">
            <input type="checkbox" name="is_free" id="is_free" style="accent-color:var(--cyber-accent)" <?= ($editWebinar['is_free'] ?? 0) ? 'checked' : '' ?>>
            <label for="is_free" style="font-size:0.88rem;color:var(--cyber-text);cursor:pointer">This is a FREE webinar (no payment required)</label>
          </div>
        </div>
        <div class="col-12 d-flex gap-2 pt-1">
          <button type="submit" class="btn-submit" id="webinarSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update Webinar' : 'Create Webinar' ?></button>
          <a href="<?= adminUrl('admin/webinars.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else: ?>

<div class="form-card">
  <div class="form-card-header"><i class="fas fa-list" style="color:var(--cyber-accent)"></i>All Webinars (<?= adminCatalogUsageLabel('webinars') ?>)</div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>#</th><th>Title</th><th>Instructor</th><th>Scheduled</th><th>Seats</th><th>Fee</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php foreach ($webinars as $i => $w):
        $blocked = adminRecordIsBlocked($w);
      ?>
      <tr<?= renderAdminRecordRowAttrs('webinar', (int) $w['id'], $blocked) ?>>
        <td style="color:var(--cyber-muted);font-size:0.78rem"><?= $i + 1 ?></td>
        <td>
          <div style="font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($w['title']) ?></div>
          <div style="font-size:0.72rem;color:var(--cyber-muted)"><?= htmlspecialchars($w['category']) ?></div>
        </td>
        <td style="font-size:0.82rem"><?= htmlspecialchars($w['instructor'] ?? '—') ?></td>
        <td style="font-size:0.8rem;white-space:nowrap">
          <?= date('d M Y', strtotime($w['scheduled_at'])) ?><br>
          <span style="color:var(--cyber-muted)"><?= date('h:i A', strtotime($w['scheduled_at'])) ?></span>
        </td>
        <td>
          <span style="color:var(--cyber-accent)"><?= (int) $w['reg_count'] ?></span>
          <span style="color:var(--cyber-muted);font-size:0.78rem">/ <?= (int) $w['max_seats'] ?></span>
        </td>
        <td style="font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:600">
          <?php
          require_once __DIR__ . '/../includes/webinar-register-helpers.php';
          if ($w['is_free']): ?><span style="color:var(--cyber-green)">FREE</span><?php else: ?><span><?= htmlspecialchars(webinarPaidCompactLabel()) ?></span><?php endif; ?>
        </td>
        <td><span class="badge-status badge-<?= htmlspecialchars($w['status']) ?>"><?= strtoupper($w['status']) ?></span></td>
        <td style="white-space:nowrap">
          <a href="<?= adminUrl('admin/webinars.php?edit=' . (int) $w['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i>Edit</a>
          <?php renderAdminRecordActions('webinar', (int) $w['id'], $blocked); ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($webinars)): ?>
      <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--cyber-muted)">No webinars found. <a href="<?= adminUrl('admin/webinars.php?action=add') ?>" style="color:var(--cyber-accent)">Add your first one →</a></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('webinarAdminForm', 'webinarSubmitBtn'); ?>
<script>
document.querySelector('[name="fee"]')?.addEventListener('input', function () {
  var cb = document.getElementById('is_free');
  if (cb) cb.checked = parseFloat(this.value) === 0;
});
</script>
<?php renderAdminPageEnd(); ?>
