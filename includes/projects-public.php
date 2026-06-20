<?php
declare(strict_types=1);

require_once __DIR__ . '/public-catalog.php';
require_once __DIR__ . '/live-projects-public.php';
require_once __DIR__ . '/assignments-data.php';

function renderHomepageProjectsStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style>'
        . '.hp-projects-section{padding:4rem 0}'
        . '.hp-projects-section:nth-of-type(even){background:rgba(10,22,40,0.45)}'
        . '.hp-project-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;overflow:hidden;height:100%;display:flex;flex-direction:column;transition:all .3s}'
        . '.hp-project-card:hover{border-color:var(--cyber-accent);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,212,255,0.12)}'
        . '.hp-project-thumb{aspect-ratio:16/10;background:rgba(0,0,0,.25);overflow:hidden}'
        . '.hp-project-thumb img{width:100%;height:100%;object-fit:cover;display:block}'
        . '.hp-project-body{padding:1.25rem;display:flex;flex-direction:column;flex:1}'
        . '.hp-project-title{font-size:1.05rem;font-weight:700;margin:.5rem 0 .65rem;color:var(--cyber-text)}'
        . '.hp-project-desc{font-size:.88rem;color:var(--cyber-muted);margin-bottom:.75rem;line-height:1.5;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}'
        . '.hp-project-meta{display:flex;flex-wrap:wrap;gap:.5rem .75rem;font-size:.78rem;color:var(--cyber-muted);margin-bottom:1rem}'
        . '.hp-project-meta span{display:inline-flex;align-items:center;gap:.35rem}'
        . '.hp-project-actions{margin-top:auto}'
        . '</style>';
}

/**
 * @param list<array<string, mixed>> $projects
 */
function renderHomepageProjectSection(string $sectionId, string $title, string $accent, string $subtitle, array $projects, string $viewAllUrl, string $type, bool $showHeadingDivider = true): void
{
    renderHomepageProjectsStyles();
    ?>
    <section class="section hp-projects-section" id="<?= htmlspecialchars($sectionId) ?>">
      <div class="container">
        <div class="text-center mb-4 mb-md-5">
          <h2 class="section-title"><?= htmlspecialchars($title) ?> <span class="accent"><?= htmlspecialchars($accent) ?></span></h2>
          <?php if ($showHeadingDivider): ?>
          <div class="divider mx-auto"></div>
          <?php endif; ?>
          <?php if ($subtitle !== ''): ?>
          <p class="section-subtitle"><?= htmlspecialchars($subtitle) ?></p>
          <?php endif; ?>
        </div>

        <?php if ($projects === []): ?>
        <p class="text-center" style="color:var(--cyber-muted)">No active projects in this category right now. Check back soon.</p>
        <?php else: ?>
        <div class="row g-4">
          <?php foreach ($projects as $project): ?>
          <div class="col-md-6 col-lg-4">
            <?php renderHomepageProjectCard($project, $type); ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4">
          <a href="<?= htmlspecialchars($viewAllUrl) ?>" class="btn-outline-cyber"><i class="fas fa-arrow-right me-2"></i>View all <?= htmlspecialchars($title) ?></a>
        </div>
      </div>
    </section>
    <?php
}

/** @param array<string, mixed> $project */
function renderHomepageProjectCard(array $project, string $type): void
{
    $title = (string) ($project['title'] ?? 'Project');
    $desc = (string) ($project['short_desc'] ?: $project['description'] ?? '');
    $category = (string) ($project['category'] ?? '');
    $status = (string) ($project['status'] ?? '');

    if ($type === 'live') {
        $imageUrl = liveProjectImageUrl($project);
        $detailUrl = liveProjectDetailUrl($project);
        $meta = array_filter([
            !empty($project['stack']) ? (string) $project['stack'] : '',
            !empty($project['duration']) ? (string) $project['duration'] : '',
        ]);
        $ctaLabel = 'View Project';
        $ctaIcon = 'fa-arrow-right';
    } elseif ($type === 'hands-on') {
        $imageUrl = '';
        $detailUrl = assignmentRegisterUrl($project);
        $meta = array_filter([
            !empty($project['type']) ? (string) $project['type'] : '',
            !empty($project['duration']) ? (string) $project['duration'] : '',
        ]);
        $ctaLabel = 'Apply Now';
        $ctaIcon = 'fa-paper-plane';
    } else {
        $imageUrl = freelanceProjectImageUrl($project);
        $detailUrl = freelanceProjectDetailUrl($project);
        $meta = array_filter([
            !empty($project['budget']) ? (string) $project['budget'] : '',
            !empty($project['duration']) ? (string) $project['duration'] : '',
        ]);
        $ctaLabel = 'View Details';
        $ctaIcon = 'fa-arrow-right';
    }
    ?>
    <article class="hp-project-card">
      <?php if ($imageUrl !== ''): ?>
      <div class="hp-project-thumb">
        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($title) ?>" loading="lazy" decoding="async">
      </div>
      <?php endif; ?>
      <div class="hp-project-body">
        <div class="d-flex flex-wrap gap-2">
          <?php if ($status !== ''): ?>
          <span class="webinar-badge <?= assignmentStatusBadgeClass($status) ?>"><?= htmlspecialchars($status) ?></span>
          <?php endif; ?>
          <?php if ($category !== ''): ?>
          <span class="webinar-badge badge-paid" style="font-size:.65rem"><?= htmlspecialchars($category) ?></span>
          <?php endif; ?>
        </div>
        <h3 class="hp-project-title"><?= htmlspecialchars($title) ?></h3>
        <?php if ($desc !== ''): ?>
        <p class="hp-project-desc"><?= htmlspecialchars($desc) ?></p>
        <?php endif; ?>
        <?php if ($meta !== []): ?>
        <div class="hp-project-meta">
          <?php foreach ($meta as $item): ?>
          <span><i class="fas fa-circle" style="font-size:.35rem"></i><?= htmlspecialchars($item) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="hp-project-actions">
          <a href="<?= htmlspecialchars($detailUrl) ?>" class="btn-primary-cyber" style="width:auto;padding:.6rem 1.1rem;font-size:.85rem">
            <i class="fas <?= htmlspecialchars($ctaIcon) ?> me-2"></i><?= htmlspecialchars($ctaLabel) ?>
          </a>
        </div>
      </div>
    </article>
    <?php
}

/** @return array{live: list<array<string, mixed>>, hands_on: list<array<string, mixed>>, freelance: list<array<string, mixed>>} */
function publicFetchHomepageProjects(int $limit = 3): array
{
    $limit = max(1, min(12, $limit));
    ensurePublicCatalogSchemas();

    return [
        'live'      => array_slice(dbTry(static fn () => catalogGetLiveProjects(false, true), []), 0, $limit),
        'hands_on'  => array_slice(dbTry(static fn () => catalogGetAssignments(false, true), []), 0, $limit),
        'freelance' => array_slice(dbTry(static fn () => catalogGetFreelanceProjects(false, true), []), 0, $limit),
    ];
}
