<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$userId = $ctx['userId'];
$regs = db()->fetchAll(
    "SELECT wr.*, w.title, w.scheduled_at, w.status as webinar_status
     FROM webinar_registrations wr JOIN webinars w ON w.id = wr.webinar_id
     WHERE wr.user_id = ? ORDER BY wr.registered_at DESC",
    [$userId]
);
$boots = db()->fetchAll(
    "SELECT be.*, b.title, b.start_date, b.end_date
     FROM bootcamp_enrollments be JOIN bootcamps b ON b.id = be.bootcamp_id
     WHERE be.user_id = ? ORDER BY be.enrolled_at DESC",
    [$userId]
);

renderStudentHead('My Registrations');
renderStudentSidebar('registrations', $ctx);
?>
<main class="main">
  <?php renderPortalTopbar('My Registrations'); ?>
  <div class="content">
    <h4 style="font-family:'Rajdhani',sans-serif;margin-bottom:1rem">Webinars</h4>
    <?php if (empty($regs)): ?><div class="card-panel" style="color:var(--cyber-muted)">No webinar registrations.</div>
    <?php else: foreach ($regs as $r): ?>
    <div class="card-panel"><?= htmlspecialchars($r['title']) ?> · <?= date('d M Y', strtotime($r['scheduled_at'])) ?> · <span style="color:var(--cyber-accent)"><?= htmlspecialchars($r['payment_status']) ?></span></div>
    <?php endforeach; endif; ?>
    <h4 style="font-family:'Rajdhani',sans-serif;margin:1.5rem 0 1rem">Bootcamps</h4>
    <?php if (empty($boots)): ?><div class="card-panel" style="color:var(--cyber-muted)">No bootcamp enrollments.</div>
    <?php else: foreach ($boots as $b): ?>
    <div class="card-panel"><?= htmlspecialchars($b['title']) ?> · <?= date('d M Y', strtotime($b['start_date'])) ?> · <span style="color:var(--cyber-accent)"><?= htmlspecialchars($b['payment_status']) ?></span></div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
