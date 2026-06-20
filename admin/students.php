<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

$viewId = (int) ($_GET['view'] ?? 0);
$status = sanitize($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'blocked', 'all', 'deleted'], true)) {
    $status = 'active';
}
$searchQ = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $uid = (int) ($_POST['user_id'] ?? 0);
    $result = adminResetUserPassword(
        $uid,
        (string) ($_POST['new_password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );
    if ($result['success']) {
        adminFlashRedirect('admin/students.php?view=' . $uid, 'success', $result['message']);
    }
    setFlash('error', $result['message']);
    header('Location: ' . adminUrl('admin/students.php?view=' . $uid));
    exit;
}

$viewUser = $viewId > 0 ? adminFetchUserById($viewId, true) : null;
$userCounts = adminCountUsersByStatus();
$students = adminFetchUsers(['status' => $status, 'q' => $searchQ, 'limit' => 300]);

renderAdminPageStart('User Management', 'students', 'fa-users');
$flash = getFlash();
if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<?php if ($viewUser):
    $blocked = adminRecordIsBlocked($viewUser);
    $inTrash = adminUserRecordStatus($viewUser) === 'deleted';
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>User #<?= (int) $viewUser['id'] ?> — <?= htmlspecialchars($viewUser['full_name']) ?></span>
    <?php adminRenderUserStatusBadge($viewUser); ?>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Full name</label><span><?= htmlspecialchars($viewUser['full_name']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><a href="mailto:<?= htmlspecialchars($viewUser['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewUser['email']) ?></a></span></div>
      <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewUser['phone'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Referral code</label><span><?= htmlspecialchars($viewUser['referral_code'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Wallet (NxL)</label><span>₹<?= number_format((float) $viewUser['wallet_balance'], 2) ?></span></div>
      <div class="detail-item"><label>Email verified</label><span><?= (int) $viewUser['is_verified'] ? 'Yes' : 'No' ?></span></div>
      <div class="detail-item"><label>Joined</label><span><?= date('d M Y, h:i A', strtotime($viewUser['created_at'])) ?></span></div>
      <?php if ($inTrash && !empty($viewUser['deleted_at'])): ?>
      <div class="detail-item"><label>Deleted at</label><span><?= date('d M Y, h:i A', strtotime($viewUser['deleted_at'])) ?></span></div>
      <?php endif; ?>
    </div>

    <div class="form-card mt-3">
      <div class="form-card-header"><i class="fas fa-key"></i> Account security</div>
      <div class="form-card-body">
        <label class="form-label">Stored password</label>
        <?php renderAdminMaskedPasswordField(); ?>

        <?php if (!$inTrash): ?>
        <form method="post" class="mt-3" style="max-width:420px" autocomplete="off">
          <input type="hidden" name="action" value="reset_password">
          <input type="hidden" name="user_id" value="<?= (int) $viewUser['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <div class="mb-2">
            <label class="form-label" for="new_password">New password</label>
            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" autocomplete="new-password" placeholder="Min. 8 chars, 1 uppercase, 1 number">
          </div>
          <div class="mb-3">
            <label class="form-label" for="confirm_password">Confirm password</label>
            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
          </div>
          <button type="submit" class="btn-submit" onclick="return confirm('Set a new password for this user? They must use it on next login.');"><i class="fas fa-redo"></i> Reset password</button>
        </form>
        <?php else: ?>
        <p class="password-hint mt-2">Restore this account before resetting the password.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
      <?php if ($inTrash): ?>
        <?php renderAdminRecordActions('user', (int) $viewUser['id'], false, true); ?>
      <?php else: ?>
        <?php renderAdminRecordActions('user', (int) $viewUser['id'], $blocked, false); ?>
      <?php endif; ?>
      <a href="<?= adminUrl('admin/students.php?status=' . rawurlencode($status) . ($searchQ !== '' ? '&q=' . rawurlencode($searchQ) : '')) ?>" class="btn-cancel">← Back to list</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="stat-cards-row">
  <div class="stat-card"><div class="stat-value" style="color:var(--cyber-green)"><?= $userCounts['active'] ?></div><div class="stat-label">Active users</div></div>
  <div class="stat-card"><div class="stat-value" style="color:var(--cyber-orange)"><?= $userCounts['blocked'] ?></div><div class="stat-label">Blocked</div></div>
  <div class="stat-card"><div class="stat-value" style="color:#ff8888"><?= $userCounts['deleted'] ?></div><div class="stat-label">Deleted</div></div>
  <div class="stat-card"><div class="stat-value"><?= $userCounts['all'] ?></div><div class="stat-label">All (non-deleted)</div></div>
</div>

<div class="section-card">
  <div class="section-card-header">Platform users (<?= count($students) ?> shown)</div>
  <div class="section-card-body">
    <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      OTP / platform accounts in <code>users</code>. Website popup signups are under <a href="<?= adminUrl('admin/website-registrations.php') ?>" class="btn-sm-link">Website signups</a>.
    </p>
    <?php renderAdminListToolbar('admin/students.php', $status, $searchQ, [
        'active'  => 'Active',
        'blocked' => 'Blocked',
        'all'     => 'All',
        'deleted' => 'Deleted',
    ], $userCounts); ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Joined</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Wallet</th>
            <th>Verified</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$students): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No users match your filters.</td></tr>
          <?php else: foreach ($students as $s):
            $blocked = adminRecordIsBlocked($s);
            $inTrash = adminUserRecordStatus($s) === 'deleted';
          ?>
          <tr<?= renderAdminRecordRowAttrs('user', (int) $s['id'], $blocked && !$inTrash) ?>>
            <td><?php adminRenderUserStatusBadge($s); ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
            <td>
              <a href="<?= adminUrl('admin/students.php?view=' . (int) $s['id']) ?>" class="btn-sm-link"><?= htmlspecialchars($s['full_name']) ?></a>
            </td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
            <td>₹<?= number_format((float) $s['wallet_balance'], 2) ?></td>
            <td><?= (int) $s['is_verified'] ? '<span class="badge-status badge-paid">Yes</span>' : '<span class="badge-status badge-pending">No</span>' ?></td>
            <td>
              <a href="<?= adminUrl('admin/students.php?view=' . (int) $s['id']) ?>" class="btn-sm-link me-1">View</a>
              <?php renderAdminRecordActions('user', (int) $s['id'], $blocked, $inTrash); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
