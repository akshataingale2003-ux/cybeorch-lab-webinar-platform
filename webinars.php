<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';

startSession();

$webinars = dbTry(
    fn () => db()->fetchAll(
        "SELECT * FROM webinars WHERE status IN ('upcoming','live') ORDER BY scheduled_at ASC"
    ),
    []
);
$pageTitle = 'Webinars';
$navActive = 'webinars';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LAB</title>
<meta name="description" content="Live cybersecurity webinars on ethical hacking, web security, and more.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Live <span class="accent">Webinars</span></h1>
    <p>Expert-led sessions on ethical hacking, web security, cloud security, and the latest cyber threats.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <?php if (hasDbError()): ?>
    <div class="alert" style="background:#ff6b35;color:#050b18;padding:1rem;border-radius:8px;margin-bottom:2rem">
      <?= htmlspecialchars(dbErrorMessage()) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($webinars)): ?>
    <div class="empty-state">
      <i class="fas fa-calendar-xmark d-block mb-3"></i>
      <p>No upcoming webinars. Check back soon or <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">contact us</a>.</p>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($webinars as $w):
        $fillPct = (int) $w['max_seats'] > 0
            ? min(100, ((int) $w['registered_seats'] / (int) $w['max_seats']) * 100)
            : 0;
        $seatsLeft = max(0, (int) $w['max_seats'] - (int) $w['registered_seats']);
        $badgeClass = $w['status'] === 'live' ? 'badge-live' : (!empty($w['is_free']) ? 'badge-free' : 'badge-paid');
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="webinar-card">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="webinar-badge <?= $badgeClass ?>">
              <?php if ($w['status'] === 'live'): ?>Live Now
              <?php elseif (!empty($w['is_free'])): ?>Free
              <?php else: ?><?= formatRupee((float) $w['fee']) ?>
              <?php endif; ?>
            </span>
            <?php if (!empty($w['category'])): ?>
            <span style="font-size:.75rem;color:var(--cyber-muted)"><?= htmlspecialchars($w['category']) ?></span>
            <?php endif; ?>
          </div>
          <h3 class="webinar-title"><?= htmlspecialchars($w['title']) ?></h3>
          <p class="webinar-desc"><?= htmlspecialchars($w['short_desc'] ?? '') ?></p>
          <div class="webinar-meta">
            <span><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i><?= date('d M Y', strtotime($w['scheduled_at'])) ?></span>
            <span><i class="fa-solid fa-clock me-1" aria-hidden="true"></i><?= date('h:i A', strtotime($w['scheduled_at'])) ?></span>
            <span><i class="fa-solid fa-hourglass-half me-1" aria-hidden="true"></i><?= (int) $w['duration_mins'] ?> min</span>
          </div>
          <?php if (!empty($w['instructor'])): ?>
          <div class="webinar-instructor">
            <div class="instructor-avatar"><?= strtoupper(substr($w['instructor'], 0, 1)) ?></div>
            <span><?= htmlspecialchars($w['instructor']) ?></span>
          </div>
          <?php endif; ?>
          <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;color:var(--cyber-muted)">
            <span><?= (int) $w['registered_seats'] ?> registered</span>
            <span><?= $seatsLeft ?> seats left</span>
          </div>
          <div class="seats-bar"><div class="seats-fill" style="width:<?= $fillPct ?>%"></div></div>
          <a href="<?= trainingDetailUrl('webinar', $w) ?>" class="btn-primary-cyber mt-3">
            <?= !empty($w['is_free']) ? '<i class="fas fa-bolt me-1"></i>Register Free' : '<i class="fas fa-ticket me-1"></i>Register Now' ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
