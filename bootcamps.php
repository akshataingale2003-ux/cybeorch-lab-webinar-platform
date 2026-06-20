<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/public-catalog.php';
require_once __DIR__ . '/includes/webinar-register-helpers.php';
require_once __DIR__ . '/includes/program-pricing.php';

startSession();

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug !== '') {
    if (!isCybeorchProgramPathSlug($slug)) {
        $bootcamp = publicFetchBootcampBySlug($slug);
        if ($bootcamp && !isCybeorchProgramPathSlug((string) ($bootcamp['slug'] ?? ''))) {
            header('Location: ' . bootcampSecurePaymentUrl($bootcamp));
            exit;
        }
    }
    header('Location: ' . url('bootcamps.php'));
    exit;
}

/** Catalog bootcamps only — Program Path tracks render in the funnel above, not as duplicate cards. */
$bootcamps = publicFetchDisplayBootcamps();
$pageTitle = 'Bootcamps';
$navActive = 'bootcamps';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LABS</title>
<?php renderSiteFavicon(); ?>
<meta name="description" content="Cybeorch bootcamps — intensive hands-on programs with live labs, mentorship, and certificates.">
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
        <h2>CYBEORCH LABS Program Path</h2>
        <p>Structured progression — entry funnel, mid-level conversions, and premium industry labs.</p>
      </div>
      <div class="funnel-tiers">
        <?php foreach (cybeorchProgramPathFunnelTiers() as $tier):
            $cfg = cybeorchProgramPathCatalog()[$tier['slug']];
            $row = publicFetchBootcampBySlug($tier['slug']);
            $priceRow = bootcampRowWithProgramDefaults(is_array($row) ? $row : ['slug' => $tier['slug']]);
        ?>
        <div class="funnel-tier funnel-tier--<?= htmlspecialchars($tier['tier']) ?>">
          <span class="funnel-tier-label"><?= htmlspecialchars($tier['tier_label']) ?></span>
          <h3><?= htmlspecialchars((string) ($priceRow['title'] ?? $cfg['title'])) ?></h3>
          <div class="funnel-tier-price"><?php renderBootcampDualPrice($priceRow, 'funnel-tier-price-inner'); ?></div>
          <p class="funnel-tier-duration"><i class="fa-solid fa-clock me-1" aria-hidden="true"></i><?= htmlspecialchars(bootcampDurationDisplay($priceRow)) ?></p>
          <p class="funnel-tier-desc"><?= htmlspecialchars((string) $cfg['description']) ?></p>
          <a href="<?= htmlspecialchars(bootcampProgramPathPaymentUrl($tier['slug'])) ?>" class="funnel-tier-cta"><?= htmlspecialchars($tier['cta']) ?></a>
        </div>
        <?php endforeach; ?>
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
        $b = bootcampRowWithProgramDefaults($b);
        $seatsLeft = max(0, (int) $b['total_seats'] - bootcampEnrolledCount($b));
        $priceDecimals = bootcampPriceDecimals($b);
      ?>
      <div class="col-md-6">
        <div class="bootcamp-card">
          <div class="bootcamp-header">
            <div class="bootcamp-price">
              <div class="price-current"><?php renderBootcampDualPrice($b); ?></div>
            </div>
            <?php if (!empty($b['category'])): ?>
            <span class="webinar-badge badge-free"><?= htmlspecialchars($b['category']) ?></span>
            <?php endif; ?>
            <h3 style="font-family:'Rajdhani',sans-serif;font-size:1.4rem;font-weight:600;color:#fff;margin-top:.5rem">
              <?= htmlspecialchars($b['title']) ?>
            </h3>
            <div style="color:rgba(255,255,255,0.65);font-size:.85rem;margin-top:.5rem">
              <i class="fa-solid fa-calendar-days me-1"></i><?= date('d M', strtotime($b['start_date'])) ?> &ndash; <?= date('d M Y', strtotime($b['end_date'])) ?>
              <span class="ms-2"><i class="fa-solid fa-clock me-1"></i><?= htmlspecialchars(bootcampDurationDisplay($b)) ?></span>
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
            <?php renderBootcampCardRegisterCta($b, 'w-100 text-center', 'font-size:0.9rem; padding:0.65rem'); ?>
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
