<?php
declare(strict_types=1);

require_once __DIR__ . '/projects-data.php';
require_once __DIR__ . '/helpers.php';

/**
 * Encode asset paths safely (spaces, &, etc.) while keeping slashes.
 * Also normalizes Windows paths (backslashes) to URL paths.
 */
function lpAssetUrl(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $parts = array_map('rawurlencode', explode('/', $relativePath));
    return url(implode('/', $parts));
}

/**
 * @param array<string, mixed> $project
 */
function lpThumbPathForProject(array $project): string
{
    $title = strtolower(trim((string) ($project['title'] ?? '')));
    $slug = strtolower(trim((string) ($project['slug'] ?? '')));
    $haystack = $slug . ' ' . $title;

    $map = [
        // Core categories
        'mern' => 'assets/images/MERN Full Stack Development.png',
        'java' => 'assets/images/Java Full Stack Development.png',
        'cyber security' => 'assets/images/Cyber Security.png',
        'cybersecurity' => 'assets/images/Cyber Security.png',
        'data science' => 'assets/images/Data Science.png',
        'ai & ml' => 'assets/images/AI & ML.png',
        'ai and ml' => 'assets/images/AI & ML.png',
        'machine learning' => 'assets/images/AI & ML.png',
        'fintech' => 'assets/images/FinTech.png',

        // Blockchain / Web3 / Wallets
        'blockchain' => 'assets/images/blockchain_technology.png',
        'web3' => 'assets/images/Web3 Crypto Wallet Creation.png',
        'crypto wallet' => 'assets/images/Web3 Crypto Wallet Creation.png',
        'wallet security' => 'assets/images/Web3 Wallet Security & Cryptographic Protection.png',
        'cryptographic protection' => 'assets/images/Web3 Wallet Security & Cryptographic Protection.png',

        // Credits / Tokens / NxL ecosystem
        'nxl' => 'assets/images/Own NXL Credits Tokens System.png',
        'credits tokens' => 'assets/images/Own NXL Credits Tokens System.png',
        'token creation' => 'assets/images/Token creation.png',
        'buy credits' => 'assets/images/Buy Credits for Shopping System.png',
        'shopping' => 'assets/images/Buy Credits for Shopping System.png',
        
        // Note: file name contains literal "&amp;".
        'pos bridge' => 'assets/images/Cafe Merchant Redemption & POS Bridge.png',
        'merchant redemption' => 'assets/images/Cafe Merchant Redemption & POS Bridge.png',
        'redemption' => 'assets/images/Cafe Merchant Redemption & POS Bridge.png',

        // Security suite / DB protection
        'database security' => 'assets/images/Database Security & Cryptographic Protection Suite.png',
        'cryptographic protection suite' => 'assets/images/Database Security & Cryptographic Protection Suite.png',

        // Engagement / Gaming
        'gaming' => 'assets/images/Interactive Gaming & Engagement Engine.png',
        'engagement' => 'assets/images/Interactive Gaming & Engagement Engine.png',
    ];

    foreach ($map as $needle => $path) {
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return $path;
        }
    }

    return 'assets/images/AI & ML.png';
}

function lpAssetExists(string $relativePath): bool
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $root = dirname(__DIR__);
    $fsPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    return is_file($fsPath);
}

/**
 * Resolve a working image URL for details + listing.
 *
 * @param array<string, mixed> $project
 */
function liveProjectImageUrl(array $project): string
{
    $candidate = trim((string) ($project['image_path'] ?? ''));
    if ($candidate !== '' && !str_starts_with($candidate, 'http://') && !str_starts_with($candidate, 'https://')) {
        if (lpAssetExists($candidate)) {
            return lpAssetUrl($candidate);
        }
    } elseif ($candidate !== '') {
        return $candidate;
    }

    $thumb = lpThumbPathForProject($project);
    if ($thumb !== '' && lpAssetExists($thumb)) {
        return lpAssetUrl($thumb);
    }

    return lpAssetUrl('assets/images/AI & ML.png');
}

function renderLiveProjectsPageStyles(): void
{
    echo '<style>'
        . '.lp-grid{display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:1.25rem}'
        . '@media(min-width:576px){.lp-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}'
        . '@media(min-width:992px){.lp-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}'
        . '.lp-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;overflow:hidden;height:100%;display:flex;flex-direction:column;transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease}'
        . '.lp-card:hover{border-color:rgba(0,212,255,.55);transform:translateY(-6px);box-shadow:0 18px 48px rgba(0,212,255,.14)}'
        . '.lp-card--wide{grid-column:1/-1}'
        . '@media(min-width:992px){.lp-card--wide{grid-column:span 2}}'
        . '.lp-card-media{position:relative;min-height:140px;display:flex;align-items:flex-end;padding:1.25rem;background:linear-gradient(145deg,rgba(10,22,40,.95),rgba(0,212,255,.12))}'
        . '.lp-card-media.theme-cyber{background:linear-gradient(145deg,#0a1628 0%,#1a3a5c 40%,rgba(0,212,255,.25) 100%)}'
        . '.lp-card-media.theme-java{background:linear-gradient(145deg,#0a1628 0%,#3d2c1e 40%,rgba(255,107,53,.28) 100%)}'
        . '.lp-card-media.theme-mern{background:linear-gradient(145deg,#0a1628 0%,#1e3d32 40%,rgba(0,255,136,.22) 100%)}'
        . '.lp-card-media.theme-data{background:linear-gradient(145deg,#0a1628 0%,#2a1f4d 45%,rgba(147,112,219,.3) 100%)}'
        . '.lp-card-media.theme-ai{background:linear-gradient(145deg,#0a1628 0%,#1a2040 40%,rgba(0,212,255,.35) 100%)}'
        . '.lp-card-media.theme-fintech{background:linear-gradient(145deg,#0a1628 0%,#1a3328 35%,rgba(0,255,136,.32) 100%)}'
        . '.lp-card-media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.35}'
        . '.lp-card-icon{width:52px;height:52px;border-radius:14px;background:rgba(5,11,24,.65);border:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:var(--cyber-accent);position:relative;z-index:1}'
        . '.lp-card-body{padding:1.25rem 1.35rem 1.35rem;flex:1;display:flex;flex-direction:column}'
        . '.lp-card-title{font-family:\'Rajdhani\',sans-serif;font-size:1.35rem;font-weight:700;margin:0 0 .35rem;line-height:1.2}'
        . '.lp-card-desc{color:var(--cyber-muted);font-size:.88rem;line-height:1.65;margin-bottom:.85rem;flex:1}'
        . '.lp-meta{display:flex;flex-wrap:wrap;gap:.5rem .85rem;font-size:.78rem;color:var(--cyber-muted);margin-bottom:.85rem}'
        . '.lp-meta span{display:inline-flex;align-items:center;gap:.35rem}'
        . '.lp-features{display:grid;grid-template-columns:repeat(1,minmax(0,1fr));gap:.45rem;margin:0 0 1rem;padding:0;list-style:none}'
        . '@media(min-width:576px){.lp-features{grid-template-columns:repeat(2,minmax(0,1fr))}}'
        . '.lp-features li{font-size:.78rem;color:#c5d4e4;display:flex;align-items:flex-start;gap:.45rem;line-height:1.4}'
        . '.lp-features li i{color:var(--cyber-accent);margin-top:.15rem;width:1em;flex-shrink:0}'
        . '.lp-card-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:auto}'
        . '.btn-outline-lp{background:transparent;border:1px solid var(--cyber-border);color:var(--cyber-accent);padding:.55rem 1rem;border-radius:8px;font-weight:600;font-size:.82rem}'
        . '.btn-outline-lp:hover{border-color:var(--cyber-accent);color:#fff}'
        . '</style>';
}

/**
 * @param array<string, mixed> $project
 * @param array{wide?:bool,compact?:bool} $opts
 */
function renderLiveProjectCard(array $project, array $opts = []): void
{
    $slug = (string) ($project['slug'] ?? '');
    $theme = preg_replace('/[^a-z0-9-]/', '', (string) ($project['card_theme'] ?? 'theme-default')) ?: 'theme-default';
    $icon = liveProjectIconClasses((string) ($project['icon_class'] ?? 'fa-code-branch'));
    $wide = !empty($opts['wide']) || $slug === 'fintech';
    $features = $project['features'] ?? [];
    $imagePath = trim((string) ($project['image_path'] ?? ''));
    $detailUrl = liveProjectDetailUrl($project);
    ?>
    <article class="lp-card<?= $wide ? ' lp-card--wide' : '' ?>">
      <div class="lp-card-media <?= htmlspecialchars($theme) ?>">
        <?php if ($imagePath !== ''): ?>
        <img src="<?= htmlspecialchars(url($imagePath)) ?>" alt="" loading="lazy">
        <?php endif; ?>
        <div class="lp-card-icon"><i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i></div>
      </div>
      <div class="lp-card-body">
        <div class="d-flex flex-wrap gap-2 mb-2">
          <span class="webinar-badge <?= projectStatusBadgeClass((string) ($project['status'] ?? '')) ?>"><?= htmlspecialchars((string) ($project['status'] ?? '')) ?></span>
          <?php if (!empty($project['category'])): ?>
          <span class="webinar-badge badge-paid"><?= htmlspecialchars((string) $project['category']) ?></span>
          <?php endif; ?>
        </div>
        <h3 class="lp-card-title"><?= htmlspecialchars((string) ($project['title'] ?? '')) ?></h3>
        <p class="lp-card-desc"><?= htmlspecialchars((string) ($project['short_desc'] ?: $project['description'] ?? '')) ?></p>
        <div class="lp-meta">
          <?php if (!empty($project['stack'])): ?><span><i class="fas fa-layer-group"></i><?= htmlspecialchars((string) $project['stack']) ?></span><?php endif; ?>
          <?php if (!empty($project['duration'])): ?><span><i class="far fa-clock"></i><?= htmlspecialchars((string) $project['duration']) ?></span><?php endif; ?>
          <?php if (!empty($project['team'])): ?><span><i class="fas fa-users"></i><?= htmlspecialchars((string) $project['team']) ?></span><?php endif; ?>
        </div>
        <?php if ($features !== [] && $wide): ?>
        <ul class="lp-features">
          <?php foreach (array_slice($features, 0, 12) as $feat):
              $featIcon = (string) ($feat['icon'] ?? 'fa-check');
              $featLabel = (string) ($feat['label'] ?? '');
              if ($featLabel === '') {
                  continue;
              }
          ?>
          <li><i class="<?= htmlspecialchars(liveProjectIconClasses($featIcon)) ?>"></i><?= htmlspecialchars($featLabel) ?></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="lp-card-actions">
          <a href="<?= htmlspecialchars($detailUrl) ?>" class="btn-primary-cyber" style="width:auto;padding:.6rem 1.15rem;font-size:.85rem"><i class="fas fa-arrow-right me-2"></i>View Project</a>
          <a href="<?= url('contact.php') ?>" class="btn-outline-lp"><i class="fas fa-handshake me-1"></i>Collaborate</a>
        </div>
      </div>
    </article>
    <?php
}
