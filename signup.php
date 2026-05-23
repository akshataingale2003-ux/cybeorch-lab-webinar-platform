<?php
/**
 * Legacy signup URL — redirects to the main registration page.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

$ref = isset($_GET['ref']) ? '&ref=' . urlencode(sanitize($_GET['ref'])) : '';
header('Location: ' . url('login.php?signup=1' . $ref));
exit;
