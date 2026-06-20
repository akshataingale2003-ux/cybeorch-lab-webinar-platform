<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/public-catalog.php';
require_once __DIR__ . '/includes/webinar-registration-service.php';
require_once __DIR__ . '/includes/webinar-register-helpers.php';

startSession();

$slug = trim((string) ($_GET['slug'] ?? ''));
$detailWebinar = null;
if ($slug !== '') {
    $detailWebinar = publicFetchWebinarBySlug($slug);
    if (!$detailWebinar) {
        header('Location: ' . url('webinars.php'));
        exit;
    }
}

$liveWebinars = $detailWebinar ? [] : publicFetchLiveWebinars();
$pageTitle = $detailWebinar ? (string) ($detailWebinar['title'] ?? 'Webinar') : 'Live Webinars';
$navActive = 'webinars';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= escHtml($pageTitle) ?> – CYBEORCH LABS</title>
<meta name="description" content="Live cybersecurity webinars on ethical hacking, web security, and more.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
<style>
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
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<?php if ($detailWebinar):
    $w = $detailWebinar;
    $thumbUrl = catalogImageUrl(webinarThumbPathForWebinar($w));
    $seatsLeft = max(0, (int) $w['max_seats'] - (int) $w['registered_seats']);
    $fillPct = (int) $w['max_seats'] > 0
        ? min(100, ((int) $w['registered_seats'] / (int) $w['max_seats']) * 100)
        : 0;
?>
<header class="page-hero webinar-detail-hero">
  <div class="container text-start" style="max-width:820px">
    <a href="<?= url('webinars.php') ?>" style="color:var(--cyber-muted);font-size:.88rem;display:inline-block;margin-bottom:1rem">
      <i class="fas fa-arrow-left me-1"></i> All Webinars
    </a>
    <div class="webinar-thumb-wrap mb-3">
      <img class="webinar-thumb" src="<?= $thumbUrl ?>" alt="<?= escHtml((string) ($w['title'] ?? 'Webinar')) ?>" loading="lazy">
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <?php renderWebinarPricingBadge($w); ?>
      <?php if (!empty($w['category'])): ?>
      <span style="font-size:.8rem;color:var(--cyber-muted)"><?= escHtml((string) $w['category']) ?></span>
      <?php endif; ?>
    </div>
    <h1><?= escHtml((string) $w['title']) ?></h1>
    <div class="webinar-meta mb-3">
      <span><i class="fa-solid fa-calendar-days me-1"></i><?= date('d M Y', strtotime((string) $w['scheduled_at'])) ?></span>
      <span><i class="fa-solid fa-clock me-1"></i><?= date('h:i A', strtotime((string) $w['scheduled_at'])) ?></span>
      <span><i class="fa-solid fa-hourglass-half me-1"></i><?= (int) $w['duration_mins'] ?> min</span>
    </div>
    <?php if (!empty($w['description'])): ?>
    <p style="color:#fff;line-height:1.75;margin-bottom:1rem"><?= nl2br(escHtml((string) $w['description'])) ?></p>
    <?php elseif (!empty($w['short_desc'])): ?>
    <p style="color:#fff;line-height:1.75;margin-bottom:1rem"><?= escHtml((string) $w['short_desc']) ?></p>
    <?php endif; ?>
    <?php if (!empty($w['instructor'])): ?>
    <div class="webinar-instructor mb-3">
      <div class="instructor-avatar"><?= escHtml(strtoupper(substr(decodeStoredText((string) $w['instructor']), 0, 1))) ?></div>
      <span><?= escHtml((string) $w['instructor']) ?></span>
    </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;color:var(--cyber-muted)">
      <span><?= (int) $w['registered_seats'] ?> registered</span>
      <span><?= $seatsLeft ?> seats left</span>
    </div>
    <div class="seats-bar mb-3"><div class="seats-fill" style="width:<?= $fillPct ?>%"></div></div>
    <div style="max-width:360px">
      <?php renderWebinarCardRegisterCta($w, 'w-100 text-center', 'font-size:1rem;padding:0.85rem 1.25rem'); ?>
    </div>
  </div>
</header>
<?php else: ?>
<header class="page-hero">
  <div class="container">
    <h1>Live <span class="accent">Webinars</span></h1>
    <p>Expert-led sessions on ethical hacking, web security, cloud security, and the latest cyber threats.</p>
  </div>
</header>

<section class="section pt-0" id="live-webinars">
  <div class="container">
    <?php if (hasDbError()): ?>
    <div class="alert" style="background:#ff6b35;color:#050b18;padding:1rem;border-radius:8px;margin-bottom:2rem">
      <?= htmlspecialchars(dbErrorMessage()) ?>
    </div>
    <?php endif; ?>

    <?php if ($liveWebinars === []): ?>
    <div class="empty-state">
      <i class="fas fa-video d-block mb-3"></i>
      <p>No live webinars at the moment. Browse <a href="<?= url('index.php#webinars') ?>" style="color:var(--cyber-accent)">upcoming webinars</a> on the homepage or <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">contact us</a>.</p>
    </div>
    <?php else: ?>
    <?php renderPublicWebinarCardGrid($liveWebinars, 'col-md-6 col-lg-4', true, true); ?>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php renderTrainingPublicFooter(); ?>
<?php renderSiteScripts(true); ?>

<div class="webinar-lightbox" id="webinarLightbox" aria-hidden="true" role="dialog" aria-label="Webinar image preview">
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
    lightboxImage.alt = title || 'Webinar image preview';
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
</body>
</html>
