<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/public-catalog.php';
require_once __DIR__ . '/includes/assignments-data.php';

startSession();

$slug = trim((string) ($_GET['slug'] ?? ''));
$detailProject = null;
if ($slug !== '') {
    $detailProject = publicFetchFreelanceProjectBySlug($slug);
    if (!$detailProject) {
        header('Location: ' . url('freelance-projects.php'));
        exit;
    }
}

$projects = $detailProject ? [] : publicFetchFreelanceProjects();
$navActive = 'freelance-projects';
$isDetail = $detailProject !== null;
$pageTitle = $isDetail
    ? (string) ($detailProject['title'] ?? 'Freelance Project')
    : 'Freelance Projects';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?><?= $isDetail ? ' – Freelance Projects' : '' ?> – CYBEORCH LAB</title>
<?php if (!$isDetail): ?>
<meta name="description" content="Browse open freelance projects at CYBEORCH LAB — remote software, design, cybersecurity, and Web3 engagements.">
<?php endif; ?>
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
<style>
.freelance-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;height:100%;transition:all .3s;display:flex;flex-direction:column}
.freelance-card:hover{border-color:var(--cyber-accent);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,212,255,0.12)}
.freelance-card .card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.75rem;flex-wrap:wrap}
.freelance-meta{display:flex;flex-wrap:wrap;gap:.75rem 1rem;font-size:.8rem;color:var(--cyber-muted);margin:.75rem 0 1rem}
.freelance-meta span{display:inline-flex;align-items:center;gap:.35rem}
.freelance-budget{font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:700;color:var(--cyber-green);margin-bottom:.5rem}
<?php if ($isDetail): ?>
.fp-hero{padding:2.5rem 0 1.5rem;position:relative;z-index:1}
.fp-panel{border-radius:18px;border:1px solid var(--cyber-border);overflow:hidden;background:var(--cyber-card)}
.fp-banner{min-height:220px;padding:2rem;position:relative;display:flex;align-items:flex-end}
.fp-banner img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.65}
.fp-body{padding:1.75rem 2rem 2rem}
@media(max-width:575.98px){.fp-body{padding:1.25rem}.fp-banner{min-height:200px;padding:1.25rem}}
.fp-meta{display:flex;flex-wrap:wrap;gap:.75rem 1.25rem;color:var(--cyber-muted);font-size:.9rem;margin:1rem 0}
.fp-meta span{display:inline-flex;align-items:center;gap:.45rem}
.fp-budget{font-family:'Rajdhani',sans-serif;font-size:1.25rem;font-weight:800;color:var(--cyber-green);margin:.35rem 0 .75rem}
<?php endif; ?>
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<?php if ($isDetail):
    $p = $detailProject;
    $thumbUrl = freelanceProjectImageUrl($p);
?>
<section class="fp-hero">
  <div class="container">
    <p class="mb-3"><a href="<?= url('freelance-projects.php') ?>" style="color:var(--cyber-accent);font-size:.9rem"><i class="fas fa-arrow-left me-1"></i>All Freelance Projects</a></p>
    <div class="fp-panel">
      <div class="fp-banner">
        <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= htmlspecialchars($pageTitle) ?> banner" loading="lazy">
      </div>
      <div class="fp-body">
        <div class="d-flex flex-wrap gap-2 mb-2">
          <span class="webinar-badge <?= assignmentStatusBadgeClass((string) ($p['status'] ?? 'Open')) ?>"><?= htmlspecialchars((string) ($p['status'] ?? 'Open')) ?></span>
          <?php if (!empty($p['category'])): ?>
          <span class="webinar-badge badge-paid"><?= htmlspecialchars((string) $p['category']) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="section-title" style="font-size:2rem;margin-bottom:.25rem"><?= htmlspecialchars($pageTitle) ?></h1>
        <?php if (!empty($p['budget'])): ?>
        <div class="fp-budget"><?= htmlspecialchars((string) $p['budget']) ?></div>
        <?php endif; ?>
        <p style="color:var(--cyber-muted);max-width:900px;line-height:1.8;font-size:1.02rem">
          <?= htmlspecialchars((string) ($p['description'] ?? '')) ?>
        </p>
        <div class="fp-meta">
          <?php if (!empty($p['client_name'])): ?><span><i class="fas fa-building"></i><?= htmlspecialchars((string) $p['client_name']) ?></span><?php endif; ?>
          <?php if (!empty($p['duration'])): ?><span><i class="far fa-clock"></i><?= htmlspecialchars((string) $p['duration']) ?></span><?php endif; ?>
          <?php if (!empty($p['mode'])): ?><span><i class="fas fa-location-dot"></i><?= htmlspecialchars((string) $p['mode']) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($p['skills'])): ?>
        <div style="margin-top:.5rem;color:var(--cyber-text)">
          <strong style="color:var(--cyber-muted)">Skills:</strong> <?= htmlspecialchars((string) $p['skills']) ?>
        </div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2 mt-4">
          <a href="<?= freelanceProjectApplyUrl($p) ?>" class="btn-primary-cyber" style="width:auto;padding:.75rem 1.5rem">
            <i class="fas fa-paper-plane me-2"></i>Apply as Freelancer
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php else: ?>

<header class="page-hero">
  <div class="container">
    <h1>Freelance <span class="accent">Projects</span></h1>
    <p>Scoped client and internal engagements for registered CYBEORCH freelancers — apply with your portfolio and availability.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="section-title">Open <span class="accent">Engagements</span></h2>
      <div class="divider"></div>
    </div>

    <?php if (hasDbError()): ?>
    <div class="alert" style="background:#ff6b35;color:#050b18;padding:1rem;border-radius:8px;margin-bottom:2rem">
      <?= htmlspecialchars(dbErrorMessage()) ?>
    </div>
    <?php endif; ?>

    <div class="row g-4">
      <?php foreach ($projects as $p):
        $title = (string) ($p['title'] ?? 'Freelance Project');
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="webinar-card freelance-card">
          <div class="card-top">
            <span class="webinar-badge <?= assignmentStatusBadgeClass($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span>
            <?php if (!empty($p['category'])): ?>
            <span class="webinar-badge badge-paid" style="font-size:.65rem"><?= htmlspecialchars($p['category']) ?></span>
            <?php endif; ?>
          </div>
          <h3 class="webinar-title"><?= htmlspecialchars($title) ?></h3>
          <?php if (!empty($p['budget'])): ?>
          <div class="freelance-budget"><?= htmlspecialchars($p['budget']) ?></div>
          <?php endif; ?>
          <p class="webinar-desc"><?= htmlspecialchars((string) ($p['short_desc'] ?: $p['description'] ?? '')) ?></p>
          <?php if (!empty($p['skills'])): ?>
          <div class="skills" style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:.5rem"><strong>Skills:</strong> <?= htmlspecialchars($p['skills']) ?></div>
          <?php endif; ?>
          <div class="freelance-meta">
            <?php if (!empty($p['client_name'])): ?><span><i class="fas fa-building"></i><?= htmlspecialchars($p['client_name']) ?></span><?php endif; ?>
            <?php if (!empty($p['duration'])): ?><span><i class="far fa-clock"></i><?= htmlspecialchars($p['duration']) ?></span><?php endif; ?>
            <?php if (!empty($p['mode'])): ?><span><i class="fas fa-location-dot"></i><?= htmlspecialchars($p['mode']) ?></span><?php endif; ?>
          </div>
          <div class="card-actions mt-auto">
            <a href="<?= freelanceProjectApplyUrl($p) ?>" class="btn-primary-cyber" style="width:auto;padding:.65rem 1.15rem">
              <i class="fas fa-paper-plane me-2"></i>Apply
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($projects === []): ?>
    <p class="text-center" style="color:var(--cyber-muted)">No freelance projects are open right now. Check back soon.</p>
    <?php endif; ?>
  </div>
</section>

<?php endif; ?>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
