<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/projects-data.php';

startSession();

$projects = getLiveProjects();
$pageTitle = 'Live Projects';
$navActive = 'live-projects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LAB</title>
<meta name="description" content="Explore live engineering projects at CYBEORCH — cybersecurity, EdTech, and product builds with real teams.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Live <span class="accent">Projects</span></h1>
    <p>Real-world builds from CYBEORCH Studio and partner teams. Join as a contributor, intern, or client collaborator on active engineering work.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="section-title">Active <span class="accent">Builds</span></h2>
      <div class="divider"></div>
    </div>
    <div class="row g-4">
      <?php foreach ($projects as $p): ?>
      <div class="col-md-6 col-lg-4">
        <div class="project-card">
          <div class="icon-wrap"><i class="fas fa-code-branch"></i></div>
          <span class="webinar-badge <?= projectStatusBadgeClass($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span>
          <?php if (!empty($p['category'])): ?>
          <span class="webinar-badge badge-paid ms-1"><?= htmlspecialchars($p['category']) ?></span>
          <?php endif; ?>
          <h3 class="webinar-title mt-2"><?= htmlspecialchars($p['title']) ?></h3>
          <p class="webinar-desc"><?= htmlspecialchars($p['description']) ?></p>
          <div class="project-meta">
            <span><i class="fas fa-layer-group"></i><?= htmlspecialchars($p['stack']) ?></span>
            <span><i class="far fa-clock"></i><?= htmlspecialchars($p['duration']) ?></span>
            <span><i class="fas fa-users"></i><?= htmlspecialchars($p['team']) ?></span>
          </div>
          <a href="<?= url('contact.php') ?>" class="btn-primary-cyber" style="width:auto;padding:.6rem 1.25rem">
            <i class="fas fa-handshake me-2"></i>Collaborate
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-5">
      <p style="color:var(--cyber-muted);max-width:560px;margin:0 auto 1.5rem">
        Colleges and startups can propose new projects. We scope, staff, and deliver with our engineering and security teams.
      </p>
      <a href="<?= url('contact.php') ?>" class="btn-primary-cyber" style="width:auto;display:inline-block;padding:.85rem 2rem">
        Start a Project Inquiry
      </a>
    </div>
  </div>
</section>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
