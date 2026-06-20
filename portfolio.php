<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/company-public.php';

startSession();
$navActive = 'portfolio';
$items = publicFetchPortfolioItems();
$pageTitle = 'Portfolio';
$pageDescription = 'Explore CYBEORCH product and platform portfolio including Nexora, NXL Credit Engine, Energeia, and more.';
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
    <h1>Our <span class="accent">Portfolio</span></h1>
    <p>Platforms and products engineered by CYBEORCH — built for scale, security, and real-world business outcomes.</p>
  </div>
</header>

<main class="company-section pt-0">
  <div class="container">
    <div class="row g-4">
      <?php foreach ($items as $item): ?>
      <div class="col-md-6 col-lg-4 fade-in">
        <article class="company-card">
          <?php if (!empty($item['image_path'])): ?>
          <img src="<?= htmlspecialchars(companyPublicImageUrl((string) $item['image_path'])) ?>" alt="<?= htmlspecialchars((string) $item['title']) ?>" style="width:100%;border-radius:8px;margin-bottom:1rem" loading="lazy">
          <?php else: ?>
          <i class="fas fa-layer-group" aria-hidden="true"></i>
          <?php endif; ?>
          <h3><?= htmlspecialchars((string) $item['title']) ?></h3>
          <p><?= nl2br(htmlspecialchars((string) ($item['description'] ?: $item['short_desc'] ?: ''))) ?></p>
          <?php if (!empty($item['tech_stack'])): ?>
          <p style="margin-top:.75rem;font-size:.82rem;color:var(--cyber-accent)"><strong>Stack:</strong> <?= htmlspecialchars((string) $item['tech_stack']) ?></p>
          <?php endif; ?>
        </article>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($items === []): ?>
    <p class="text-center" style="color:var(--cyber-muted)">Portfolio items will appear here once added in the admin panel.</p>
    <?php endif; ?>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); renderSiteScripts(false); renderCompanyPageEndScript(); ?>
</body>
</html>
