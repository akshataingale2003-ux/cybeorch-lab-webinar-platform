<?php
declare(strict_types=1);

function renderTrainingPublicStyles(): void
{
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
        . '.webinar-thumb-wrap{border-radius:10px;overflow:hidden;border:1px solid rgba(0,212,255,0.22);background:rgba(10,22,40,0.7);margin-bottom:1rem;padding:.75rem}'
        . '.webinar-thumb{display:block;width:100%;height:auto;max-width:100%;object-fit:contain;object-position:center;border-radius:10px}'
        . '.webinar-thumb-wrap .webinar-thumb[data-lightbox-src]{cursor:pointer}'
        . '.webinar-badge{font-size:.72rem;font-weight:600;padding:.25rem .75rem;border-radius:4px;text-transform:uppercase}'
        . '.badge-free{background:rgba(0,255,136,0.15);color:var(--cyber-green);border:1px solid rgba(0,255,136,0.3)}'
        . '.badge-paid{background:rgba(255,193,7,0.15);color:var(--cyber-gold-dark);border:1px solid rgba(255,193,7,0.3);text-transform:none;letter-spacing:.02em}'
        . '.badge-live{background:rgba(255,0,0,0.2);color:#ff4444;border:1px solid rgba(255,0,0,0.3)}'
        . '.webinar-fee-notice{font-size:.88rem;line-height:1.5;color:var(--cyber-text);margin:.75rem 0 .5rem}'
        . '.webinar-fee-notice strong{font-weight:700;color:#fff}'
        . '.webinar-paid-compact{font-size:.88rem;font-weight:600;color:var(--cyber-gold-dark);margin:0 0 .75rem;letter-spacing:.02em}'
        . '.webinar-detail-hero .webinar-fee-notice{font-size:1rem;margin:1rem 0 1.25rem}'
        . '.webinar-meta{color:var(--cyber-muted);font-size:.85rem;margin:.75rem 0;display:flex;gap:1rem;flex-wrap:wrap}'
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
        . '.project-meta{display:flex;flex-wrap:wrap;gap:.75rem;margin:.75rem 0 1rem;font-size:.82rem;color:var(--cyber-muted)}'
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

/**
 * Map webinar title -> image path (relative).
 * Uses loose matching so DB titles still pick the correct thumbnail.
 */
function webinarThumbPathForTitle(string $title): string
{
    $t = strtolower(trim($title));

    $map = [
        'cybersecurity fundamentals for beginners' => 'assets/images/Cybersecurity Fundamentals for Beginners.png',
        'ethical hacking & penetration testing' => 'assets/images/Ethical Hacking & Penetration Testing.png',
        'cybersecurity and awareness webinar' => 'assets/images/Cybersecurity and Awareness Webinar.png',
        'cybersecurity fundamental' => 'assets/images/Cybersecurity Fundamentals for Beginners.png',
        'cybersecurity fundamentals' => 'assets/images/Cybersecurity Fundamentals for Beginners.png',
        'cybersecurity' => 'assets/images/Cybersecurity Fundamentals for Beginners.png',
        'awareness webinar' => 'assets/images/Cybersecurity and Awareness Webinar.png',
        'pipeline security' => 'assets/images/DevOps & Pipeline Security Webinar.png',
        'devops' => 'assets/images/DevOps & Pipeline Security Webinar.png',
        'ai tools' => 'assets/images/AI Tools & Automation Webinar.png',
        'automation webinar' => 'assets/images/AI Tools & Automation Webinar.png',
        'ai / ml' => 'assets/images/AI Tools & Automation Webinar.png',
        'ai/ml' => 'assets/images/AI Tools & Automation Webinar.png',
        'cloud security' => 'assets/images/Cloud Security & AWS Essentials.png',
        'aws essentials' => 'assets/images/Cloud Security & AWS Essentials.png',
        'ethical hacking' => 'assets/images/Ethical Hacking & Penetration Testing.png',
        'penetration testing' => 'assets/images/Ethical Hacking & Penetration Testing.png',
        'blockchain' => 'assets/images/blockchain_technology.png',
        'web3' => 'assets/images/Web3 Crypto Wallet Creation.png',
    ];

    foreach ($map as $needle => $path) {
        if (str_contains($t, $needle)) {
            return $path;
        }
    }

    return 'assets/images/AI Tools & Automation Webinar.png';
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

function renderTrainingPublicFooter(): void
{
    require_once __DIR__ . '/public-footer.php';
    renderPublicFooter();
}
