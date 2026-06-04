<?php
declare(strict_types=1);
define('CYBEORCH_JSON_API', true);
ob_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/popup-registration.php';
require_once __DIR__ . '/../includes/webinar-registration-service.php';
startSession();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    otpJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    otpJsonResponse(['success' => false, 'message' => 'Invalid session. Refresh the page.'], 403);
}
$webinarId = (int) ($_POST['webinar_id'] ?? 0);
$token = trim((string) ($_POST['token'] ?? $_SESSION[WEBINAR_INTAKE_SESSION_KEY] ?? ''));
if ($webinarId < 1 || $token === '') {
    otpJsonResponse(['success' => false, 'message' => 'Registration session expired. Please start again from the webinars page.'], 400);
}
try {
    $result = completeFreeWebinarRegistrationFromIntake($token, $webinarId);
    otpJsonResponse([
        'success' => $result['success'],
        'message' => $result['message'],
        'redirect' => $result['redirect'] ?? null,
    ], $result['success'] ? 200 : 400);
} catch (Throwable $e) {
    $msg = function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev() ? $e->getMessage() : 'Server error. Please try again.';
    otpJsonResponse(['success' => false, 'message' => $msg], 500);
}
