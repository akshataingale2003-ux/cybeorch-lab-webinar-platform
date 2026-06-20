<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/company-public.php';

startSession();
$navActive = 'case-studies';
$studies = publicFetchCaseStudies();
foreach ($studies as &$study) {
    $study['screenshots'] = dbTry(
        fn () => db()->fetchAll('SELECT * FROM case_study_screenshots WHERE case_study_id = ? ORDER BY sort_order ASC, id ASC', [(int) $study['id']]),
        []
    );
}
unset($study);

$pageTitle = 'Case Studies';
$pageDescription = 'CYBEORCH case studies — projects delivered, client outcomes, performance improvements, and success stories.';
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
    <h1>Case <span class="accent">Studies</span></h1>
    <p>Measurable outcomes from real client engagements — delivery excellence, performance gains, and long-term partnership results.</p>
  </div>
</header>

<main class="company-section pt-0">
  <div class="container">
    <?php foreach ($studies as $study): ?>
    <article class="company-card mb-4 fade-in" id="<?= htmlspecialchars((string) $study['slug']) ?>">
      <div class="row g-4">
        <div class="col-lg-8">
          <h2 class="section-title" style="font-size:1.6rem"><?= htmlspecialchars((string) $study['title']) ?></h2>
          <?php if (!empty($study['client_name'])): ?>
          <p style="color:var(--cyber-accent);font-size:.9rem;margin-bottom:.75rem">Client: <?= htmlspecialchars((string) $study['client_name']) ?></p>
          <?php endif; ?>
          <?php if (!empty($study['summary'])): ?>
          <p style="color:var(--cyber-muted);line-height:1.8"><?= nl2br(htmlspecialchars((string) $study['summary'])) ?></p>
          <?php endif; ?>

          <?php if (!empty($study['projects_delivered'])): ?>
          <h3 class="section-title" style="font-size:1.15rem;margin-top:1.25rem">Projects Delivered</h3>
          <ul class="company-list"><?php foreach (companyLinesToList((string) $study['projects_delivered']) as $line): ?><li><?= htmlspecialchars($line) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>

          <?php if (!empty($study['client_outcomes'])): ?>
          <h3 class="section-title" style="font-size:1.15rem;margin-top:1.25rem">Client Outcomes</h3>
          <ul class="company-list"><?php foreach (companyLinesToList((string) $study['client_outcomes']) as $line): ?><li><?= htmlspecialchars($line) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>

          <?php if (!empty($study['before_after_metrics'])): ?>
          <h3 class="section-title" style="font-size:1.15rem;margin-top:1.25rem">Before / After Metrics</h3>
          <div><?php foreach (companyLinesToList((string) $study['before_after_metrics']) as $line): ?><span class="metric-pill"><?= htmlspecialchars($line) ?></span><?php endforeach; ?></div>
          <?php endif; ?>

          <?php if (!empty($study['performance_improvements'])): ?>
          <h3 class="section-title" style="font-size:1.15rem;margin-top:1.25rem">Performance Improvements</h3>
          <ul class="company-list"><?php foreach (companyLinesToList((string) $study['performance_improvements']) as $line): ?><li><?= htmlspecialchars($line) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>

          <?php if (!empty($study['success_story'])): ?>
          <h3 class="section-title" style="font-size:1.15rem;margin-top:1.25rem">Success Stories</h3>
          <p style="color:var(--cyber-muted);line-height:1.8"><?= nl2br(htmlspecialchars((string) $study['success_story'])) ?></p>
          <?php endif; ?>

          <?php if (!empty($study['results_achievements'])): ?>
          <h3 class="section-title" style="font-size:1.15rem;margin-top:1.25rem">Results &amp; Achievements</h3>
          <ul class="company-list"><?php foreach (companyLinesToList((string) $study['results_achievements']) as $line): ?><li><?= htmlspecialchars($line) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
        <div class="col-lg-4">
          <?php if (!empty($study['cover_image_path'])): ?>
          <img class="case-shot mb-3" src="<?= htmlspecialchars(companyPublicImageUrl((string) $study['cover_image_path'])) ?>" alt="" loading="lazy">
          <?php endif; ?>
          <?php foreach (($study['screenshots'] ?? []) as $shot): ?>
          <figure class="mb-3">
            <img class="case-shot" src="<?= htmlspecialchars(companyPublicImageUrl((string) $shot['image_path'])) ?>" alt="<?= htmlspecialchars((string) ($shot['caption'] ?? '')) ?>" loading="lazy">
            <?php if (!empty($shot['caption'])): ?><figcaption style="color:var(--cyber-muted);font-size:.8rem;margin-top:.35rem"><?= htmlspecialchars((string) $shot['caption']) ?></figcaption><?php endif; ?>
          </figure>
          <?php endforeach; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>

    <?php if ($studies === []): ?>
    <p class="text-center" style="color:var(--cyber-muted)">Case studies will appear here once added in the admin panel.</p>
    <?php endif; ?>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); renderSiteScripts(false); renderCompanyPageEndScript(); ?>
</body>
</html>
