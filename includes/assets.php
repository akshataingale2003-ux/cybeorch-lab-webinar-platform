<?php
declare(strict_types=1);

require_once __DIR__ . '/responsive.php';
require_once __DIR__ . '/portal-styles.php';

/**
 * Site-wide icons (Font Awesome) — local files with CDN fallback.
 */
function fontAwesomeCssUrl(): string
{
    $localFile = dirname(__DIR__) . '/assets/vendor/fontawesome/css/all.min.css';
    if (is_file($localFile)) {
        return url('assets/vendor/fontawesome/css/all.min.css');
    }
    return 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css';
}

function renderIconStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $faCss = fontAwesomeCssUrl();
    $isLocal = str_contains($faCss, 'assets/vendor/fontawesome');
    if (!$isLocal) {
        echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n";
    }
    echo '<link rel="stylesheet" href="' . htmlspecialchars($faCss, ENT_QUOTES, 'UTF-8') . '"' . ($isLocal ? '' : ' crossorigin="anonymous" referrerpolicy="no-referrer"') . '>' . "\n";

    echo '<style id="cybeorch-icons">'
        . ':root{--icon-muted:var(--cyber-muted,#7a8fa6);--icon-accent:var(--cyber-accent,#00d4ff);}'
        . '.webinar-meta i,.webinar-meta .fa,.bootcamp-header i,.feature-list i{color:var(--icon-accent);}'
        . '.webinar-meta i,.webinar-meta .fa{min-width:1em;text-align:center;}'
        . '.sidebar-link i,.about-card i,.career-card>i,.icon-wrap i{color:var(--icon-accent);}'
        . '.navbar-toggler-icon-custom i{font-size:1.25rem;color:var(--icon-accent);}'
        . '</style>' . "\n";
}

function renderSiteFavicon(): void
{
    static $rendered = false;
    if ($rendered) {
        return;
    }
    $rendered = true;

    $root = dirname(__DIR__);
    $logoFile = $root . '/assets/images/logo.jpeg';
    $v = is_file($logoFile) ? (string) filemtime($logoFile) : (string) time();

    if (is_file($logoFile)) {
        $iconHref = absoluteUrl('assets/images/logo.jpeg') . '?v=' . $v;
        $iconType = 'image/jpeg';
    } elseif (is_file($root . '/favicon.png')) {
        $iconHref = absoluteUrl('favicon.png') . '?v=' . $v;
        $iconType = 'image/png';
    } else {
        $iconHref = absoluteUrl('favicon.php') . '?v=' . $v;
        $iconType = 'image/png';
    }

    $href = htmlspecialchars($iconHref, ENT_QUOTES, 'UTF-8');
    echo '<link rel="icon" type="' . $iconType . '" sizes="32x32" href="' . $href . '">' . "\n";
    echo '<link rel="shortcut icon" type="' . $iconType . '" href="' . $href . '">' . "\n";

    if (is_file($logoFile)) {
        echo '<link rel="apple-touch-icon" href="' . htmlspecialchars(absoluteUrl('assets/images/logo.jpeg') . '?v=' . $v, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    } elseif (is_file($root . '/favicon.ico')) {
        echo '<link rel="icon" href="' . htmlspecialchars(absoluteUrl('favicon.ico') . '?v=' . $v, ENT_QUOTES, 'UTF-8') . '" type="image/x-icon">' . "\n";
    }
}

function renderSiteFonts(): void
{
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">' . "\n";
}

function renderBootstrapCss(): void
{
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">' . "\n";
}

/** Fonts + Bootstrap + Font Awesome + favicon + navbar + responsive (all public pages). */
function renderPublicHeadAssets(): void
{
    renderSiteFavicon();
    renderSiteFonts();
    renderBootstrapCss();
    renderIconStyles();
    renderPublicNavbarStyles();
}

/** Auth pages only — avoids global responsive CSS breaking forms. */
function renderAuthPageStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    renderBrandStyles();
    echo '<style id="cybeorch-auth">'
        . 'html{overflow-x:clip;-webkit-text-size-adjust:100%;}'
        . 'body.auth-page{margin:0;overflow-x:clip;max-width:100vw;}'
        . 'body.auth-page .mb-3{margin-bottom:1rem!important;}'
        . 'body.auth-page .form-control,body.auth-page .form-select{font-size:16px!important;}'
        . 'body.auth-page .auth-subtitle{color:var(--cyber-muted,#7a8fa6)!important;}'
        . 'body.auth-page .input-group{display:flex;width:100%;}'
        . 'body.auth-page .input-group .form-control{flex:1 1 auto;min-width:0;width:1%;}'
        . '</style>' . "\n";
}

/**
 * Auth, checkout, wallet — fonts, Bootstrap, icons (no navbar).
 */
function renderAuthPageHead(): void
{
    renderSiteFavicon();
    renderSiteFonts();
    renderBootstrapCss();
    renderIconStyles();
    renderAuthPageStyles();
    renderSiteResponsiveStyles();
}

/** User Registration & Trainee dashboard / wallet — portal layout only (no public-site responsive CSS). */
function renderPortalPageHead(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    renderSiteFavicon();
    renderSiteFonts();
    renderBootstrapCss();
    renderIconStyles();
    renderBrandStyles();
    renderPortalStyles();
    if (function_exists('renderPortalResponsiveStyles')) {
        renderPortalResponsiveStyles();
    }
    echo '<style id="cybeorch-portal-forms">'
        . 'body.portal-page .form-control,body.portal-page .form-select{font-size:16px!important;}'
        . 'body.portal-page .input-group{display:flex;width:100%;}'
        . 'body.portal-page .input-group .form-control{flex:1 1 auto;min-width:0;}'
        . '</style>' . "\n";
}

/** Admin panel head — same as auth plus admin layout CSS. */
function renderAdminPageHead(): void
{
    renderAuthPageHead();
    if (function_exists('renderAdminStyles')) {
        renderAdminStyles();
    }
}

/** Bootstrap JS (navbar collapse, modals). Safe to call multiple times. */
function renderSiteScripts(bool $withNavbarScript = false): void
{
    static $bootstrapDone = false;
    if (!$bootstrapDone) {
        $bootstrapDone = true;
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>' . "\n";
    }
    if ($withNavbarScript) {
        renderPublicNavbarScript();
    }
}

/** Standard public page footer scripts. */
function renderPublicPageEnd(bool $withNavbarScript = true): void
{
    if ($withNavbarScript) {
        renderPublicNavbarScript();
    }
    renderSiteScripts(false);
    echo '</body></html>';
}

/**
 * Use in &lt;head&gt; after &lt;title&gt; on public marketing pages.
 * Includes mobile / tablet / laptop / iPhone responsive CSS.
 */
function renderPublicPageHead(): void
{
    renderPublicHeadAssets();
    if (!function_exists('renderPublicFooterStyles')) {
        require_once __DIR__ . '/public-footer.php';
    }
    renderPublicFooterStyles();
}

/** Shared navbar styles — hover dropdowns, layout, active states */
function renderPublicNavbarStyles(): void
{
    renderIconStyles();
    echo '<style>'
        . ':root{--cyber-dark:#050b18;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);}'
        . '.site-header-sticky{position:fixed;top:0;left:0;right:0;width:100%;z-index:1050;margin:0!important;padding:0!important;}'
        . '.site-header-sticky .cybeorch-db-alert{margin:0!important;padding:.5rem 0!important;}'
        . '.site-header-spacer{display:block;width:100%;height:var(--site-header-h,64px);flex-shrink:0;}'
        . '.navbar{background:rgba(5,11,24,0.97)!important;backdrop-filter:blur(20px);border-bottom:1px solid var(--cyber-border);padding:.5rem 0!important;padding-top:.5rem!important;padding-bottom:.5rem!important;position:static!important;top:auto!important;margin:0!important;}'
        . '.navbar .container{padding-top:0!important;margin-top:0!important;}'
        . '.navbar-brand{padding:0;margin-right:1rem;display:flex;align-items:center;flex-shrink:0;}'
        . '.navbar-logo-img{height:42px;width:auto;max-width:min(180px,42vw);object-fit:contain;display:block;filter:drop-shadow(0 4px 12px rgba(0,0,0,.25));}'
        . '.navbar-brand:hover .navbar-logo-img{opacity:.92;}'
        . '.navbar-toggler:focus{box-shadow:0 0 0 2px rgba(0,212,255,0.35);}'
        . '@media(max-width:991.98px){.navbar .container{flex-wrap:wrap;}.navbar-brand{order:0;}.navbar-toggler{order:1;margin-left:auto;}.navbar-collapse{order:2;flex-basis:100%;}}'
        . '.navbar-toggler-icon-custom{color:var(--cyber-accent);font-size:1.25rem;}'
        . '.nav-link{color:var(--cyber-text)!important;font-size:.9rem;font-weight:500;padding:.55rem 1rem!important;transition:color .2s,background .2s;border-radius:4px;}'
        . '.nav-link:hover,.nav-link:focus,.nav-link.active{color:var(--cyber-accent)!important;}'
        . '.dropdown-menu{background:rgba(10,22,40,0.98)!important;border:1px solid var(--cyber-border);border-radius:8px;padding:.5rem 0;min-width:200px;box-shadow:0 12px 40px rgba(0,0,0,.45);}'
        . '.dropdown-item{color:var(--cyber-text)!important;padding:.55rem 1.1rem;font-size:.9rem;transition:background .15s,color .15s;}'
        . '.dropdown-item:hover,.dropdown-item:focus{background:rgba(0,212,255,0.12)!important;color:var(--cyber-accent)!important;}'
        . '.dropdown-item.active{background:rgba(0,212,255,0.15)!important;color:var(--cyber-accent)!important;font-weight:600;}'
        . '.btn-nav-login{display:inline-block;border:1px solid var(--cyber-accent);color:var(--cyber-accent)!important;border-radius:4px;padding:.45rem 1.15rem!important;font-weight:500;text-align:center;transition:all .2s;}'
        . '.btn-nav-login:hover{background:var(--cyber-accent);color:var(--cyber-dark)!important;}'
        . '.btn-nav-register{display:inline-block;background:var(--cyber-accent);color:var(--cyber-dark)!important;border-radius:4px;padding:.45rem 1.15rem!important;font-weight:600;text-align:center;transition:all .2s;}'
        . '.btn-nav-register:hover{background:var(--cyber-green);transform:translateY(-1px);}'
        . '.nav-profile-dropdown{margin-left:.5rem;}'
        . '.nav-profile-btn{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;padding:0;border:1px solid var(--cyber-border);border-radius:50%;background:rgba(0,212,255,0.1);color:var(--cyber-accent);cursor:pointer;transition:all .2s;}'
        . '.nav-profile-btn::after{display:none;}'
        . '.nav-profile-btn:hover,.nav-profile-btn.show{border-color:var(--cyber-accent);background:rgba(0,212,255,0.2);box-shadow:0 0 0 3px rgba(0,212,255,0.15);}'
        . '.nav-profile-avatar{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-family:Rajdhani,sans-serif;font-weight:700;font-size:1.1rem;color:#050b18;background:linear-gradient(135deg,var(--cyber-accent),var(--cyber-green));border-radius:50%;}'
        . '.nav-profile-dropdown .dropdown-header{color:var(--cyber-muted);font-size:.8rem;}'
        . '@media(min-width:992px){'
        . '.nav-profile-dropdown .dropdown-menu{margin-top:.5rem;}'
        . '.navbar .nav-item.dropdown{position:relative;}'
        . '.navbar .dropdown-menu{display:block;opacity:0;visibility:hidden;transform:translateY(6px);transition:opacity .2s ease,transform .2s ease,visibility .2s;pointer-events:none;margin-top:0;}'
        . '.navbar .dropdown-menu::before{content:"";position:absolute;top:-8px;left:0;right:0;height:8px;}'
        . '.navbar .dropdown:hover>.dropdown-menu,.navbar .dropdown-menu.show{opacity:1;visibility:visible;transform:translateY(0);pointer-events:auto;}'
        . '.navbar .dropdown:hover>.nav-link.dropdown-toggle{color:var(--cyber-accent)!important;}'
        . '}'
        . '@media(max-width:991.98px){.navbar .dropdown-menu{background:rgba(10,22,40,0.98)!important;margin-top:.25rem;}}'
        . '</style>' . "\n";
    renderSiteResponsiveStyles();
}

/** CYBEORCH = accent cyan, LAB = white */
function brandMark(bool $withLab = true): string
{
    $lab = $withLab ? '<span class="brand-lab"> LAB</span>' : '';
    return '<span class="brand-cybeorch">CYBEORCH</span>' . $lab;
}

function renderBrandStyles(): void
{
    echo '<style id="cybeorch-brand">'
        . '.brand-mark,.footer-logo,.auth-logo,.sidebar-logo-text,.checkout-logo{font-family:Rajdhani,sans-serif;font-weight:700;letter-spacing:1px;}'
        . '.brand-cybeorch{color:#00d4ff!important;}'
        . '.brand-lab,.auth-logo .logo-white{color:#ffffff!important;}'
        . '.brand-labs{color:#00ff88!important;}'
        . '.brand-academy{color:#ffd166!important;}'
        . '.brand-studio{color:#ff6b35!important;}'
        . '.brand-consult{color:#ffffff!important;}'
        . '.brand-eva{color:#00ff88!important;font-weight:600;}'
        . '.brand-yellow,.solution-title{color:#ffd166!important;}'
        . '.solution-card h3{color:#ffd166!important;}'
        . '</style>' . "\n";
}

/** Desktop hover support for navbar dropdowns */
function renderPublicNavbarScript(): void
{
    echo '<script id="cybeorch-public-nav">'
        . 'document.addEventListener("DOMContentLoaded",function(){'
        . 'function syncHeaderH(){'
        . 'var h=document.querySelector(".site-header-sticky");'
        . 'if(!h)return;'
        . 'var px=h.offsetHeight+"px";'
        . 'document.documentElement.style.setProperty("--site-header-h",px);'
        . 'document.querySelectorAll(".site-header-spacer").forEach(function(s){s.style.height=px;});'
        . '}'
        . 'syncHeaderH();'
        . 'window.addEventListener("resize",syncHeaderH);'
        . 'var d=document.querySelectorAll(".navbar .dropdown");'
        . 'd.forEach(function(el){'
        . 'el.addEventListener("mouseenter",function(){if(window.innerWidth>=992){var m=el.querySelector(".dropdown-menu");if(m)m.classList.add("show");el.querySelector(".dropdown-toggle")?.setAttribute("aria-expanded","true");}});'
        . 'el.addEventListener("mouseleave",function(){if(window.innerWidth>=992){var m=el.querySelector(".dropdown-menu");if(m)m.classList.remove("show");el.querySelector(".dropdown-toggle")?.setAttribute("aria-expanded","false");}});'
        . '});'
        . 'if(window.ResizeObserver){var hdr=document.querySelector(".site-header-sticky");if(hdr)new ResizeObserver(syncHeaderH).observe(hdr);}'
        . '});'
        . '</script>' . "\n";
}
