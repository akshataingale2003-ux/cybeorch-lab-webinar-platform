<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';

startSession();

$bootcamps = dbTry(
    fn () => db()->fetchAll("SELECT * FROM bootcamps WHERE status = 'open' ORDER BY start_date ASC"),
    []
);
$pageTitle = 'Bootcamps';
$navActive = 'bootcamps';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LAB</title>
<?php renderSiteFavicon(); ?>
<meta name="description" content="Intensive cybersecurity bootcamps with hands-on labs, mentorship, and certificates.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Cybeorch <span class="accent">Bootcamps</span></h1>
    <p>Hands-on intensive programs with live projects, expert mentorship, and industry-recognized certificates.</p>

    <div class="program-funnel-card">
      <div class="funnel-card-intro">
        <h2>CYBEORCH LAB Program Path</h2>
        <p>Structured progression — entry funnel, mid-level conversions, and premium industry labs.</p>
      </div>
      <div class="funnel-tiers">
        <div class="funnel-tier funnel-tier--entry">
          <span class="funnel-tier-label">Entry funnel</span>
          <h3>30-Day Bootcamp</h3>
          <div class="funnel-tier-price"><?= formatRupee(14999) ?></div>
          <p class="funnel-tier-desc">Fast-track foundation with live labs, mentorship, and certificate — ideal first step into cybersecurity.</p>
          <a href="<?= url('enquire-enroll.php?program=30-day-bootcamp') ?>" class="funnel-tier-cta">Enquire &amp; Enroll</a>
        </div>
        <div class="funnel-tier funnel-tier--mid">
          <span class="funnel-tier-label">Mid-level conversions</span>
          <h3>90-Day Industry Bootcamp</h3>
          <div class="funnel-tier-price"><?= formatRupee(24999) ?></div>
          <p class="funnel-tier-desc">Deeper specialization, capstone projects, and career placement support for graduates ready to level up.</p>
          <a href="<?= url('enquire-enroll.php?program=90-day-bootcamp') ?>" class="funnel-tier-cta">Upgrade Your Track</a>
        </div>
        <div class="funnel-tier funnel-tier--premium">
          <span class="funnel-tier-label">Premium revenue model</span>
          <h3>Premium Industry Lab Program</h3>
          <div class="funnel-tier-price"><?= formatRupee(35000) ?>+</div>
          <p class="funnel-tier-desc">Elite lab access, enterprise mentors, and real-world industry projects — built for serious professionals and teams.</p>
          <a href="<?= url('enquire-enroll.php?program=premium-industry-lab') ?>" class="funnel-tier-cta">Enquire &amp; Enroll</a>
        </div>
      </div>
    </div>
  </div>
</header>



<section class="section pt-0">
  <div class="container">
    <?php if (hasDbError()): ?>
    <div class="alert" style="background:#ff6b35;color:#050b18;padding:1rem;border-radius:8px;margin-bottom:2rem">
      <?= htmlspecialchars(dbErrorMessage()) ?>
    </div>
    <?php endif; ?>

    <?php if (empty($bootcamps)): ?>
    <div class="empty-state">
      <i class="fas fa-graduation-cap d-block mb-3"></i>
      <p>No open bootcamps right now. Check back soon or <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">contact us</a>.</p>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($bootcamps as $b):
        $seatsLeft = max(0, (int) $b['total_seats'] - (int) $b['enrolled_seats']);
      ?>
      <div class="col-md-6">
        <div class="bootcamp-card">
          <div class="bootcamp-header">
            <div class="bootcamp-price">
              <?php if ((float) $b['original_fee'] > (float) $b['discounted_fee']): ?>
              <div class="price-original"><?= formatRupee((float) $b['original_fee']) ?></div>
              <?php endif; ?>
              <div class="price-current"><?= formatRupee((float) $b['discounted_fee']) ?></div>
            </div>
            <?php if (!empty($b['category'])): ?>
            <span class="webinar-badge badge-free"><?= htmlspecialchars($b['category']) ?></span>
            <?php endif; ?>
            <h3 style="font-family:'Rajdhani',sans-serif;font-size:1.4rem;font-weight:600;color:#fff;margin-top:.5rem">
              <?= htmlspecialchars($b['title']) ?>
            </h3>
            <div style="color:rgba(255,255,255,0.65);font-size:.85rem;margin-top:.5rem">
              <i class="fa-solid fa-calendar-days me-1"></i><?= date('d M', strtotime($b['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($b['end_date'])) ?>
              <span class="ms-2"><i class="fa-solid fa-clock me-1"></i><?= (int) $b['duration_weeks'] ?> weeks</span>
            </div>
          </div>
          <div class="bootcamp-body">
            <p class="webinar-desc"><?= htmlspecialchars($b['short_desc'] ?? '') ?></p>
            <ul class="feature-list">
              <li><i class="fa-solid fa-flask" aria-hidden="true"></i> Hands-on lab exercises &amp; CTF challenges</li>
              <li><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Industry expert mentorship</li>
              <?php if (!empty($b['certificate'])): ?><li><i class="fa-solid fa-certificate" aria-hidden="true"></i> Certificate of completion</li><?php endif; ?>
              <li><i class="fa-solid fa-users" aria-hidden="true"></i> <?= (int) $seatsLeft ?> seats remaining</li>
              <li><i class="fa-solid fa-coins" aria-hidden="true"></i> Earn <?= (int) NXL_BOOTCAMP_REWARD ?> NxL tokens on enrollment</li>
            </ul>
            <a href="<?= trainingDetailUrl('bootcamp', $b) ?>" class="btn-primary-cyber">
              <i class="fas fa-rocket me-2"></i>Enroll Now &ndash; <?= formatRupee((float) $b['discounted_fee']) ?>
            </a>
          </div>
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
