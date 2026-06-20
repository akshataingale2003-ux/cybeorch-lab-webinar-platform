<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/webinar-admin.php';
require_once __DIR__ . '/../includes/webinar-closing-soon.php';
requireAdminLogin();

ensureWebinarClosingSoonSchema();

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
    $back = $postId > 0 ? 'admin/webinars.php?edit=' . $postId : 'admin/webinars.php?action=add';

    $nonceScope = $postId > 0 ? 'webinar_edit_' . $postId : 'webinar_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/webinars.php', 'error', 'This webinar was already saved. Refresh the page to add another.');
    }

    $existing = null;
    if ($postId > 0) {
        $existing = db()->fetchOne('SELECT * FROM webinars WHERE id = ?', [$postId]);
        if (!$existing) {
            adminFlashRedirect('admin/webinars.php', 'error', 'Webinar not found.');
        }
    }

    $built = webinarAdminBuildPayload($_POST, $_FILES, $postId, $existing);
    if (!$built['ok']) {
        adminFlashRedirect($back, 'error', $built['error']);
    }
    $data = $built['data'];

    try {
        if ($postId > 0) {
            webinarAdminUpdate($postId, $data);
            adminFlashRedirect('admin/webinars.php', 'success', 'Webinar updated successfully!');
        }

        adminCatalogRejectCreateIfAtLimit('webinars', 'admin/webinars.php');

        webinarAdminInsert($data);
        adminFlashRedirect('admin/webinars.php', 'success', 'Webinar created successfully!');
    } catch (Throwable $e) {
        error_log('Webinar save failed: ' . $e->getMessage());
        adminFlashRedirect($back, 'error', 'Could not save the webinar. Please check your entries and try again.');
    }
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
require_once __DIR__ . '/../includes/webinar-register-helpers.php';
require_once __DIR__ . '/../includes/training-public.php';
renderWebinarPricingBadgeStyles();
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
    <form method="POST" id="webinarAdminForm" enctype="multipart/form-data" action="<?= adminUrl('admin/webinars.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?>
      <input type="hidden" name="webinar_id" value="<?= $editId ?>">
      <?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Webinar Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Ethical Hacking Masterclass" value="<?= escHtml((string) ($editWebinar['title'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" placeholder="e.g. Cybersecurity" value="<?= escHtml((string) ($editWebinar['category'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Short Description</label>
          <input type="text" name="short_desc" class="form-control" placeholder="One-line summary shown in listing" value="<?= escHtml((string) ($editWebinar['short_desc'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Full Description</label>
          <textarea name="description" class="form-control" placeholder="Detailed webinar description, agenda, takeaways..."><?= escHtml((string) ($editWebinar['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Thumbnail Image</label>
          <?php if (!empty($editWebinar['thumbnail'])): ?>
          <?php $thumbPreview = webinarThumbPathForWebinar($editWebinar); ?>
          <div style="margin-bottom:.65rem">
            <img src="<?= catalogImageUrl($thumbPreview) ?>" alt="" style="max-width:220px;max-height:140px;border-radius:8px;border:1px solid var(--cyber-border)">
          </div>
          <?php endif; ?>
          <input type="file" name="thumbnail" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Optional. JPG, PNG, WEBP, or GIF up to 5MB. If omitted, a default image is chosen from the webinar title.</p>
        </div>
        <div class="col-md-4">
          <label class="form-label">Instructor Name</label>
          <input type="text" name="instructor" class="form-control" placeholder="Speaker full name" value="<?= escHtml((string) ($editWebinar['instructor'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Scheduled Date & Time *</label>
          <input type="datetime-local" name="scheduled_at" class="form-control" required value="<?= $editWebinar ? date('Y-m-d\TH:i', strtotime($editWebinar['scheduled_at'])) : '' ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Registration Closes At</label>
          <input type="datetime-local" name="registration_closes_at" class="form-control" value="<?= !empty($editWebinar['registration_closes_at']) ? date('Y-m-d\TH:i', strtotime((string) $editWebinar['registration_closes_at'])) : '' ?>">
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Optional. Leave blank to use the scheduled start time. Popup uses this deadline.</p>
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
          <input type="number" name="fee" id="webinarFeeInr" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editWebinar['fee'] ?? 0)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Equivalent Fee (USD)</label>
          <div id="webinarFeeUsdPreview" class="form-control" style="background:rgba(10,22,40,0.55);color:var(--cyber-accent);font-weight:600;height:auto;min-height:calc(1.5em + .75rem + 2px)">
            <?= htmlspecialchars(cybeorchFormatUsdFee(cybeorchConvertInrToUsd((float) ($editWebinar['fee'] ?? 0)))) ?>
          </div>
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">Auto-calculated from INR using <?= htmlspecialchars((string) cybeorchInrPerUsd()) ?> INR/USD.</p>
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
          <?php $formStatusDefault = $editId > 0 ? (string) ($editWebinar['status'] ?? 'live') : 'live'; ?>
          <select name="status" class="form-select" required>
            <?php foreach (['live','upcoming','completed','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $formStatusDefault === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">New webinars default to Live (Live Webinars page). Choose Upcoming only for the homepage Upcoming Webinars section.</p>
        </div>
        <div class="col-12">
          <label class="form-label">Meeting Link</label>
          <input type="url" name="meeting_link" class="form-control" placeholder="https://zoom.us/j/..." value="<?= escHtml((string) ($editWebinar['meeting_link'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Tags (comma separated)</label>
          <input type="text" name="tags" class="form-control" placeholder="ethical hacking, nmap, penetration testing" value="<?= escHtml((string) ($editWebinar['tags'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <div style="display:flex;align-items:center;gap:0.5rem">
            <input type="checkbox" name="is_free" id="is_free" style="accent-color:var(--cyber-accent)" <?= ($editWebinar['is_free'] ?? 0) ? 'checked' : '' ?>>
            <label for="is_free" style="font-size:0.88rem;color:var(--cyber-text);cursor:pointer">This is a FREE webinar (no payment required)</label>
          </div>
        </div>
        <div class="col-12">
          <div style="display:flex;align-items:flex-start;gap:0.5rem">
            <input type="checkbox" name="closing_soon_enabled" id="closing_soon_enabled" style="accent-color:var(--cyber-accent);margin-top:0.2rem" <?= ($editWebinar['closing_soon_enabled'] ?? 0) ? 'checked' : '' ?>>
            <label for="closing_soon_enabled" style="font-size:0.88rem;color:var(--cyber-text);cursor:pointer;line-height:1.45">
              Show &ldquo;Registration Closing Soon&rdquo; popup for this webinar
              <span style="display:block;font-size:.75rem;color:var(--cyber-muted);margin-top:.2rem">Also appears automatically when registration closes within <?= (int) WEBINAR_CLOSING_SOON_THRESHOLD_HOURS ?> hours.</span>
            </label>
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
          <div style="font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= escHtml((string) $w['title']) ?></div>
          <div style="font-size:0.72rem;color:var(--cyber-muted)"><?= escHtml((string) $w['category']) ?></div>
          <?php if (!empty($w['closing_soon_enabled'])): ?>
          <span style="font-size:.62rem;margin-top:.25rem;display:inline-block;background:#ef4444;color:#fff;padding:.2rem .55rem;border-radius:999px;font-weight:700">Closing Soon Popup</span>
          <?php endif; ?>
        </td>
        <td style="font-size:0.82rem"><?= escHtml((string) ($w['instructor'] ?? '—')) ?></td>
        <td style="font-size:0.8rem;white-space:nowrap">
          <?= date('d M Y', strtotime($w['scheduled_at'])) ?><br>
          <span style="color:var(--cyber-muted)"><?= date('h:i A', strtotime($w['scheduled_at'])) ?></span>
        </td>
        <td>
          <span style="color:var(--cyber-accent)"><?= (int) $w['reg_count'] ?></span>
          <span style="color:var(--cyber-muted);font-size:0.78rem">/ <?= (int) $w['max_seats'] ?></span>
        </td>
        <td class="webinar-pricing-badge-cell">
          <?php renderWebinarPricingBadge($w); ?>
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
(function () {
  var feeInput = document.getElementById('webinarFeeInr');
  var usdPreview = document.getElementById('webinarFeeUsdPreview');
  var isFree = document.getElementById('is_free');
  var rate = <?= json_encode(cybeorchInrPerUsd()) ?>;
  function syncFeeUi() {
    if (!feeInput) return;
    var inr = parseFloat(feeInput.value) || 0;
    if (isFree) isFree.checked = inr === 0;
    if (usdPreview) {
      var usd = inr > 0 ? Math.round(inr / rate) : 0;
      usdPreview.textContent = usd > 0 ? ('$' + usd.toLocaleString('en-US') + ' USD') : '$0 USD';
    }
  }
  feeInput?.addEventListener('input', syncFeeUi);
  isFree?.addEventListener('change', function () {
    if (this.checked && feeInput) {
      feeInput.value = '0';
      syncFeeUi();
    }
  });
  syncFeeUi();
})();
</script>
<?php renderAdminPageEnd(); ?>
