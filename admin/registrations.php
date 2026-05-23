<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$tab = ($_GET['tab'] ?? 'webinar') === 'bootcamp' ? 'bootcamp' : 'webinar';

$webinarRegs = adminDb(
    fn () => db()->fetchAll(
        "SELECT wr.*, u.full_name, u.email, w.title AS item_title
         FROM webinar_registrations wr
         JOIN users u ON u.id = wr.user_id
         JOIN webinars w ON w.id = wr.webinar_id
         WHERE " . adminSqlActive('wr') . "
         ORDER BY wr.registered_at DESC LIMIT 150"
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
         ORDER BY be.enrolled_at DESC LIMIT 150"
    ),
    []
);

renderAdminPageStart('Registrations', 'registrations', 'fa-ticket-alt');
?>

<div class="section-card">
  <div class="section-card-header">Course registrations</div>
  <div class="section-card-body">
    <div class="filter-bar">
      <a href="<?= url('admin/registrations.php?tab=webinar') ?>" class="<?= $tab === 'webinar' ? 'active' : '' ?>">Webinars (<?= count($webinarRegs) ?>)</a>
      <a href="<?= url('admin/registrations.php?tab=bootcamp') ?>" class="<?= $tab === 'bootcamp' ? 'active' : '' ?>">Bootcamps (<?= count($bootcampRegs) ?>)</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Date</th><th>Reg. No</th><th>User Registration &amp; Trainee</th><th><?= $tab === 'webinar' ? 'Webinar' : 'Bootcamp' ?></th><th>Payment</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php
          $rows = $tab === 'webinar' ? $webinarRegs : $bootcampRegs;
          if (!$rows): ?>
          <tr><td colspan="6" style="color:var(--cyber-muted)">No registrations yet.</td></tr>
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
            <td><?php renderAdminRecordActions($entity, (int) $r['id'], $blocked); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
