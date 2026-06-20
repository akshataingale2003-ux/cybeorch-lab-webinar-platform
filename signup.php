<?php
/**
 * Legacy signup URL — redirects to the main registration page.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isPublicAuthEnabled()) {
    header('Location: ' . url('index.php'));
    exit;
}

$ref = isset($_GET['ref']) ? '&ref=' . urlencode(sanitize($_GET['ref'])) : '';
$redirect = isset($_GET['redirect']) ? '&redirect=' . urlencode(sanitize($_GET['redirect'])) : '';
header('Location: ' . url('login.php?signup=1' . $ref . $redirect));
exit;
