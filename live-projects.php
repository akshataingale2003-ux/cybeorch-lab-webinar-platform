<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/live-projects-public.php';

/**
 * User-facing category titles (preserves slugs, IDs, and database values).
 *
 * @param array<string, mixed> $project
 */
function liveProjectsDisplayTitle(array $project): string
{
    static $bySlug = [
        'ai-and-ml'             => 'AI & ML',
        'blockchain-technology' => 'Blockchain Technology',
        'data-bases-secutity'   => 'Data Bases Security',
        'payment-gateway'       => 'Payment Gateway',
    ];
    static $byTitle = [
        'AI & ML1'              => 'AI & ML',
        'blockchain_technology' => 'Blockchain Technology',
        'data bases secutity'   => 'Data Bases Security',
        'payment gateway'       => 'Payment Gateway',
    ];

    $slug = strtolower(trim((string) ($project['slug'] ?? '')));
    if ($slug !== '' && isset($bySlug[$slug])) {
        return $bySlug[$slug];
    }

    $title = trim((string) ($project['title'] ?? ''));
    if ($title !== '' && isset($byTitle[$title])) {
        return $byTitle[$title];
    }

    return $title !== '' ? $title : 'Live Project';
}

startSession();

$slug = sanitize($_GET['slug'] ?? '');
$project = $slug !== '' ? getLiveProjectBySlug($slug) : null;

if ($slug !== '' && !$project) {
    header('Location: ' . url('live-projects.php'));
    exit;
}

$isDetail = $project !== null;
$pageTitle = $isDetail ? liveProjectsDisplayTitle($project) : 'Live Projects';
$navActive = 'live-projects';
$projects = $isDetail ? [] : getLiveProjects();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?><?= $isDetail ? ' – Live Projects' : '' ?> – CYBEORCH LABS</title>
<?php if ($isDetail): ?>
<meta name="description" content="<?= htmlspecialchars((string) ($project['short_desc'] ?? $project['description'] ?? 'Live project at CYBEORCH LABS')) ?>">
<?php else: ?>
<meta name="description" content="Explore CYBEORCH live projects — Cybersecurity, full stack development, Data Science, AI & ML, FinTech, blockchain, Web3 wallets, and NxL credits.">
<?php endif; ?>
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
<?php if ($isDetail) {
    renderLiveProjectsPageStyles();
} ?>
<style>
  .webinar-thumb-wrap{
    position:relative;
    border-radius:10px;
    overflow:hidden;
    border:1px solid rgba(0,212,255,0.22);
    background:rgba(10,22,40,0.7);
    margin-bottom:1rem;
  }
  .webinar-thumb{
    display:block;
    width:100%;
    height:140px;
    object-fit:cover;
    cursor:pointer;
    transition:transform .35s ease, filter .35s ease;
  }
  .webinar-card:hover .webinar-thumb{
    transform:scale(1.06);
    filter:saturate(1.1) contrast(1.05);
  }
  @media(min-width:576px){
    .webinar-thumb{height:150px}
  }
  @media(min-width:992px){
    .webinar-thumb{height:160px}
  }

  .webinar-lightbox{
    position:fixed;
    inset:0;
    z-index:9999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1.25rem;
    background:rgba(2,6,14,0.92);
    backdrop-filter:blur(8px);
  }
  .webinar-lightbox.is-open{
    display:flex;
    animation:wbFadeIn .18s ease-out;
  }
  .webinar-lightbox-panel{
    position:relative;
    width:min(1120px, 94vw);
    max-height:88vh;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .webinar-lightbox-img{
    max-width:100%;
    max-height:88vh;
    width:auto;
    height:auto;
    border-radius:16px;
    border:1px solid rgba(0,212,255,0.35);
    box-shadow:0 24px 80px rgba(0,0,0,0.65);
    animation:wbZoomIn .22s ease-out;
  }
  .webinar-lightbox-close{
    position:absolute;
    top:-10px;
    right:-10px;
    width:46px;
    height:46px;
    border-radius:999px;
    border:1px solid rgba(0,212,255,0.45);
    background:rgba(5,11,24,0.92);
    color:#fff;
    font-size:1.6rem;
    line-height:1;
    cursor:pointer;
    transition:all .2s ease;
  }
  .webinar-lightbox-close:hover{
    background:var(--cyber-accent);
    color:#050b18;
    transform:rotate(90deg);
  }
  @keyframes wbFadeIn{from{opacity:0}to{opacity:1}}
  @keyframes wbZoomIn{from{opacity:.35;transform:scale(.96)}to{opacity:1;transform:scale(1)}}

  .lp-detail-hero{padding:2.5rem 0 1.5rem;position:relative;z-index:1}
  .lp-detail-hero .hero-panel{border-radius:18px;border:1px solid var(--cyber-border);overflow:hidden;background:var(--cyber-card)}
  .lp-detail-hero .hero-banner{min-height:200px;padding:2rem;position:relative;display:flex;align-items:flex-end}
  .lp-detail-hero .hero-banner img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.85;display:block}
  .lp-detail-hero .hero-banner .lp-card-icon{width:64px;height:64px;font-size:1.65rem}
  .lp-detail-hero .hero-body{padding:1.75rem 2rem 2rem}
  .lp-detail-hero h1{font-family:'Rajdhani',sans-serif;font-size:clamp(2rem,4vw,2.75rem);font-weight:700;margin:.75rem 0}
  @media(max-width:575.98px){.lp-detail-hero .hero-body{padding:1.25rem}}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<?php if ($isDetail):
    $features = $project['features'] ?? [];
    $icon = liveProjectIconClasses((string) ($project['icon_class'] ?? 'fa-code-branch'));
    $theme = preg_replace('/[^a-z0-9-]/', '', (string) ($project['card_theme'] ?? 'theme-default')) ?: 'theme-default';
    $bannerUrl = liveProjectImageUrl($project);
    $fallbackUrl = lpAssetUrl('assets/images/live_projects/Cybersecurity.png');
?>
<section class="lp-detail-hero">
  <div class="container">
    <p class="mb-3"><a href="<?= url('live-projects.php') ?>" style="color:var(--cyber-accent);font-size:.9rem"><i class="fas fa-arrow-left me-1"></i>All Live Projects</a></p>
    <div class="hero-panel">
      <div class="hero-banner lp-card-media <?= htmlspecialchars($theme) ?>">
        <img
          src="<?= htmlspecialchars($bannerUrl) ?>"
          alt="<?= htmlspecialchars($pageTitle) ?> banner"
          loading="lazy"
          onerror="this.onerror=null;this.src='<?= htmlspecialchars($fallbackUrl, ENT_QUOTES, 'UTF-8') ?>';"
        >
        <div class="lp-card-icon"><i class="<?= htmlspecialchars($icon) ?>"></i></div>
      </div>
      <div class="hero-body">
        <span class="webinar-badge <?= projectStatusBadgeClass((string) ($project['status'] ?? '')) ?>"><?= htmlspecialchars((string) ($project['status'] ?? '')) ?></span>
        <?php if (!empty($project['category'])): ?>
        <span class="webinar-badge badge-paid ms-1"><?= htmlspecialchars((string) $project['category']) ?></span>
        <?php endif; ?>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p style="color:var(--cyber-muted);max-width:800px;line-height:1.75;font-size:1.05rem"><?= htmlspecialchars((string) ($project['description'] ?? '')) ?></p>
        <div class="lp-meta mt-3">
          <?php if (!empty($project['stack'])): ?><span><i class="fas fa-layer-group"></i><?= htmlspecialchars((string) $project['stack']) ?></span><?php endif; ?>
          <?php if (!empty($project['duration'])): ?><span><i class="far fa-clock"></i><?= htmlspecialchars((string) $project['duration']) ?></span><?php endif; ?>
          <?php if (!empty($project['team'])): ?><span><i class="fas fa-users"></i><?= htmlspecialchars((string) $project['team']) ?></span><?php endif; ?>
        </div>
        <?php if ($features !== []): ?>
        <h2 class="section-title mt-4 mb-2" style="font-size:1.5rem">Modules &amp; <span class="accent">Features</span></h2>
        <ul class="lp-features" style="max-width:900px">
          <?php foreach ($features as $feat): ?>
          <li><i class="<?= htmlspecialchars(liveProjectIconClasses((string) ($feat['icon'] ?? 'fa-check'))) ?>"></i><?= htmlspecialchars((string) ($feat['label'] ?? '')) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="d-flex flex-wrap justify-content-center mt-4">
          <a href="<?= url('collaborate-project.php' . (!empty($project['slug']) ? ('?slug=' . urlencode((string) $project['slug'])) : '')) ?>" class="btn-primary-cyber" style="width:auto;padding:.75rem 1.5rem"><i class="fas fa-handshake me-2"></i>Collaborate on this project</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php else: ?>

<header class="page-hero">
  <div class="container">
    <h1>Live <span class="accent">Projects</span></h1>
    <div class="divider mx-auto"></div>
    <p>Industry-aligned project categories — from cyber security and full stack development to data science, AI, and FinTech with the NxL credits ecosystem.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <?php if (empty($projects)): ?>
    <div class="empty-state">
      <i class="fas fa-folder-open d-block mb-3"></i>
      <p>No live projects are published yet. Please check back soon.</p>
    </div>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($projects as $p):
        $thumbUrl = liveProjectImageUrl($p);
        $title = liveProjectsDisplayTitle($p);
        $detailUrl = liveProjectDetailUrl($p);
      ?>
      <div class="col-md-6 col-lg-4">
        <div class="webinar-card">
          <div class="webinar-thumb-wrap">
            <img
              class="webinar-thumb"
              src="<?= htmlspecialchars($thumbUrl) ?>"
              alt="<?= htmlspecialchars($title) ?> thumbnail"
              loading="lazy"
              decoding="async"
              data-lightbox-src="<?= htmlspecialchars($thumbUrl) ?>"
              data-lightbox-title="<?= htmlspecialchars($title) ?>"
            >
          </div>
          <h3 class="webinar-title"><?= htmlspecialchars($title) ?></h3>
          <a href="<?= htmlspecialchars($detailUrl) ?>" class="btn-primary-cyber mt-3">
            <i class="fas fa-arrow-right me-1"></i>View Project
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="text-center mt-4 mt-md-5 pt-2">
      <p style="color:var(--cyber-muted);max-width:560px;margin:0 auto 1.25rem;line-height:1.7">
        Colleges, startups, and product teams can propose new tracks. We scope, staff, and deliver with CYBEORCH mentors.
      </p>
      <a href="<?= url('start-project.php') ?>" class="btn-primary-cyber" style="width:auto;display:inline-block;padding:.85rem 2rem">
        Start a Project Inquiry
      </a>
    </div>
  </div>
</section>

<?php endif; ?>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if (!$isDetail): ?>
<div class="webinar-lightbox" id="webinarLightbox" aria-hidden="true" role="dialog" aria-label="Project image preview">
  <div class="webinar-lightbox-panel" role="document">
    <button type="button" class="webinar-lightbox-close" id="webinarLightboxClose" aria-label="Close image preview">&times;</button>
    <img src="" alt="" id="webinarLightboxImage" class="webinar-lightbox-img">
  </div>
</div>

<script>
(function () {
  var lightbox = document.getElementById('webinarLightbox');
  var lightboxImage = document.getElementById('webinarLightboxImage');
  var closeBtn = document.getElementById('webinarLightboxClose');

  function openLightbox(src, title) {
    if (!lightbox || !lightboxImage || !src) return;
    lightboxImage.src = src;
    lightboxImage.alt = title || 'Project image preview';
    lightbox.classList.add('is-open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    if (!lightbox || !lightboxImage) return;
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImage.src = '';
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-lightbox-src]');
    if (trigger) {
      openLightbox(trigger.getAttribute('data-lightbox-src'), trigger.getAttribute('data-lightbox-title'));
      return;
    }
    if (event.target === lightbox) {
      closeLightbox();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeLightbox);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeLightbox();
    }
  });
})();
</script>
<?php endif; ?>
</body>
</html>
