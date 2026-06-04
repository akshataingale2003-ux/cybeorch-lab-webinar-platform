<?php
declare(strict_types=1);

/**
 * Site-wide session auth (loaded from config.php on every web request).
 *
 * Public (no login): index.php (landing/home) + guest auth pages.
 * Everything else: redirect to login.php with ?redirect= return URL.
 * Admin: handled separately via requireAdminLogin() on /admin/* pages.
 */
function enforcePublicSiteAuth(): void
{
    if (!isPublicAuthEnabled() || PHP_SAPI === 'cli') {
        return;
    }

    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    if (str_contains($scriptPath, '/admin/')) {
        return;
    }
    if (str_contains($scriptPath, '/api/')) {
        return;
    }

    if (in_array($script, ['send_otp.php', 'verify_otp.php', 'setup-smtp.php'], true)) {
        return;
    }

    $utilityPages = [
        'install_fontawesome.php',
        'reset_admin_password.php',
        'favicon.php',
    ];
    if (in_array($script, $utilityPages, true)) {
        return;
    }

    $guestAuthPages = [
        'login.php',
        'signup.php',
        'register-website.php',
        'forgot-password.php',
        'logout.php',
        'oauth-start.php',
        'oauth-callback.php',
        'send_magic_link.php',
        'magic-login.php',
    ];
    if (in_array($script, $guestAuthPages, true)) {
        if ($script !== 'logout.php' && isLoggedIn()) {
            $dest = $script === 'register-website.php'
                ? 'dashboard.php'
                : loginSuccessRedirectPath();
            header('Location: ' . url($dest));
            exit;
        }
        return;
    }

    /** Homepage + popup POST must load without registration (avoids redirect loop). */
    if (in_array($script, ['index.php', 'register-website.php'], true)) {
        return;
    }

    /**
     * Public catalog & marketing pages (no website popup, no trainee login).
     * Admin-managed webinars, bootcamps, live projects, assignments, etc. must be visible here.
     */
    $publicCatalogPages = [
        'webinars.php',
        'bootcamps.php',
        'checkout.php',
        'products.php',
        'services.php',
        'aboutus.php',
        'about.php',
        'contact.php',
        'terms.php',
        'privacy.php',
        'freelancer.php',
        'freelance-projects.php',
        'live-projects.php',
        'hands-on-projects.php',
        'enquire-enroll.php',
        'secure-payment.php',
        'get-started.php',
        'assignment-register.php',
        'start-project.php',
        'book-consulting.php',
        'webinar-registration-confirm.php',
        'registration-success.php',
    ];
    if (in_array($script, $publicCatalogPages, true)) {
        return;
    }

    /** Remaining pages require website popup registration first. */
    if (!hasWebsiteAccess()) {
        header('Location: ' . url('index.php?register_required=1'));
        exit;
    }

    /** Dashboard, wallet, profile, etc. require user registration & trainee session (created on popup register). */
    if (!isLoggedIn()) {
        $return = publicAuthReturnPath();
        $login = url('login.php');
        if ($return !== '') {
            $login .= '?redirect=' . rawurlencode($return);
        }
        header('Location: ' . $login);
        exit;
    }
}

function publicAuthReturnPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $base = BASE_PATH;
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    $path = ltrim(parse_url($uri, PHP_URL_PATH) ?: '', '/');
    if ($path === '' || $path === 'index.php') {
        return 'index.php';
    }
    if (str_contains($path, '..') || preg_match('#^[a-z]+://#i', $path)) {
        return 'index.php';
    }
    $query = parse_url($uri, PHP_URL_QUERY);
    return $query ? $path . '?' . $query : $path;
}

/** Where to send users after a successful user registration & trainee login. */
function loginSuccessRedirectPath(): string
{
    return 'dashboard.php';
}

function safeRedirectPath(string $path): string
{
    $path = ltrim($path, '/');
    if ($path === '' || str_contains($path, '..') || preg_match('#^[a-z]+://#i', $path)) {
        return 'index.php';
    }
    $base = explode('?', $path)[0];
    $forceHome = [
        'login.php',
        'logout.php',
        'forgot-password.php',
        'about.php',
        'aboutus.php',
    ];
    if (in_array($base, $forceHome, true)) {
        return 'index.php';
    }
    return $path;
}
