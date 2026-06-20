<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/recorded-sessions.php';
requireAdminLogin();

ensureRecordedSessionsSchema();

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
        adminFlashRedirect('admin/recorded-sessions.php', 'error', 'Invalid request. Please try again.');
    }

    $postId = (int) ($_POST['recorded_session_id'] ?? 0);
    $back = $postId > 0 ? 'admin/recorded-sessions.php?edit=' . $postId : 'admin/recorded-sessions.php?action=add';

    $existing = null;
    if ($postId > 0) {
        $existing = recordedSessionGetById($postId);
        if (!$existing) {
            adminFlashRedirect('admin/recorded-sessions.php', 'error', 'Recorded session not found.');
        }
    }

    $nonceScope = $postId > 0 ? 'recorded_session_edit_' . $postId : 'recorded_session_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect('admin/recorded-sessions.php', 'error', 'This session was already saved. Refresh the page to add another.');
    }

    $built = recordedSessionBuildPayloadFromPost($_POST);
    if (!$built['ok']) {
        adminFlashRedirect($back, 'error', $built['error']);
    }
    $data = $built['data'];
    $data['slug'] = recordedSessionEnsureUniqueSlug($data['slug'], $postId);

    $videoPath = (string) ($existing['video_path'] ?? '');
    $videoOriginal = (string) ($existing['video_original'] ?? '');
    $videoMime = (string) ($existing['video_mime'] ?? '');
    $videoSize = (int) ($existing['video_size'] ?? 0);

    $hasUpload = isset($_FILES['video']) && (int) ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $uploadToken = trim((string) ($_POST['video_upload_token'] ?? ''));

    if ($uploadToken !== '') {
        $claimed = recordedSessionClaimUploadToken((int) ($_SESSION['admin_id'] ?? 0), $uploadToken);
        if (!$claimed['ok']) {
            adminFlashRedirect($back, 'error', $claimed['error']);
        }
        if ($videoPath !== '') {
            recordedSessionDeleteVideoFile($videoPath);
        }
        $videoPath = $claimed['path'];
        $videoOriginal = $claimed['original_name'];
        $videoMime = $claimed['mime'];
        $videoSize = $claimed['size'];
    } elseif ($hasUpload) {
        $upload = validateAndStoreRecordedSessionVideo($_FILES['video']);
        if (!$upload['ok']) {
            adminFlashRedirect($back, 'error', $upload['error']);
        }
        if ($videoPath !== '') {
            recordedSessionDeleteVideoFile($videoPath);
        }
        $videoPath = $upload['path'];
        $videoOriginal = $upload['original_name'];
        $videoMime = $upload['mime'];
        $videoSize = $upload['size'];
    } elseif ($postId < 1) {
        adminFlashRedirect($back, 'error', 'Please upload a video file for this recorded session.');
    }

    $hasVideo = $videoPath !== '';
    $data['status'] = recordedSessionResolveStatusOnSave($data['status'], $hasVideo, $data);

    if ($data['status'] === RECORDED_SESSION_STATUS_PUBLISHED && !$hasVideo) {
        adminFlashRedirect($back, 'error', 'A video file is required to publish this session to My Courses.');
    }

    try {
        if ($postId > 0) {
            db()->execute(
                'UPDATE recorded_sessions SET title=?, slug=?, description=?, link_type=?, webinar_id=?, bootcamp_id=?, video_path=?, video_original=?, video_mime=?, video_size=?, status=?, sort_order=?, updated_at=NOW() WHERE id=?',
                [
                    $data['title'],
                    $data['slug'],
                    $data['description'],
                    $data['link_type'],
                    $data['webinar_id'],
                    $data['bootcamp_id'],
                    $videoPath !== '' ? $videoPath : null,
                    $videoOriginal !== '' ? $videoOriginal : null,
                    $videoMime !== '' ? $videoMime : null,
                    $videoSize > 0 ? $videoSize : null,
                    $data['status'],
                    $data['sort_order'],
                    $postId,
                ]
            );
            $saved = recordedSessionGetById($postId);
            $msg = $saved ? recordedSessionPublishedSaveMessage($saved) : 'Recorded session updated successfully!';
            adminFlashRedirect('admin/recorded-sessions.php', 'success', $msg);
        }

        adminCatalogRejectCreateIfAtLimit('recorded_sessions', 'admin/recorded-sessions.php');

        $newId = db()->insert(
            'INSERT INTO recorded_sessions (title, slug, description, link_type, webinar_id, bootcamp_id, video_path, video_original, video_mime, video_size, status, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['title'],
                $data['slug'],
                $data['description'],
                $data['link_type'],
                $data['webinar_id'],
                $data['bootcamp_id'],
                $videoPath,
                $videoOriginal,
                $videoMime,
                $videoSize,
                $data['status'],
                $data['sort_order'],
            ]
        );
        $saved = recordedSessionGetById($newId);
        $msg = $saved ? recordedSessionPublishedSaveMessage($saved) : 'Recorded session created successfully!';
        adminFlashRedirect('admin/recorded-sessions.php', 'success', $msg);
    } catch (Throwable $e) {
        error_log('Recorded session save failed: ' . $e->getMessage());
        adminFlashRedirect($back, 'error', 'Could not save the recorded session. Please try again.');
    }
}

$editRow = null;
if ($editId > 0) {
    $editRow = recordedSessionGetById($editId);
    if (!$editRow) {
        adminFlashRedirect('admin/recorded-sessions.php', 'error', 'Recorded session not found.');
    }
    $action = 'add';
}

$viewRow = null;
if ($viewId > 0 && $action !== 'add') {
    $viewRow = recordedSessionGetById($viewId);
    if (!$viewRow) {
        adminFlashRedirect('admin/recorded-sessions.php', 'error', 'Recorded session not found.');
    }
}

adminCatalogRejectAddActionIfAtLimit('recorded_sessions', $action, $editId, 'admin/recorded-sessions.php');

$formNonce = adminIssueFormNonce($editId > 0 ? 'recorded_session_edit_' . $editId : 'recorded_session_create');

$items = recordedSessionListForAdmin();
$webinars = db()->fetchAll('SELECT id, title, scheduled_at FROM webinars WHERE ' . adminSqlActive() . ' ORDER BY scheduled_at DESC');
$bootcamps = db()->fetchAll('SELECT id, title, start_date FROM bootcamps WHERE ' . adminSqlActive() . ' ORDER BY start_date DESC');

$pageTitle = $action === 'add' ? ($editId ? 'Edit Recorded Session' : 'Add Recorded Session') : 'Recorded Sessions';
renderAdminPageStart($pageTitle, 'recorded-sessions', 'fa-circle-play');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<?php adminCatalogRenderListToolbar('recorded_sessions', 'admin/recorded-sessions.php?action=add', 'Recorded Session'); ?>
<?php endif; ?>

<?php if ($viewRow): ?>
<div class="section-card mb-3">
  <div class="section-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View Recorded Session</span>
    <div class="d-flex gap-2 flex-wrap">
      <a href="<?= adminUrl('admin/recorded-sessions.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i> Edit</a>
      <a href="<?= adminUrl('admin/recorded-sessions.php') ?>" class="btn-cancel">Back to list</a>
    </div>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Title</label><div><?= htmlspecialchars($viewRow['title']) ?></div></div>
      <div class="detail-item"><label>Status</label><div><span class="badge-status badge-<?= ($viewRow['status'] ?? '') === RECORDED_SESSION_STATUS_PUBLISHED ? 'paid' : 'pending' ?>"><?= htmlspecialchars($viewRow['status']) ?></span></div></div>
      <div class="detail-item"><label>Linked to</label><div><?= htmlspecialchars(recordedSessionLinkedProgramTypeLabel($viewRow)) ?>: <?= htmlspecialchars(recordedSessionLinkedProgramLabel($viewRow)) ?></div></div>
      <div class="detail-item"><label>Video file</label><div><?= htmlspecialchars((string) ($viewRow['video_original'] ?? '—')) ?><?php if (!empty($viewRow['video_size'])): ?> (<?= htmlspecialchars(recordedSessionFormatBytes((int) $viewRow['video_size'])) ?>)<?php endif; ?></div></div>
      <div class="detail-item"><label>My Courses visibility</label><div>
        <?php if (($viewRow['status'] ?? '') === RECORDED_SESSION_STATUS_PUBLISHED && recordedSessionHasVideoFile($viewRow) && recordedSessionIsLinkedToProgram($viewRow)): ?>
        <span class="badge-status badge-paid">Live for <?= (int) recordedSessionCountActiveEnrolledUsers($viewRow) ?> enrolled user(s)</span>
        <?php elseif (($viewRow['status'] ?? '') === RECORDED_SESSION_STATUS_DRAFT): ?>
        <span class="badge-status badge-pending">Draft — hidden from My Courses</span>
        <?php else: ?>
        <span class="badge-status badge-pending">Not visible (needs video, link, and Published status)</span>
        <?php endif; ?>
      </div></div>
      <div class="detail-item"><label>Sort order</label><div><?= (int) ($viewRow['sort_order'] ?? 0) ?></div></div>
    </div>
    <?php if (!empty($viewRow['description'])): ?>
    <div class="msg-body mt-2"><?= nl2br(htmlspecialchars($viewRow['description'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($viewRow['video_path'])): ?>
    <div class="mt-3">
      <label class="form-label">Preview</label>
      <?php recordedSessionRenderVideoPlayer((int) $viewRow['id']); ?>
      <p class="password-hint mt-2"><i class="fas fa-lock me-1"></i>Enrolled users only · <?= COURSE_ACCESS_MONTHS ?>-month access · max <?= RECORDED_SESSION_MAX_WATCHES ?> full views per user.</p>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Recorded Session' : 'Create Recorded Session' ?></div>
  <div class="form-card-body">
    <form method="POST" id="recordedSessionAdminForm" enctype="multipart/form-data" data-require-video="<?= $editId ? '0' : '1' ?>" action="<?= adminUrl('admin/recorded-sessions.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <?php if ($editId): ?><input type="hidden" name="recorded_session_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Session Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="e.g. Ethical Hacking — Session Recording" value="<?= escHtml((string) ($editRow['title'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (recordedSessionStatusOptions() as $statusOpt): ?>
            <option value="<?= htmlspecialchars($statusOpt) ?>" <?= ($editRow['status'] ?? RECORDED_SESSION_STATUS_PUBLISHED) === $statusOpt ? 'selected' : '' ?>><?= htmlspecialchars($statusOpt) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="password-hint">Published sessions with a linked course appear immediately in My Courses for enrolled users.</p>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="4" placeholder="Summary or notes about this recording"><?= escHtml((string) ($editRow['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Link to *</label>
          <select name="link_type" id="recordedLinkType" class="form-select" required>
            <?php foreach (recordedSessionLinkTypeOptions() as $val => $label): ?>
            <option value="<?= htmlspecialchars($val) ?>" <?= ($editRow['link_type'] ?? RECORDED_SESSION_LINK_WEBINAR) === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8" id="recordedWebinarField">
          <label class="form-label">Webinar *</label>
          <select name="webinar_id" id="recordedWebinarId" class="form-select">
            <option value="">— Select webinar —</option>
            <?php foreach ($webinars as $w): ?>
            <option value="<?= (int) $w['id'] ?>" <?= (int) ($editRow['webinar_id'] ?? 0) === (int) $w['id'] ? 'selected' : '' ?>>
              <?= escHtml((string) $w['title']) ?> (<?= date('d M Y', strtotime($w['scheduled_at'])) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8" id="recordedBootcampField" style="display:none">
          <label class="form-label">Bootcamp *</label>
          <select name="bootcamp_id" id="recordedBootcampId" class="form-select">
            <option value="">— Select bootcamp —</option>
            <?php foreach ($bootcamps as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= (int) ($editRow['bootcamp_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
              <?= escHtml((string) $b['title']) ?> (<?= date('d M Y', strtotime($b['start_date'])) ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Video Upload <?= $editId ? '' : '*' ?></label>
          <input type="file" id="recordedVideoFile" class="form-control" accept=".mp4,.mkv,.webm,.mov,.avi,.m4v,video/*">
          <input type="hidden" name="video_upload_token" id="videoUploadToken" value="">
          <div id="recordedUploadProgress" style="display:none;margin-top:.75rem">
            <div style="background:rgba(0,0,0,0.25);border-radius:6px;height:8px;overflow:hidden">
              <div id="recordedUploadProgressBar" style="height:100%;width:0;background:var(--cyber-accent);transition:width .2s"></div>
            </div>
            <p id="recordedUploadProgressText" style="font-size:.78rem;color:var(--cyber-muted);margin:.35rem 0 0"></p>
          </div>
          <p id="recordedUploadStatus" class="password-hint" style="margin-top:.5rem"></p>
          <p class="password-hint">MP4, MKV, WEBM, MOV, AVI, or M4V. Max <?= htmlspecialchars(recordedSessionMaxBytesLabel()) ?>. Large files upload in chunks automatically.</p>
          <?php if ($editId && !empty($editRow['video_original'])): ?>
          <p style="font-size:.82rem;color:var(--cyber-muted);margin-top:.5rem">
            Current file: <strong style="color:var(--cyber-accent)"><?= htmlspecialchars($editRow['video_original']) ?></strong>
            <?php if (!empty($editRow['video_size'])): ?>(<?= htmlspecialchars(recordedSessionFormatBytes((int) $editRow['video_size'])) ?>)<?php endif; ?>
            — select a new file above to replace it.
          </p>
          <?php endif; ?>
        </div>
        <div class="col-12 d-flex gap-2 flex-wrap">
          <button type="submit" id="recordedSessionSubmitBtn" class="btn-submit"><i class="fas fa-save me-1"></i><?= $editId ? 'Update Session' : 'Save Session' ?></button>
          <a href="<?= adminUrl('admin/recorded-sessions.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  var linkType = document.getElementById('recordedLinkType');
  var webinarField = document.getElementById('recordedWebinarField');
  var bootcampField = document.getElementById('recordedBootcampField');
  var webinarSelect = document.getElementById('recordedWebinarId');
  var bootcampSelect = document.getElementById('recordedBootcampId');
  if (!linkType) return;
  function syncLinkFields() {
    var isWebinar = linkType.value === 'webinar';
    webinarField.style.display = isWebinar ? '' : 'none';
    bootcampField.style.display = isWebinar ? 'none' : '';
    webinarSelect.required = isWebinar;
    bootcampSelect.required = !isWebinar;
    if (isWebinar) bootcampSelect.value = '';
    else webinarSelect.value = '';
  }
  linkType.addEventListener('change', syncLinkFields);
  syncLinkFields();
})();
</script>
<script>
window.recordedSessionUploadConfig = {
  uploadUrl: <?= json_encode(url('api/recorded-session-upload.php')) ?>,
  csrf: <?= json_encode(generateCSRF()) ?>,
  chunkSize: <?= RECORDED_SESSION_CHUNK_BYTES ?>,
  maxBytes: <?= RECORDED_SESSION_MAX_BYTES ?>,
  maxLabel: <?= json_encode(recordedSessionMaxBytesLabel()) ?>
};
</script>
<script src="<?= url('assets/js/recorded-session-chunk-upload.js') ?>"></script>
<?php endif; ?>

<?php if ($action !== 'add' && !$viewId): ?>
<div class="section-card">
  <div class="section-card-header">All recorded sessions (<?= adminCatalogUsageLabel('recorded_sessions') ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>#</th><th>Title</th><th>Linked program</th><th>Video</th><th>Status</th><th>My Courses</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$items): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No recorded sessions yet. <?php if (adminCatalogCanCreate('recorded_sessions')): ?><a href="<?= adminUrl('admin/recorded-sessions.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a><?php endif; ?></td></tr>
          <?php else: foreach ($items as $i => $row):
            $blocked = adminRecordIsBlocked($row);
            $descPreview = (string) ($row['description'] ?? '');
            if ($descPreview !== '') {
                $descPreview = function_exists('mb_strimwidth')
                    ? mb_strimwidth($descPreview, 0, 80, '…')
                    : (strlen($descPreview) > 80 ? substr($descPreview, 0, 77) . '…' : $descPreview);
            }
          ?>
          <tr<?= renderAdminRecordRowAttrs('recorded_session', (int) $row['id'], $blocked) ?>>
            <td style="color:var(--cyber-muted);font-size:0.78rem"><?= $i + 1 ?></td>
            <td>
              <strong><?= htmlspecialchars($row['title']) ?></strong>
              <?php if ($descPreview !== ''): ?>
              <br><span style="font-size:.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($descPreview) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-status badge-read" style="margin-bottom:.25rem;display:inline-block"><?= htmlspecialchars(recordedSessionLinkedProgramTypeLabel($row)) ?></span><br>
              <?= htmlspecialchars(recordedSessionLinkedProgramLabel($row)) ?>
            </td>
            <td style="font-size:.82rem">
              <?php if (!empty($row['video_original'])): ?>
              <i class="fas fa-file-video" style="color:var(--cyber-accent)"></i> <?= htmlspecialchars($row['video_original']) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><span class="badge-status badge-<?= ($row['status'] ?? '') === RECORDED_SESSION_STATUS_PUBLISHED ? 'paid' : 'pending' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td style="font-size:.78rem">
              <?php if (($row['status'] ?? '') === RECORDED_SESSION_STATUS_PUBLISHED && recordedSessionHasVideoFile($row) && recordedSessionIsLinkedToProgram($row)): ?>
              <span style="color:var(--cyber-green)"><?= (int) recordedSessionCountActiveEnrolledUsers($row) ?> enrolled</span>
              <?php else: ?>
              <span style="color:var(--cyber-muted)">—</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/recorded-sessions.php?view=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="View"><i class="fas fa-eye"></i></a>
              <a href="<?= adminUrl('admin/recorded-sessions.php?edit=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <?php renderAdminRecordActions('recorded_session', (int) $row['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('recordedSessionAdminForm', 'recordedSessionSubmitBtn'); ?>
<?php renderAdminPageEnd(); ?>
