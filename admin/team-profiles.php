<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/team-profiles-admin.php';
requireAdminLogin();

ensureTeamProfileSchema();

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
        adminFlashRedirect('admin/team-profiles.php', 'error', 'Invalid request. Please try again.');
    }

    $postAction = (string) ($_POST['post_action'] ?? 'save');
    $postId = (int) ($_POST['member_id'] ?? $_GET['edit'] ?? 0);
    $back = $postId > 0 ? 'admin/team-profiles.php?edit=' . $postId : 'admin/team-profiles.php?action=add';

    if ($postAction === 'delete') {
        $deleteId = (int) ($_POST['member_id'] ?? 0);
        if ($deleteId < 1) {
            adminFlashRedirect('admin/team-profiles.php', 'error', 'Invalid team member.');
        }
        teamProfileAdminDelete($deleteId);
        adminFlashRedirect('admin/team-profiles.php', 'success', 'Team member deleted.');
    }

    $nonceScope = $postId > 0 ? 'team_profile_edit_' . $postId : 'team_profile_create';
    if (!adminValidateAndConsumeFormNonce($nonceScope, (string) ($_POST['form_nonce'] ?? ''))) {
        adminFlashRedirect($back, 'error', 'This form was already submitted. Refresh the page and try again.');
    }

    $existing = $postId > 0 ? teamProfileAdminFetchById($postId) : null;
    if ($postId > 0 && !$existing) {
        adminFlashRedirect('admin/team-profiles.php', 'error', 'Team member not found.');
    }

    $built = teamProfileAdminBuildPayload($_POST, $_FILES, $postId, $existing);
    if (!$built['ok']) {
        adminFlashRedirect($back, 'error', $built['error']);
    }

    try {
        if ($postId > 0) {
            teamProfileAdminUpdate($postId, $built['data']);
            adminFlashRedirect('admin/team-profiles.php', 'success', 'Team member updated successfully!');
        }

        teamProfileAdminInsert($built['data']);
        adminFlashRedirect('admin/team-profiles.php', 'success', 'Team member added successfully!');
    } catch (Throwable $e) {
        error_log('Team profile save failed: ' . $e->getMessage());
        adminFlashRedirect($back, 'error', 'Could not save the team member. Please try again.');
    }
}

$editMember = null;
if ($editId > 0) {
    $editMember = teamProfileAdminFetchById($editId);
    if (!$editMember) {
        $msg = 'Team member not found.';
        $msgType = 'error';
        $editId = 0;
    } else {
        $action = 'add';
    }
}

$formNonce = adminIssueFormNonce($editId > 0 ? 'team_profile_edit_' . $editId : 'team_profile_create');
$members = teamProfileAdminFetchAll();

$pageTitle = $action === 'add' ? ($editId ? 'Edit Team Member' : 'Add Team Member') : 'Team Profiles';
renderAdminPageStart($pageTitle, 'team-profiles', 'fa-user-group');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= $msgType === 'error' ? 'exclamation' : 'check' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add'): ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="margin-bottom:1.25rem">
  <p style="color:var(--cyber-muted);font-size:.88rem;margin:0">Manage founder and team profiles shown on the About Us page.</p>
  <a href="<?= adminUrl('admin/team-profiles.php?action=add') ?>" class="btn-primary-cyber"><i class="fas fa-plus me-1"></i>Add Team Member</a>
</div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'user-plus' ?>"></i><?= $editId ? 'Edit Team Member' : 'Add Team Member' ?></div>
  <div class="form-card-body">
    <form method="POST" id="teamProfileForm" enctype="multipart/form-data" action="<?= adminUrl('admin/team-profiles.php' . ($editId ? '?edit=' . (int) $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="form_nonce" value="<?= htmlspecialchars($formNonce) ?>">
      <input type="hidden" name="post_action" value="save">
      <?php if ($editId > 0): ?>
      <input type="hidden" name="member_id" id="teamMemberIdField" value="<?= (int) $editId ?>">
      <?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" id="teamMemberName" class="form-control" required maxlength="150" value="<?= htmlspecialchars((string) ($editMember['name'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Display Section</label>
          <select name="profile_type" class="form-select">
            <option value="team" <?= ($editMember['profile_type'] ?? 'team') === 'team' ? 'selected' : '' ?>>Team Profiles</option>
            <option value="founder" <?= ($editMember['profile_type'] ?? '') === 'founder' ? 'selected' : '' ?>>Founder Profile</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Designation / Role</label>
          <input type="text" name="designation" class="form-control" maxlength="150" placeholder="e.g. Software Developer" value="<?= htmlspecialchars(teamMemberDesignation($editMember ?? [])) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Degree / Qualification</label>
          <input type="text" name="degree" class="form-control" maxlength="150" placeholder="e.g. B.Tech" value="<?= htmlspecialchars(teamMemberDegree($editMember ?? [])) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Description / Bio</label>
          <textarea name="bio" class="form-control" rows="4" placeholder="Short professional bio shown on the About Us page"><?= htmlspecialchars((string) ($editMember['bio'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Profile Image</label>
          <input type="file" name="photo" id="teamPhotoInput" class="form-control" accept="image/jpeg,image/png,image/webp">
          <p class="form-text" style="font-size:.75rem;color:var(--cyber-muted)">JPG, JPEG, PNG, or WEBP. Max <?= (int) (MAX_UPLOAD_SIZE / 1024 / 1024) ?>MB. Saved to assets/images/Team_Profile/</p>
        </div>
        <div class="col-md-3">
          <label class="form-label">Display Order</label>
          <input type="number" name="sort_order" class="form-control" min="0" value="<?= (int) ($editMember['sort_order'] ?? 0) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="is_active" class="form-select">
            <option value="1" <?= !isset($editMember['is_active']) || !empty($editMember['is_active']) ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= isset($editMember['is_active']) && empty($editMember['is_active']) ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Image Preview</label>
          <div id="teamPhotoPreviewWrap" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <?php
            $currentPhoto = trim((string) ($editMember['photo_path'] ?? ''));
            $previewSrc = $currentPhoto !== '' ? teamProfileAdminPhotoUrl($currentPhoto) : '';
            ?>
            <img id="teamPhotoPreview" src="<?= htmlspecialchars($previewSrc) ?>" alt="" style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid var(--cyber-border);<?= $previewSrc === '' ? 'display:none;' : '' ?>">
            <span id="teamPhotoPreviewPlaceholder" style="color:var(--cyber-muted);font-size:.85rem;<?= $previewSrc !== '' ? 'display:none;' : '' ?>">No image selected</span>
          </div>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit" id="teamProfileSubmitBtn"><i class="fas fa-save me-1"></i><?= $editId ? 'Update' : 'Create' ?></button>
          <a href="<?= adminUrl('admin/team-profiles.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else: ?>

<div class="section-card">
  <div class="section-card-header">All Team Members (<?= count($members) ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Photo</th>
            <th>Name</th>
            <th>Designation</th>
            <th>Degree</th>
            <th>Section</th>
            <th>Order</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($members === []): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No team members yet. <a href="<?= adminUrl('admin/team-profiles.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a></td></tr>
          <?php else: foreach ($members as $m):
            $photo = trim((string) ($m['photo_path'] ?? ''));
          ?>
          <tr>
            <td>
              <?php if ($photo !== ''): ?>
              <img src="<?= htmlspecialchars(teamProfileAdminPhotoUrl($photo)) ?>" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:1px solid var(--cyber-border)">
              <?php else: ?>
              <span style="display:inline-flex;width:44px;height:44px;border-radius:50%;background:rgba(0,212,255,0.1);align-items:center;justify-content:center;color:var(--cyber-accent);font-weight:700"><?= htmlspecialchars(strtoupper(substr((string) $m['name'], 0, 1))) ?></span>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars((string) $m['name']) ?></strong></td>
            <td><?= htmlspecialchars(teamMemberDesignation($m) ?: '—') ?></td>
            <td><?= htmlspecialchars(teamMemberDegree($m) ?: '—') ?></td>
            <td><?= htmlspecialchars(teamProfileSectionLabel($m)) ?></td>
            <td><?= (int) ($m['sort_order'] ?? 0) ?></td>
            <td><span class="badge-status badge-<?= !empty($m['is_active']) ? 'paid' : 'pending' ?>"><?= htmlspecialchars(teamProfileStatusLabel($m)) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/team-profiles.php?edit=' . (int) $m['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this team member? They will be removed from the website.');">
                <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                <input type="hidden" name="post_action" value="delete">
                <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                <button type="submit" class="btn-sm-cyber btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php adminRenderFormSubmitGuard('teamProfileForm', 'teamProfileSubmitBtn'); ?>
<script>
(function () {
  var input = document.getElementById('teamPhotoInput');
  var preview = document.getElementById('teamPhotoPreview');
  var placeholder = document.getElementById('teamPhotoPreviewPlaceholder');
  if (!input || !preview) return;
  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file) return;
    if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
      alert('Please choose a JPG, PNG, or WEBP image.');
      input.value = '';
      return;
    }
    var reader = new FileReader();
    reader.onload = function (e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
      if (placeholder) placeholder.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });
})();
</script>
<?php renderAdminPageEnd(); ?>
