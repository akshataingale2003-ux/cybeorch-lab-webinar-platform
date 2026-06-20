<?php
require_once __DIR__ . '/includes/public-enquiry.php';
require_once __DIR__ . '/includes/public-catalog.php';

startSession();

$navActive = 'freelance-projects';
$pageTitle = 'Apply as Freelancer';
$subject = 'Freelance Project Application';

$slug = trim((string) ($_GET['project'] ?? $_POST['project'] ?? ''));
$project = $slug !== '' ? publicFetchFreelanceProjectBySlug($slug) : null;

handlePublicEnquiryPost('apply-freelance.php' . ($slug !== '' ? '?project=' . urlencode($slug) : ''), $subject, [
    'Project slug'  => $slug,
    'Project title' => (string) ($project['title'] ?? ''),
    'Category'      => (string) ($project['category'] ?? ''),
    'Budget'        => (string) ($project['budget'] ?? ''),
    'Duration'      => (string) ($project['duration'] ?? ''),
    'Mode'          => (string) ($project['mode'] ?? ''),
    'Portfolio'     => trim((string) ($_POST['portfolio_url'] ?? '')),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH</title>
<meta name="description" content="Apply for freelance projects at CYBEORCH LABS. Share your portfolio, skills, and availability.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Apply as <span class="accent">Freelancer</span></h1>
    <p>Submit your application and our team will reach out with the next steps.</p>
  </div>
</header>

<main class="section pt-0">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">Selected <span class="accent">Project</span></h2>

        <div class="info-card">
          <?php if ($project): ?>
            <div style="font-size:1.05rem;font-weight:700;margin-bottom:.35rem"><?= htmlspecialchars((string) $project['title']) ?></div>
            <div style="color:var(--cyber-muted);font-size:.9rem;line-height:1.7"><?= htmlspecialchars((string) ($project['short_desc'] ?: $project['description'] ?? '')) ?></div>
            <div style="margin-top:1rem;color:var(--cyber-muted);font-size:.88rem;display:flex;flex-direction:column;gap:.35rem">
              <?php if (!empty($project['category'])): ?><div><strong>Category:</strong> <?= htmlspecialchars((string) $project['category']) ?></div><?php endif; ?>
              <?php if (!empty($project['budget'])): ?><div><strong>Budget:</strong> <?= htmlspecialchars((string) $project['budget']) ?></div><?php endif; ?>
              <?php if (!empty($project['duration'])): ?><div><strong>Duration:</strong> <?= htmlspecialchars((string) $project['duration']) ?></div><?php endif; ?>
              <?php if (!empty($project['mode'])): ?><div><strong>Mode:</strong> <?= htmlspecialchars((string) $project['mode']) ?></div><?php endif; ?>
            </div>
          <?php else: ?>
            <div style="color:var(--cyber-muted);line-height:1.7">
              No project selected. You can still apply, or go back to the list and choose a project.
              <div style="margin-top:1rem">
                <a href="<?= url('freelance-projects.php') ?>" style="color:var(--cyber-accent)"><i class="fas fa-arrow-left me-1"></i>Back to freelance projects</a>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <h2 class="section-title" style="font-size:1.5rem">Application <span class="accent">Form</span></h2>
          <?= showFlash() ?>

          <form method="POST" action="<?= url('apply-freelance.php' . ($slug !== '' ? '?project=' . urlencode($slug) : '')) ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="project" value="<?= htmlspecialchars($slug) ?>">
            <input type="hidden" name="require_phone" value="1">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Your Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="John Doe" value="<?= postVal('name') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Gmail Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="yourname@gmail.com" value="<?= postVal('email') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" required placeholder="+91 XXXXX XXXXX" value="<?= postVal('phone') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Portfolio URL</label>
                <input type="url" name="portfolio_url" class="form-control" placeholder="https://..." value="<?= postVal('portfolio_url') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Message *</label>
                <textarea name="message" class="form-control" rows="6" required placeholder="Briefly share your skills, experience, and availability."><?= postVal('message') ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-cyber w-100">
                  <i class="fas fa-paper-plane me-2"></i>Submit Application
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

