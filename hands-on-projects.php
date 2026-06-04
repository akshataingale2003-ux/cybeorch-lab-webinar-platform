<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/assignments-data.php';

startSession();

$assignments = getAssignments();
$pageTitle = 'Hands-on Projects';
$navActive = 'assignments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LAB</title>
<meta name="description" content="Browse open internships, projects, and freelance hands-on projects at CYBEORCH LAB — software, cybersecurity, AI, and design roles.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
<style>
.assignment-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;height:100%;transition:all .3s;display:flex;flex-direction:column}
.assignment-card:hover{border-color:var(--cyber-accent);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,212,255,0.12)}
.assignment-card .card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap}
.assignment-card .skills{font-size:.82rem;color:var(--cyber-muted);margin:.75rem 0 1rem;padding:.65rem .85rem;background:rgba(0,0,0,0.2);border-radius:6px;border:1px solid var(--cyber-border)}
.assignment-card .skills strong{color:var(--cyber-text);font-weight:500}
.assignment-card .card-actions{margin-top:auto;padding-top:.5rem}
.assignment-meta{display:flex;flex-wrap:wrap;gap:.75rem;margin:.5rem 0;font-size:.82rem;color:var(--cyber-muted)}
.assignment-meta span{display:inline-flex;align-items:center;gap:.35rem}
.cta-strip{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:2rem;text-align:center;margin-top:2rem}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Open <span class="accent">Hands-on Projects</span></h1>
    <p>Internships, live projects, and freelance opportunities across software development, cybersecurity, AI, design, and cloud — work with real teams on industry hands-on projects.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="section-title">Available <span class="accent">Roles</span></h2>
      <div class="divider"></div>
    </div>
    <div class="row g-4">
      <?php foreach ($assignments as $a): ?>
      <div class="col-md-6 col-lg-4">
        <div class="assignment-card">
          <div class="card-top">
            <span class="webinar-badge <?= assignmentTypeBadgeClass($a['type']) ?>"><?= htmlspecialchars($a['type']) ?></span>
            <span class="webinar-badge <?= assignmentStatusBadgeClass($a['status']) ?>"><?= htmlspecialchars($a['status']) ?></span>
          </div>
          <?php if (!empty($a['category'])): ?>
          <span class="webinar-badge badge-paid" style="font-size:.65rem"><?= htmlspecialchars($a['category']) ?></span>
          <?php endif; ?>
          <h3 class="webinar-title mt-2"><?= htmlspecialchars($a['title']) ?></h3>
          <p class="webinar-desc"><?= htmlspecialchars($a['description']) ?></p>
          <div class="skills"><strong>Skills:</strong> <?= htmlspecialchars($a['skills']) ?></div>
          <div class="assignment-meta">
            <span><i class="far fa-clock"></i><?= htmlspecialchars($a['duration']) ?></span>
            <span><i class="fas fa-location-dot"></i><?= htmlspecialchars($a['mode']) ?></span>
          </div>
          <div class="card-actions">
            <a href="<?= assignmentRegisterUrl($a) ?>" class="btn-primary-cyber" style="width:auto;padding:.65rem 1.25rem">
              <i class="fas fa-paper-plane me-2"></i>Apply Now
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

   <!-- <div class="cta-strip">
      <h3 class="section-title mb-2" style="font-size:1.5rem">Not sure which role fits?</h3>
      <p style="color:var(--cyber-muted);max-width:560px;margin:0 auto 1.25rem;line-height:1.7">
        User registrations & trainees and interns can create a free account. Experienced contributors can register as a freelancer for project-based work.
      </p>
      <a href="<?= url('index.php?register_required=1') ?>" class="btn-primary-cyber" style="width:auto;display:inline-block;padding:.75rem 1.5rem;margin:.35rem">
        <i class="fas fa-user-plus me-2"></i>Create Account
      </a>
      <a href="<?= url('register-freelancer.php') ?>" class="btn-primary-cyber" style="width:auto;display:inline-block;padding:.75rem 1.5rem;margin:.35rem;background:transparent;border:1px solid var(--cyber-border);color:var(--cyber-accent)">
        <i class="fas fa-user-tie me-2"></i>Register as Freelancer
      </a>
    </div> -->
  </div>
</section>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
