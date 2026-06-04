<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth-registration-popup.php';

rejectWhenPublicAuthDisabled(true);

startSession();

header('Content-Type: application/json; charset=UTF-8');

if (!isPublicAuthEnabled()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Registration is unavailable.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isLoggedIn()) {
    echo json_encode([
        'success'  => false,
        'message'  => 'You are already signed in.',
        'redirect' => url('dashboard.php'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$redirect = safeRedirectPath((string) ($_GET['redirect'] ?? 'index.php'));
$config = buildAuthRegistrationPopupConfig([
    'ref_code'               => sanitize($_GET['ref'] ?? ''),
    'login_redirect'         => $redirect,
    'success_url'            => authRegistrationSuccessUrl($redirect),
    'banner_tagline'         => trim((string) ($_GET['tagline'] ?? '')),
    'show_register_required' => !empty($_GET['register_required']),
]);

echo json_encode([
    'success' => true,
    'html'    => captureAuthRegistrationPopupHtml($config),
    'config'  => authRegistrationPopupClientConfig($config),
], JSON_UNESCAPED_UNICODE);
