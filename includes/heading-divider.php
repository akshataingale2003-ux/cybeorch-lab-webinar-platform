<?php
declare(strict_types=1);

/**
 * Site-wide divider styles — included once per page via renderPublicPageHead / renderPortalPageHead / renderAuthPageHead.
 */
function renderGlobalDividerStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style id="cybeorch-heading-divider">'
        . '.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent,#00d4ff),var(--cyber-green,#00ff88));margin:.75rem auto 1.5rem;border-radius:2px;flex-shrink:0;}'
        . '.page-hero .divider{margin-bottom:1rem;}'
        . '.auth-title + .divider,.page-title + .divider,.section-title + .divider,.team-profile-heading + .divider{margin-top:0;}'
        . 'body.portal-page .topbar{align-items:stretch;flex-wrap:wrap;}'
        . 'body.portal-page .topbar-row{display:flex;align-items:center;justify-content:space-between;gap:.75rem;width:100%;flex-wrap:nowrap;}'
        . 'body.portal-page .topbar .divider{margin:.35rem auto 0;width:60px;}'
        . 'body.admin-page .topbar{align-items:stretch;flex-wrap:wrap;}'
        . 'body.admin-page .topbar-row{display:flex;align-items:center;justify-content:space-between;gap:.75rem;width:100%;}'
        . 'body.admin-page .topbar .divider{margin:.35rem auto 0;width:60px;}'
        . '</style>' . "\n";
}

/**
 * Public marketing page hero (H1 + optional subtitle).
 */
function renderPublicPageHero(string $titleHtml, string $subtitleHtml = '', string $extraInnerHtml = ''): void
{
    echo '<header class="page-hero"><div class="container">';
    echo '<h1>' . $titleHtml . '</h1>';
    if ($subtitleHtml !== '') {
        echo '<p>' . $subtitleHtml . '</p>';
    }
    if ($extraInnerHtml !== '') {
        echo $extraInnerHtml;
    }
    echo '</div></header>' . "\n";
}

/**
 * Public marketing site homepage (same tab).
 */
function portalWebsiteHomeUrl(): string
{
    return function_exists('url') ? url('index.php') : '/';
}

/**
 * Portal topbar with page title.
 */
function renderPortalTopbar(string $titleHtml, string $actionsHtml = ''): void
{
    $homeUrl = portalWebsiteHomeUrl();

    echo '<div class="topbar">';
    echo '<div class="topbar-row">';
    echo '<div class="topbar-start">';
    echo '<a href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') . '" class="topbar-link topbar-back-link">';
    echo '<i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Back to Website</a>';
    echo '<div class="page-title">' . $titleHtml . '</div>';
    echo '</div>';
    if ($actionsHtml !== '') {
        echo $actionsHtml;
    }
    echo '</div>';
    echo '</div>' . "\n";
}

/**
 * Auth card heading (H1).
 */
function renderAuthPageHeading(string $titleHtml): void
{
    echo '<h1 class="auth-title">' . $titleHtml . '</h1>';
}

/**
 * Centered section title block (H2 + optional subtitle).
 */
function renderSectionTitleBlock(string $titleHtml, string $subtitleHtml = '', string $titleClass = 'section-title'): void
{
    echo '<h2 class="' . htmlspecialchars($titleClass, ENT_QUOTES, 'UTF-8') . '">' . $titleHtml . '</h2>';
    if ($subtitleHtml !== '') {
        echo '<p class="section-subtitle">' . $subtitleHtml . '</p>';
    }
}
