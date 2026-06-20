<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/company-public.php';

startSession();
$navActive = 'corporate-services';
$services = publicFetchCorporateServices();
$pageTitle = 'Corporate Services';
$pageDescription = 'Enterprise software, mobile, SaaS, API, AI, cybersecurity, and cloud services from CYBEORCH LABS.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LABS</title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<?php renderPublicPageHead(); ?>
<?php renderCompanyPageStyles(); ?>
</head>
<body>
<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="company-hero">
  <div class="container">
    <h1>Corporate <span class="accent">Services</span></h1>
    <p>End-to-end technology services for startups and enterprises — from product engineering and APIs to AI, cybersecurity, and cloud operations.</p>
  </div>
</header>

<main class="company-section pt-0">
  <div class="container">
    <div class="row g-4">
      <?php foreach ($services as $i => $svc): ?>
      <div class="col-md-6 col-lg-4 fade-in">
        <article class="company-card">
          <i class="fas <?= htmlspecialchars((string) ($svc['icon_class'] ?: 'fa-code')) ?>" aria-hidden="true"></i>
          <?php if (!empty($svc['image_path'])): ?>
          <img src="<?= htmlspecialchars(companyPublicImageUrl((string) $svc['image_path'])) ?>" alt="" style="width:100%;border-radius:8px;margin-bottom:1rem" loading="lazy">
          <?php endif; ?>
          <h3><?= htmlspecialchars((string) $svc['title']) ?></h3>
          <p><?= nl2br(htmlspecialchars((string) ($svc['description'] ?: $svc['short_desc'] ?: ''))) ?></p>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($services === []): ?>
    <p class="text-center" style="color:var(--cyber-muted)">Corporate services will appear here once added in the admin panel.</p>
    <?php endif; ?>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); renderSiteScripts(false); renderCompanyPageEndScript(); ?>
</body>
</html>
