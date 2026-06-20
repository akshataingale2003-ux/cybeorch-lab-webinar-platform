<?php
declare(strict_types=1);

define('CYBEORCH_ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/admin-password-reset.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . adminUrl('forgot-password.php'));
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request. Please refresh the page and try again.');
    header('Location: ' . adminUrl('forgot-password.php'));
    exit;
}

if (trim((string) ($_POST['company_url'] ?? '')) !== '') {
    setFlash('error', 'Invalid request.');
    header('Location: ' . adminUrl('forgot-password.php'));
    exit;
}

$result = requestPrimaryAdminPasswordReset();

if (!$result['success']) {
    setFlash('error', $result['message']);
    header('Location: ' . adminUrl('forgot-password.php'));
    exit;
}

setFlash('success', $result['message']);
header('Location: ' . adminUrl('forgot-password.php'));
exit;
