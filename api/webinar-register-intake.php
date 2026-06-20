<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/webinar-registration-service.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    otpJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    otpJsonResponse(['success' => false, 'message' => 'Invalid session. Refresh the page.'], 403);
}

$webinarId = (int) ($_POST['webinar_id'] ?? 0);
if ($webinarId < 1) {
    otpJsonResponse(['success' => false, 'message' => 'Invalid webinar.'], 400);
}

$loggedInUserId = isLoggedIn() ? (int) $_SESSION['user_id'] : null;

try {
    $result = saveWebinarRegistrationIntake($webinarId, [
        'full_name'    => (string) ($_POST['full_name'] ?? ''),
        'email'        => (string) ($_POST['email'] ?? ''),
        'mobile'       => (string) ($_POST['mobile'] ?? ''),
        'city'         => (string) ($_POST['city'] ?? ''),
        'organization' => (string) ($_POST['organization'] ?? ''),
    ], $loggedInUserId);

    otpJsonResponse([
        'success'  => $result['success'],
        'message'  => $result['message'],
        'field'    => $result['field'] ?? null,
        'redirect' => $result['redirect'] ?? null,
    ], $result['success'] ? 200 : 400);
} catch (Throwable $e) {
    $msg = function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev()
        ? $e->getMessage()
        : 'Server error. Please try again.';
    otpJsonResponse(['success' => false, 'message' => $msg], 500);
}
