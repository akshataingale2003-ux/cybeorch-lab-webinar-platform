<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_webinar_attended'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/registrations.php?tab=webinar', 'error', 'Invalid request.');
    }
    $regId = (int) ($_POST['registration_id'] ?? 0);
    $result = markWebinarRegistrationAttended($regId);
    $flash = $result['success'] ? 'success' : 'error';
    adminFlashRedirect('admin/registrations.php?tab=webinar', $flash, $result['message']);
}

$tab = ($_GET['tab'] ?? 'webinar') === 'bootcamp' ? 'bootcamp' : 'webinar';
$sort = adminParseListSortParam();
$order = adminSortSqlDirection($sort);
$tabExtra = ['tab' => $tab];

$webinarRegs = adminDb(
    fn () => db()->fetchAll(
        "SELECT wr.*, u.full_name, u.email, w.title AS item_title
         FROM webinar_registrations wr
         JOIN users u ON u.id = wr.user_id
         JOIN webinars w ON w.id = wr.webinar_id
         WHERE " . adminSqlActive('wr') . "
         ORDER BY wr.registered_at {$order} LIMIT 150"
    ),
    []
);

$bootcampRegs = adminDb(
    fn () => db()->fetchAll(
        "SELECT be.*, u.full_name, u.email, b.title AS item_title
         FROM bootcamp_enrollments be
         JOIN users u ON u.id = be.user_id
         JOIN bootcamps b ON b.id = be.bootcamp_id
         WHERE " . adminSqlActive('be') . "
         ORDER BY be.enrolled_at {$order} LIMIT 150"
    ),
    []
);

renderAdminPageStart('Registrations', 'registrations', 'fa-ticket-alt');
?>

<div class="section-card">
  <div class="section-card-header">Course registrations</div>
  <div class="section-card-body">
    <?php renderAdminSortBar('admin/registrations.php', $sort, $tabExtra); ?>
    <div class="filter-bar">
      <a href="<?= adminUrl('admin/registrations.php?' . http_build_query(array_merge($tabExtra, ['sort' => $sort, 'tab' => 'webinar']))) ?>" class="<?= $tab === 'webinar' ? 'active' : '' ?>">Webinars (<?= count($webinarRegs) ?>)</a>
      <a href="<?= adminUrl('admin/registrations.php?' . http_build_query(array_merge($tabExtra, ['sort' => $sort, 'tab' => 'bootcamp']))) ?>" class="<?= $tab === 'bootcamp' ? 'active' : '' ?>">Bootcamps (<?= count($bootcampRegs) ?>)</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Date</th><th>Reg. No</th><th>User Registration &amp; Trainee</th><th><?= $tab === 'webinar' ? 'Webinar' : 'Bootcamp' ?></th><th>Payment</th><?php if ($tab === 'webinar'): ?><th>Attendance</th><?php endif; ?><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php
          $rows = $tab === 'webinar' ? $webinarRegs : $bootcampRegs;
          if (!$rows): ?>
          <tr><td colspan="<?= $tab === 'webinar' ? 7 : 6 ?>" style="color:var(--cyber-muted)">No registrations yet.</td></tr>
          <?php else: foreach ($rows as $r):
            $dateCol = $tab === 'webinar' ? $r['registered_at'] : $r['enrolled_at'];
            $regNo = $tab === 'webinar' ? $r['registration_no'] : $r['enrollment_no'];
            $entity = $tab === 'webinar' ? 'webinar_registration' : 'bootcamp_enrollment';
            $blocked = adminRecordIsBlocked($r);
          ?>
          <tr<?= renderAdminRecordRowAttrs($entity, (int) $r['id'], $blocked) ?>>
            <td><?= date('d M Y', strtotime($dateCol)) ?></td>
            <td><?= htmlspecialchars($regNo) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($r['email']) ?></span></td>
            <td><?= htmlspecialchars($r['item_title']) ?></td>
            <td><span class="badge-status badge-<?= ($r['payment_status'] ?? '') === 'paid' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($r['payment_status']) ?></span></td>
            <?php if ($tab === 'webinar'): ?>
            <td>
              <?php if (!empty($r['attended'])): ?>
              <span class="badge-status badge-paid">Attended</span>
              <?php else: ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Mark attended and credit <?= (int) NXL_WEBINAR_REWARD ?> NxL?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                <input type="hidden" name="mark_webinar_attended" value="1">
                <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="btn-sm-cyber btn-view" style="font-size:.72rem">Mark attended</button>
              </form>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td><?php renderAdminRecordActions($entity, (int) $r['id'], $blocked); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
