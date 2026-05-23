<?php

require_once __DIR__ . '/public-footer.php';

function renderLegalPublicStyles(): void
{
    echo '<style>'
        . ':root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-card:rgba(15,52,96,0.4);--cyber-border:rgba(0,212,255,0.2);}'
        . 'body{background:var(--cyber-dark);color:var(--cyber-text);font-family:\'DM Sans\',sans-serif;overflow-x:hidden}'
        . 'body::before{content:\'\';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}'
        . 'a{text-decoration:none}'
        . '.page-hero{padding:3.5rem 0 1.5rem;text-align:center;position:relative;z-index:1}'
        . '.page-hero h1{font-family:\'Rajdhani\',sans-serif;font-size:clamp(2rem,5vw,3.2rem);font-weight:700;margin-bottom:.75rem}'
        . '.page-hero h1 .accent{color:var(--cyber-accent)}'
        . '.page-hero p{color:#fff;max-width:680px;margin:0 auto;line-height:1.7}'
        . '.section{padding:0 0 4rem;position:relative;z-index:1}'
        . '.legal-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:2rem}'
        . '.legal-card h2,.legal-card h3{font-family:\'Rajdhani\',sans-serif;color:#fff;margin:1.5rem 0 .75rem}'
        . '.legal-card h2:first-child,.legal-card h3:first-child{margin-top:0}'
        . '.legal-card p,.legal-card li{color:#fff;line-height:1.8;font-size:.95rem}'
        . '.legal-card ul,.legal-card ol{padding-left:1.25rem;margin-bottom:1rem}'
        . '.legal-card li{margin-bottom:.35rem}'
        . '.legal-updated{color:var(--cyber-muted);font-size:.85rem;margin-bottom:1.5rem}'
        . '.support-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem}'
        . '.support-tile{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;transition:all .25s;height:100%}'
        . '.support-tile:hover{border-color:var(--cyber-accent);transform:translateY(-3px)}'
        . '.support-tile h3{font-family:\'Rajdhani\',sans-serif;font-size:1.2rem;color:#fff;margin-bottom:.5rem}'
        . '.support-tile p{color:var(--cyber-muted);font-size:.88rem;line-height:1.6;margin-bottom:1rem}'
        . '.support-tile .link{color:var(--cyber-accent);font-weight:600;font-size:.9rem}'
        . '.accordion-item{background:transparent;border:1px solid var(--cyber-border);margin-bottom:.75rem;border-radius:8px!important;overflow:hidden}'
        . '.accordion-button{background:rgba(10,22,40,0.95)!important;color:#fff!important;font-family:\'Rajdhani\',sans-serif;font-weight:600;font-size:1.05rem;box-shadow:none!important}'
        . '.accordion-button:not(.collapsed){background:rgba(0,212,255,0.08)!important;color:var(--cyber-accent)!important}'
        . '.accordion-button::after{filter:invert(1)}'
        . '.accordion-body{background:rgba(5,11,24,0.6);color:#fff;line-height:1.7}'
        . '</style>' . "\n";
    renderPublicFooterStyles();
}

function renderLegalPublicFooter(): void
{
    require_once __DIR__ . '/public-footer.php';
    renderPublicFooter();
}

function renderLegalPublicPageStart(string $pageTitle, string $metaDescription = '', string $navActive = ''): void
{
    $GLOBALS['legalNavActive'] = $navActive;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; <?= htmlspecialchars(SITE_NAME) ?></title>
<?php if ($metaDescription !== ''): ?>
<meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
<?php endif; ?>
<?php renderPublicPageHead(); renderLegalPublicStyles(); ?>
</head>
<body>
<?php
    $navActive = $GLOBALS['legalNavActive'] ?? '';
    require __DIR__ . '/public-navbar.php';
}

function renderLegalPublicPageEnd(): void
{
    renderLegalPublicFooter();
    renderSiteScripts(true);
    echo '</body></html>';
}
