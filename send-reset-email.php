<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/password-reset.php';

rejectWhenPublicAuthDisabled();

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('forgot-password.php'));
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid request. Please refresh the page and try again.');
    header('Location: ' . url('forgot-password.php'));
    exit;
}

if (trim((string) ($_POST['company_url'] ?? '')) !== '') {
    setFlash('error', 'Invalid request.');
    header('Location: ' . url('forgot-password.php'));
    exit;
}

$email  = trim((string) ($_POST['email'] ?? ''));
$result = requestPasswordReset($email);

if (!$result['success']) {
    setFlash('error', $result['message']);
    header('Location: ' . url('forgot-password.php'));
    exit;
}

if (!empty($result['reset_link'])) {
    $_SESSION['password_reset_dev_link'] = (string) $result['reset_link'];
}

setFlash('success', $result['message']);
header('Location: ' . url('forgot-password.php'));
exit;
