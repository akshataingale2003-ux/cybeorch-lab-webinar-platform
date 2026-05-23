<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/projects-data.php';

startSession();

$products = getStudioProducts();
$pageTitle = 'Products';
$navActive = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LAB</title>
<meta name="description" content="CYBEORCH Studio products — fintech, EdTech, retail, and security platforms built for scale.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1><span class="brand-mark"><?= brandMark() ?></span> <span class="accent">Products</span></h1>
    <p>Ready-to-deploy platforms from CYBEORCH Studio — wallets, LMS, POS, marketplaces, and security tools for startups and enterprises.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="section-title">Studio <span class="accent">Portfolio</span></h2>
      <div class="divider"></div>
    </div>
    <div class="row g-4">
      <?php foreach ($products as $prod): ?>
      <div class="col-md-6 col-lg-4">
        <div class="project-card">
          <div class="icon-wrap"><i class="fas fa-cube"></i></div>
          <span class="webinar-badge <?= projectStatusBadgeClass($prod['status']) ?>"><?= htmlspecialchars($prod['status']) ?></span>
          <?php if (!empty($prod['category'])): ?>
          <span class="webinar-badge badge-paid ms-1"><?= htmlspecialchars($prod['category']) ?></span>
          <?php endif; ?>
          <h3 class="webinar-title mt-2"><?= htmlspecialchars($prod['title']) ?></h3>
          <p class="webinar-desc"><?= htmlspecialchars($prod['description']) ?></p>
          <?php if (!empty($prod['features'])): ?>
          <ul class="feature-list">
            <?php foreach ($prod['features'] as $feature): ?>
            <li><?= htmlspecialchars($feature) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a href="<?= url('contact.php') ?>" class="btn-primary-cyber" style="width:auto;padding:.6rem 1.25rem">
            <i class="fas fa-paper-plane me-2"></i>Request Demo
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5">
      <p style="color:var(--cyber-muted);max-width:560px;margin:0 auto 1.5rem">
        Need a custom build? We white-label, integrate APIs, and deploy on your infrastructure.
      </p>
      <a href="<?= url('services.php') ?>" class="btn-primary-cyber me-2" style="width:auto;display:inline-block;padding:.85rem 2rem">
        View Services
      </a>
      <a href="<?= url('contact.php') ?>" class="btn-primary-cyber" style="width:auto;display:inline-block;padding:.85rem 2rem;background:transparent;border:1px solid var(--cyber-accent);color:var(--cyber-accent)">
        Contact Sales
      </a>
    </div>
  </div>
</section>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
