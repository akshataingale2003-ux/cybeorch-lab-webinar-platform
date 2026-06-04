<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/website-registration.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

$viewId = (int) ($_GET['view'] ?? 0);
$status = sanitize($_GET['status'] ?? 'all');
if (!in_array($status, ['active', 'blocked', 'all', 'deleted'], true)) {
    $status = 'active';
}
$searchQ = trim((string) ($_GET['q'] ?? ''));

$viewRow = null;
if ($viewId > 0) {
    $viewRow = getWebsiteUserById($viewId);
    if ($viewRow) {
        $platform = getPlatformUserForWebsiteEmail((string) $viewRow['email']);
        if ($platform) {
            $viewRow['platform_user_id'] = (int) $platform['id'];
            $viewRow['platform_password_hash'] = $platform['password'] ?? '';
            $viewRow['platform_password_plain'] = $platform['password_plain'] ?? '';
        }
    }
}
$counts = adminCountWebsiteUsersByStatus();
$rows = adminFetchWebsiteUsers(['status' => $status, 'q' => $searchQ, 'limit' => 300]);
$totalInDb = (int) dbTry(fn () => getWebsiteUsersCount(), 0);

renderAdminPageStart('Website Registrations', 'website-registrations', 'fa-user-plus');
?>

<?php if ($viewRow):
    $blocked = adminRecordIsBlocked($viewRow);
    $inTrash = adminUserRecordStatus($viewRow) === 'deleted';
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>Registration #<?= (int) $viewRow['id'] ?></span>
    <?php adminRenderUserStatusBadge($viewRow); ?>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Full name</label><span><?= htmlspecialchars($viewRow['full_name']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><a href="mailto:<?= htmlspecialchars($viewRow['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewRow['email']) ?></a></span></div>
      <div class="detail-item"><label>Mobile</label><span><?= htmlspecialchars($viewRow['mobile']) ?></span></div>
      <div class="detail-item"><label>Registered</label><span><?= date('d M Y, h:i A', strtotime($viewRow['created_at'])) ?></span></div>
      <div class="detail-item"><label>Password</label><span><?php
        $viewPwd = adminWebsiteUserDisplayPassword($viewRow);
        if ($viewPwd !== '') {
            echo '<code style="font-size:0.9rem;word-break:break-all">' . htmlspecialchars($viewPwd) . '</code>';
        } elseif (adminWebsiteUserHasPlatformPassword($viewRow)) {
            echo '<span style="font-size:0.82rem;color:var(--cyber-muted)">Set before admin recording — use User Management to reset</span>';
        } else {
            echo '<span class="password-hint" style="margin:0">Session / OTP only — no platform password yet</span>';
        }
      ?></span></div>
    </div>
    <?php if (!empty($viewRow['platform_user_id'])): ?>
    <p class="password-hint mt-2"><a href="<?= adminUrl('admin/students.php?view=' . (int) $viewRow['platform_user_id']) ?>" class="btn-sm-link">Open platform user account →</a></p>
    <?php else: ?>
    <p class="password-hint mt-2"><i class="fas fa-info-circle"></i> Popup access uses OTP session. A linked platform password appears here after the user completes full signup.</p>
    <?php endif; ?>
    <div class="mt-3">
      <?php renderAdminRecordActions('website_user', (int) $viewRow['id'], $blocked, $inTrash); ?>
      <a href="<?= adminUrl('admin/website-registrations.php?status=' . rawurlencode($status) . ($searchQ !== '' ? '&q=' . rawurlencode($searchQ) : '')) ?>" class="btn-cancel ms-2">← Back</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">Popup website registrations (<?= count($rows) ?> shown<?= $totalInDb !== count($rows) ? ' · ' . number_format($totalInDb) . ' total in database' : '' ?>)</div>
  <div class="section-card-body">
    <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      Homepage mandatory registration (<code>website_users</code>). Platform accounts with passwords are in <a href="<?= adminUrl('admin/students.php') ?>" class="btn-sm-link">User Management</a>.
    </p>
    <?php renderAdminListToolbar('admin/website-registrations.php', $status, $searchQ, [
        'active'  => 'Active',
        'blocked' => 'Blocked',
        'all'     => 'All',
        'deleted' => 'Deleted',
    ], $counts); ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Status</th><th>Date</th><th>Full name</th><th>Email</th><th>Mobile</th><th>Password</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No registrations match your filters. Try <a href="<?= adminUrl('admin/website-registrations.php?status=all') ?>" class="btn-sm-link">All</a>.</td></tr>
          <?php else: foreach ($rows as $r):
            $blocked = adminRecordIsBlocked($r);
            $inTrash = adminUserRecordStatus($r) === 'deleted';
          ?>
          <tr<?= renderAdminRecordRowAttrs('website_user', (int) $r['id'], $blocked && !$inTrash) ?>>
            <td><?php adminRenderUserStatusBadge($r); ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td><a href="<?= adminUrl('admin/website-registrations.php?view=' . (int) $r['id']) ?>" class="btn-sm-link"><?= htmlspecialchars($r['full_name']) ?></a></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= htmlspecialchars($r['mobile']) ?></td>
            <td>
              <?php
              $listPwd = adminWebsiteUserDisplayPassword($r);
              if ($listPwd !== ''): ?>
              <code style="font-size:0.78rem;word-break:break-all"><?= htmlspecialchars($listPwd) ?></code>
              <?php elseif (adminWebsiteUserHasPlatformPassword($r)): ?>
              <span style="font-size:0.78rem;color:var(--cyber-muted)" title="Password exists but was not recorded for display">—</span>
              <?php else: ?>
              <span style="font-size:0.78rem;color:var(--cyber-muted)" title="OTP / session access only">—</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= adminUrl('admin/website-registrations.php?view=' . (int) $r['id']) ?>" class="btn-sm-link">View</a>
              <?php renderAdminRecordActions('website_user', (int) $r['id'], $blocked, $inTrash); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
