<?php
declare(strict_types=1);

function renderTrainingPublicStyles(): void
{
    require_once __DIR__ . '/webinar-register-helpers.php';
    echo '<style>'
        . ':root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-blue:#0f3460;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-gold:#ffc107;--cyber-gold-dark:#b8860b;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-card:rgba(15,52,96,0.4);--cyber-border:rgba(0,212,255,0.2);}'
        . 'body{background:var(--cyber-dark);color:var(--cyber-text);font-family:\'DM Sans\',sans-serif;overflow-x:hidden}'
        . 'body::before{content:\'\';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}'
        . 'a{text-decoration:none}'
        . '.page-hero{padding:3rem 0 1rem;text-align:center;position:relative;z-index:1}'
        . '.page-hero h1{font-family:\'Rajdhani\',sans-serif;font-size:clamp(2rem,5vw,3rem);font-weight:700;margin-bottom:.75rem}'
        . '.page-hero h1 .accent{color:var(--cyber-accent)}'
        . '.page-hero p{color:#ffffff;max-width:640px;margin:0 auto;line-height:1.7}'
        . '.section{padding:3rem 0 5rem;position:relative;z-index:1}'
        . '.section-title{font-family:\'Rajdhani\',sans-serif;font-size:clamp(1.8rem,4vw,2.4rem);font-weight:700}'
        . '.section-title .accent{color:var(--cyber-accent)}'
        . '.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin:.75rem auto 2rem}'
        . '.webinar-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;height:100%;width:100%;transition:all .3s;position:relative;overflow:hidden;display:flex;flex-direction:column}'
        . '.webinar-card:hover{border-color:var(--cyber-accent);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,212,255,0.15)}'
        . '.webinar-card-body{flex:1 1 auto;min-height:0}'
        . '.webinar-card-footer{margin-top:auto;flex-shrink:0;padding-top:.75rem}'
        . '.webinar-card-footer .webinar-fee-notice--free{min-height:2.25rem;margin:0 0 .75rem;display:flex;flex-direction:column;justify-content:flex-end}'
        . '.webinar-card-footer .btn-primary-cyber{display:block;width:100%}'
        . '.catalog-dual-price{display:flex;flex-direction:column;gap:.15rem;font-size:.88rem;line-height:1.35;margin:0 0 .75rem}'
        . '.catalog-dual-price .catalog-price-inr{font-weight:700;color:var(--cyber-text,#e0e8f0)}'
        . '.catalog-dual-price .catalog-price-usd{font-size:.82rem;color:var(--cyber-muted,#7a8fa6)}'
        . '.webinar-thumb-wrap{border-radius:10px;overflow:hidden;border:1px solid rgba(0,212,255,0.22);background:rgba(10,22,40,0.7);margin-bottom:1rem;padding:.75rem}'
        . '.webinar-thumb{display:block;width:100%;height:auto;max-width:100%;object-fit:contain;object-position:center;border-radius:10px}'
        . '.webinar-thumb-wrap .webinar-thumb[data-lightbox-src]{cursor:pointer}'
        . '.webinar-badge{font-size:.72rem;font-weight:600;padding:.25rem .75rem;border-radius:4px;text-transform:uppercase;letter-spacing:.5px;display:inline-block;line-height:1.2}'
        . '.badge-free{background:rgba(0,255,136,0.15);color:var(--cyber-green);border:1px solid rgba(0,255,136,0.3)}'
        . '.badge-paid{background:rgba(255,193,7,0.15);color:var(--cyber-gold-dark);border:1px solid rgba(255,193,7,0.3);text-transform:none;letter-spacing:.02em}'
        . '.badge-live{background:#ef4444;color:#fff;border:none;text-transform:uppercase}'
        . '.webinar-card .webinar-badge.badge-live,.fp-body .webinar-badge.badge-live,.freelance-card .webinar-badge.badge-live{font-size:.72rem;font-weight:700;padding:.35rem .85rem;border-radius:999px;letter-spacing:.03em;background:#ef4444;color:#fff;border:none}'
        . webinarPricingBadgeStylesCss()
        . '.webinar-fee-notice{font-size:.88rem;line-height:1.5;color:var(--cyber-text);margin:.75rem 0 .5rem}'
        . '.webinar-fee-notice strong{font-weight:700;color:#fff}'
        . '.webinar-paid-compact{font-size:.88rem;font-weight:600;color:var(--cyber-gold-dark);margin:0 0 .75rem;letter-spacing:.02em}'
        . '.webinar-detail-hero .webinar-fee-notice{font-size:1rem;margin:1rem 0 1.25rem}'
        . '.webinar-meta{color:var(--cyber-muted);font-size:.85rem;margin:.75rem 0;display:flex;gap:1rem;flex-wrap:nowrap;align-items:center;}'
        . '.webinar-title{font-family:\'Rajdhani\',sans-serif;font-size:1.25rem;font-weight:600;margin-bottom:.5rem}'
        . '.webinar-desc{color:#ffffff;font-size:.88rem;line-height:1.6;margin-bottom:1rem}'
        . '.webinar-instructor{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}'
        . '.instructor-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--cyber-accent),var(--cyber-blue));display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;color:#050b18}'
        . '.seats-bar{background:rgba(255,255,255,0.1);border-radius:2px;height:4px;margin:.5rem 0;overflow:hidden}'
        . '.seats-fill{height:100%;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green))}'
        . '.bootcamp-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;overflow:hidden;height:100%;transition:all .3s}'
        . '.bootcamp-card:hover{border-color:var(--cyber-green);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,255,136,0.12)}'
        . '.bootcamp-header{background:linear-gradient(135deg,var(--cyber-blue),rgba(0,212,255,0.2));padding:2rem;position:relative}'
        . '.bootcamp-price{position:absolute;top:1rem;right:1rem;text-align:right}'
        . '.price-original{font-size:.85rem;color:var(--cyber-muted);text-decoration:line-through}'
        . '.price-current{font-family:\'Rajdhani\',sans-serif;font-size:1.8rem;font-weight:700;color:var(--cyber-green)}'
        . '.bootcamp-body{padding:1.5rem}'
        . '.feature-list{list-style:none;margin:1rem 0;padding:0}'
        . '.feature-list li{padding:.35rem 0;font-size:.88rem;color:var(--cyber-muted);display:flex;align-items:flex-start;gap:.55rem}'
        . '.feature-list li i,.feature-list li .fa{flex-shrink:0;margin-top:.12rem;width:1.15em;text-align:center;color:var(--cyber-accent)}'
        . '.feature-list li .fa-coins{color:var(--cyber-green)}'
        . '.btn-primary-cyber{background:var(--cyber-accent);color:#050b18;border:none;padding:.75rem 1.25rem;border-radius:6px;font-weight:700;display:inline-block;transition:all .2s;width:100%;text-align:center;text-decoration:none}'
        . '.btn-primary-cyber:hover{background:var(--cyber-green);color:#050b18;transform:translateY(-2px)}'
        . '.btn-outline-cyber{background:transparent;border:2px solid var(--cyber-accent);color:var(--cyber-accent);padding:.75rem 1.25rem;border-radius:6px;font-weight:700;display:inline-block;transition:all .2s;width:100%;text-align:center;text-decoration:none}'
        . '.btn-outline-cyber:hover{background:var(--cyber-accent);color:#050b18;transform:translateY(-2px)}'
        . '.empty-state{text-align:center;color:var(--cyber-muted);padding:3rem}'
        . '.empty-state i{font-size:3rem;opacity:.3}'
        . '.project-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;height:100%;transition:all .3s}'
        . '.project-card:hover{border-color:var(--cyber-accent);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,212,255,0.12)}'
        . '.project-card .icon-wrap{width:48px;height:48px;border-radius:10px;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:center;color:var(--cyber-accent);font-size:1.25rem;margin-bottom:1rem}'
        . '.project-meta{display:flex;flex-wrap:nowrap;gap:.75rem;margin:.75rem 0 1rem;font-size:.82rem;color:var(--cyber-muted);align-items:center;}'
        . '.project-meta span{display:inline-flex;align-items:center;gap:.35rem}'
        . '.tag-list{display:flex;flex-wrap:wrap;gap:.5rem;margin:1rem 0}'
        . '.tag-pill{font-size:.72rem;padding:.25rem .65rem;border-radius:4px;background:rgba(0,212,255,0.08);border:1px solid var(--cyber-border);color:var(--cyber-muted)}'
        . '.program-funnel-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:14px;overflow:hidden;margin-top:2rem;max-width:960px;margin-left:auto;margin-right:auto;text-align:left;box-shadow:0 16px 48px rgba(0,0,0,0.35)}'
        . '.program-funnel-card:hover{border-color:rgba(0,212,255,0.45);box-shadow:0 20px 56px rgba(0,212,255,0.12)}'
        . '.funnel-card-intro{padding:1.25rem 1.5rem;border-bottom:1px solid var(--cyber-border);background:linear-gradient(135deg,rgba(15,52,96,0.6),rgba(0,212,255,0.08))}'
        . '.funnel-card-intro h2{font-family:\'Rajdhani\',sans-serif;font-size:1.35rem;font-weight:700;margin:0 0 .35rem;color:#fff}'
        . '.funnel-card-intro p{margin:0;font-size:.9rem;color:#ffffff;line-height:1.55}'
        . '.funnel-tiers{display:flex;flex-wrap:wrap}'
        . '.funnel-tier{flex:1 1 200px;padding:1.35rem 1.25rem;border-right:1px solid var(--cyber-border);position:relative}'
        . '.funnel-tier:last-child{border-right:none}'
        . '.funnel-tier-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.65rem;display:inline-block;padding:.2rem .55rem;border-radius:4px}'
        . '.funnel-tier--entry .funnel-tier-label{background:rgba(0,212,255,0.12);color:var(--cyber-accent);border:1px solid rgba(0,212,255,0.25)}'
        . '.funnel-tier--mid .funnel-tier-label{background:rgba(255,209,102,0.12);color:#ffd166;border:1px solid rgba(255,209,102,0.3)}'
        . '.funnel-tier--premium .funnel-tier-label{background:rgba(0,255,136,0.12);color:var(--cyber-green);border:1px solid rgba(0,255,136,0.3)}'
        . '.funnel-tier h3{font-family:\'Rajdhani\',sans-serif;font-size:1.1rem;font-weight:600;margin:0 0 .5rem;color:#fff}'
        . '.funnel-tier-price{font-family:\'Rajdhani\',sans-serif;font-size:1.75rem;font-weight:700;line-height:1.35;margin-bottom:.5rem}'
        . '.funnel-tier--entry .funnel-tier-price{color:var(--cyber-accent)}'
        . '.funnel-tier--mid .funnel-tier-price{color:#ffd166}'
        . '.funnel-tier--premium .funnel-tier-price{color:var(--cyber-green)}'
        . '.funnel-tier-duration{font-size:.82rem;color:rgba(255,255,255,.7);margin:0 0 .5rem}'
        . '.bootcamp-dual-price{display:flex;flex-direction:column;align-items:flex-end;gap:.15rem;line-height:1.35}'
        . '.funnel-tier-price .bootcamp-dual-price{align-items:flex-start}'
        . '.bootcamp-price-inr{display:block}'
        . '.bootcamp-price-usd{display:block;font-size:.72em;font-weight:600;opacity:.92}'
        . '.price-current .bootcamp-dual-price{align-items:flex-end}'
        . '.funnel-tier-desc{font-size:.82rem;color:var(--cyber-muted);line-height:1.5;margin-bottom:1rem;min-height:2.5em}'
        . '.funnel-tier-cta{font-size:.8rem;font-weight:700;padding:.55rem .85rem;border-radius:6px;display:inline-block;text-align:center;width:100%;transition:all .2s}'
        . '.funnel-tier--entry .funnel-tier-cta{background:var(--cyber-accent);color:#050b18}'
        . '.funnel-tier--entry .funnel-tier-cta:hover{background:var(--cyber-green);color:#050b18}'
        . '.funnel-tier--mid .funnel-tier-cta{background:rgba(255,209,102,0.15);color:#ffd166;border:1px solid rgba(255,209,102,0.35)}'
        . '.funnel-tier--mid .funnel-tier-cta:hover{background:rgba(255,209,102,0.28);color:#fff}'
        . '.funnel-tier--premium .funnel-tier-cta{background:var(--cyber-green);color:#050b18}'
        . '.funnel-tier--premium .funnel-tier-cta:hover{background:var(--cyber-accent);color:#050b18}'
        . '@media (max-width:767.98px){.funnel-tier{border-right:none;border-bottom:1px solid var(--cyber-border)}.funnel-tier:last-child{border-bottom:none}}'
        . '</style>' . "\n";
    require_once __DIR__ . '/public-footer.php';
    renderPublicFooterStyles();
}

/** Shared Our Services / catalog image card styling (services, webinars). */
function renderCatalogImageCardStyles(): void
{
    echo '<style>'
        . '.service-image-card{background:#000;border:1px solid rgba(255,209,102,0.35);border-radius:15px;overflow:hidden;height:100%;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.45),0 0 24px rgba(255,209,102,0.08);transition:transform .35s ease,border-color .35s ease,box-shadow .35s ease}'
        . '.service-image-card:hover{transform:translateY(-6px);border-color:rgba(255,209,102,0.65);box-shadow:0 16px 42px rgba(0,0,0,0.55),0 0 32px rgba(255,209,102,0.18),0 0 48px rgba(0,212,255,0.12)}'
        . '.service-image-wrap{background:linear-gradient(180deg,rgba(10,22,40,0.6) 0%,#000 100%);padding:.75rem;border-bottom:1px solid rgba(255,209,102,0.15)}'
        . '.service-image-media{display:block;width:100%;height:auto;max-width:100%;object-fit:contain;object-position:center;border-radius:10px}'
        . '.service-image-wrap .service-image-media[data-lightbox-src]{cursor:pointer}'
        . '.service-image-body{padding:1.15rem 1.25rem 1.35rem;flex:1;display:flex;flex-direction:column}'
        . '.service-image-title{font-family:\'Rajdhani\',sans-serif;font-size:1.35rem;font-weight:700;margin:0 0 .55rem;color:var(--cyber-text,#e0e8f0)}'
        . '.service-image-desc{margin:0;color:var(--cyber-muted,#7a8fa6);font-size:.9rem;line-height:1.65}'
        . '</style>' . "\n";
}

function catalogImageUrl(string $relativePath): string
{
    return htmlspecialchars(str_replace(' ', '%20', urlVersioned($relativePath)), ENT_QUOTES, 'UTF-8');
}

/** Absolute path to upcoming webinar image folder. */
function webinarUpcomingImageDir(): string
{
    $root = defined('CYBEORCH_APP_ROOT') ? CYBEORCH_APP_ROOT : dirname(__DIR__);

    return $root . '/assets/images/upcoming_webinar';
}

/** Absolute path to live webinar image folder. */
function webinarLiveImageDir(): string
{
    $root = defined('CYBEORCH_APP_ROOT') ? CYBEORCH_APP_ROOT : dirname(__DIR__);

    return $root . '/assets/images/live_webinars';
}

/**
 * Resolve a live webinar card image from assets/images/live_webinars/.
 */
function webinarLiveThumbPathForTitle(string $title): string
{
    $dir = webinarLiveImageDir();
    $base = 'assets/images/live_webinars/';
    $title = trim($title);

    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        $candidate = $title . '.' . $ext;
        if (is_file($dir . '/' . $candidate)) {
            return $base . $candidate;
        }
    }

    if (is_dir($dir)) {
        $titleLower = strtolower($title);
        foreach (scandir($dir) ?: [] as $file) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                continue;
            }
            if (strtolower(pathinfo($file, PATHINFO_FILENAME)) === $titleLower) {
                return $base . $file;
            }
        }
    }

    $default = 'Cybersecurity Fundamentals for Beginners.png';
    if (is_file($dir . '/' . $default)) {
        return $base . $default;
    }

    foreach (scandir($dir) ?: [] as $file) {
        if (in_array($file, ['.', '..'], true)) {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            return $base . $file;
        }
    }

    return $base . 'Cybersecurity Fundamentals for Beginners.png';
}

/** Pick the correct webinar thumbnail path based on status and optional DB thumbnail. */
function webinarThumbPathForWebinar(array $webinar): string
{
    $thumb = trim((string) ($webinar['thumbnail'] ?? ''));
    if ($thumb !== '') {
        $root = defined('CYBEORCH_APP_ROOT') ? CYBEORCH_APP_ROOT : dirname(__DIR__);
        $relative = ltrim(str_replace('\\', '/', $thumb), '/');
        if (is_file($root . '/' . $relative)) {
            return $relative;
        }
    }

    $title = (string) ($webinar['title'] ?? '');
    if (($webinar['status'] ?? '') === 'live') {
        return webinarLiveThumbPathForTitle($title);
    }

    return webinarThumbPathForTitle($title);
}

/**
 * Map webinar title -> image path (relative).
 * Prefers assets/images/upcoming_webinar/ assets for catalog cards.
 */
function webinarThumbPathForTitle(string $title): string
{
    $dir = webinarUpcomingImageDir();
    $base = 'assets/images/upcoming_webinar/';
    $t = strtolower(trim($title));

    $stemNeedles = [];
    if (str_contains($t, 'blockchain')) {
        $stemNeedles = ['blockchain'];
    } elseif (str_contains($t, 'devops')) {
        $stemNeedles = ['devops'];
    } elseif (str_contains($t, 'cyber') || str_contains($t, 'security')) {
        $stemNeedles = ['cyber', 'security'];
    } elseif (
        str_contains($t, 'ai / ml')
        || str_contains($t, 'ai/ml')
        || preg_match('/\bai\b|\bml\b/', $t) === 1
    ) {
        $stemNeedles = ['ai', 'ml'];
    }

    if ($stemNeedles !== [] && is_dir($dir)) {
        $needsAiMlPair = str_contains($t, 'ai / ml')
            || str_contains($t, 'ai/ml')
            || preg_match('/\bai\b|\bml\b/', $t) === 1;

        foreach (scandir($dir) ?: [] as $file) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                continue;
            }
            $stem = strtolower(pathinfo($file, PATHINFO_FILENAME));

            if ($needsAiMlPair && $stemNeedles === ['ai', 'ml']) {
                if (str_contains($stem, 'ai') && str_contains($stem, 'ml')) {
                    return $base . $file;
                }
                continue;
            }

            foreach ($stemNeedles as $needle) {
                if (str_contains($stem, $needle)) {
                    return $base . $file;
                }
            }
        }
    }

    $fallback = [
        'Cyber Security.png',
        'AI & ML.png',
        'blockchain.png',
        'devops.png',
    ];
    foreach ($fallback as $filename) {
        if (is_file($dir . '/' . $filename)) {
            return $base . $filename;
        }
    }

    return $base . 'devops.png';
}

function trainingDetailUrl(string $type, array $row): string
{
    require_once __DIR__ . '/webinar-register-helpers.php';
    $slug = $row['slug'] ?? '';
    if ($slug !== '' && $type === 'webinar') {
        return url('webinars.php?slug=' . urlencode((string) $slug));
    }
    if ($type === 'bootcamp') {
        return bootcampSecurePaymentUrl($row);
    }
    return url('checkout.php?type=' . $type . '&id=' . (int) ($row['id'] ?? 0));
}

/**
 * Render a responsive grid of public webinar catalog cards.
 *
 * @param list<array<string, mixed>> $webinars
 */
function renderPublicWebinarCardGrid(array $webinars, string $colClass = 'col-md-6 col-lg-3', bool $linkTitles = false, bool $lightbox = false): void
{
    if ($webinars === []) {
        return;
    }

    require_once __DIR__ . '/webinar-register-helpers.php';
    renderWebinarPricingBadgeStyles();

    echo '<div class="row g-4">';
    foreach ($webinars as $cat) {
        $scheduledAt = (string) ($cat['scheduled_at'] ?? '');
        $seatsLeft = max(0, (int) ($cat['max_seats'] ?? 0) - (int) ($cat['registered_seats'] ?? 0));
        $fillPct = (int) ($cat['max_seats'] ?? 0) > 0
            ? min(100, ((int) ($cat['registered_seats'] ?? 0) / (int) $cat['max_seats']) * 100)
            : 0;
        $thumbUrl = catalogImageUrl(webinarThumbPathForWebinar($cat));
        $title = escHtml((string) ($cat['title'] ?? ''));
        ?>
      <div class="<?= htmlspecialchars($colClass) ?> fade-in d-flex">
        <div class="webinar-card">
          <div class="webinar-card-body">
            <div class="webinar-thumb-wrap">
              <img
                class="webinar-thumb"
                src="<?= $thumbUrl ?>"
                alt="<?= $title ?> — CYBEORCH webinar"
                loading="lazy"
                decoding="async"
                <?php if ($lightbox): ?>data-lightbox-src="<?= $thumbUrl ?>" data-lightbox-title="<?= $title ?>"<?php endif; ?>
              >
            </div>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <?php renderWebinarPricingBadge($cat); ?>
              <?php if (!empty($cat['category'])): ?>
              <span style="font-size:.75rem;color:var(--cyber-muted)"><?= escHtml((string) $cat['category']) ?></span>
              <?php endif; ?>
            </div>
            <h3 class="webinar-title">
              <?php if ($linkTitles): ?>
              <a href="<?= htmlspecialchars(url('webinars.php?slug=' . rawurlencode((string) ($cat['slug'] ?? '')))) ?>" style="color:inherit"><?= $title ?></a>
              <?php else: ?>
              <?= $title ?>
              <?php endif; ?>
            </h3>
            <p class="webinar-desc"><?= escHtml((string) ($cat['short_desc'] ?? '')) ?></p>
            <div class="webinar-meta">
              <span><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i><?= $scheduledAt !== '' ? date('d M Y', strtotime($scheduledAt)) : '' ?></span>
              <span><i class="fa-solid fa-clock me-1" aria-hidden="true"></i><?= $scheduledAt !== '' ? date('h:i A', strtotime($scheduledAt)) : '' ?></span>
              <span><i class="fa-solid fa-hourglass-half me-1" aria-hidden="true"></i><?= (int) ($cat['duration_mins'] ?? 0) ?> min</span>
            </div>
            <div class="webinar-instructor">
              <div class="instructor-avatar"><?= escHtml(strtoupper(substr(decodeStoredText((string) ($cat['instructor'] ?? 'C')), 0, 1))) ?></div>
              <span class="instructor-name"><?= escHtml((string) ($cat['instructor'] ?? '')) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.8rem; color:var(--cyber-muted)">
              <span><?= (int) ($cat['registered_seats'] ?? 0) ?> registered</span>
              <span><?= $seatsLeft ?> seats left</span>
            </div>
            <div class="seats-bar"><div class="seats-fill" style="width:<?= $fillPct ?>%"></div></div>
          </div>
          <div class="webinar-card-footer">
            <?php renderWebinarCardRegisterCta($cat); ?>
          </div>
        </div>
      </div>
        <?php
    }
    echo '</div>';
}

function renderTrainingPublicFooter(): void
{
    require_once __DIR__ . '/public-footer.php';
    renderPublicFooter();
}
