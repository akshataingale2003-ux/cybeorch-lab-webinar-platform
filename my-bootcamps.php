<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/student-layout.php';

startSession();
requireLogin();

$ctx = studentContext();
$bootcamps = db()->fetchAll("SELECT * FROM bootcamps WHERE status = 'open' ORDER BY start_date ASC");

renderStudentHead('Bootcamps');
renderStudentSidebar('bootcamps', $ctx);
?>
<main class="main">
  <div class="topbar">
    <div class="page-title"><i class="fas fa-graduation-cap me-2" style="color:var(--cyber-accent)"></i>Bootcamps</div>
  </div>
  <div class="content">
    <?php if (empty($bootcamps)): ?>
    <div class="card-panel text-center" style="color:var(--cyber-muted)">No open bootcamps at the moment.</div>
    <?php else: foreach ($bootcamps as $b): ?>
    <div class="card-panel">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h3 style="font-family:'Rajdhani',sans-serif;margin-bottom:.35rem"><?= htmlspecialchars($b['title']) ?></h3>
          <p style="color:var(--cyber-muted);font-size:.88rem;margin:0"><?= htmlspecialchars($b['short_desc'] ?? '') ?></p>
          <p style="font-size:.82rem;color:var(--cyber-muted);margin-top:.5rem">
            <?= date('d M', strtotime($b['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($b['end_date'])) ?>
            &middot; <?= (int) $b['duration_weeks'] ?> weeks
          </p>
        </div>
        <a href="<?= trainingDetailUrl('bootcamp', $b) ?>" class="btn-cyber">
          Enroll &ndash; <?= formatRupee((float) $b['discounted_fee']) ?>
        </a>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
