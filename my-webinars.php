<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/webinar-register-helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$webinars = db()->fetchAll(
    "SELECT * FROM webinars WHERE status IN ('upcoming','live') ORDER BY scheduled_at ASC"
);

renderStudentHead('Webinars');
renderStudentSidebar('webinars', $ctx);
?>
<main class="main">
  <div class="topbar"><div class="page-title"><i class="fas fa-video me-2" style="color:var(--cyber-accent)"></i>Webinars</div></div>
  <div class="content">
    <p style="color:var(--cyber-muted);font-size:.88rem;margin-bottom:1rem">
      Register from your dashboard. For the full catalog view, visit
      <a href="<?= url('webinars.php') ?>" style="color:var(--cyber-accent)">public webinars</a>.
    </p>
    <?php if (empty($webinars)): ?>
    <div class="card-panel text-center" style="color:var(--cyber-muted)">No upcoming webinars right now.</div>
    <?php else: foreach ($webinars as $w): ?>
    <div class="card-panel">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h3 style="font-family:'Rajdhani',sans-serif;margin-bottom:.35rem"><?= htmlspecialchars($w['title']) ?></h3>
          <p style="color:var(--cyber-muted);font-size:.88rem;margin:0"><?= htmlspecialchars($w['short_desc'] ?? '') ?></p>
          <p style="font-size:.82rem;color:var(--cyber-muted);margin-top:.5rem">
            <i class="fa-solid fa-calendar-days me-1"></i><?= date('d M Y, h:i A', strtotime($w['scheduled_at'])) ?>
            &middot; <?= (int) $w['duration_mins'] ?> min
          </p>
        </div>
        <div class="text-end">
          <?php renderWebinarFeeNotice($w); ?>
          <a href="<?= trainingDetailUrl('webinar', $w) ?>" class="btn-cyber">
            <?= !empty($w['is_free']) ? 'Register Free' : 'Register Now' ?>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php
renderStudentLayoutEnd();
