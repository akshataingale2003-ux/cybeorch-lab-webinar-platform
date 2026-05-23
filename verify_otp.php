<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/popup-registration.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    otpJsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    otpJsonResponse(['success' => false, 'message' => 'Invalid session. Refresh the page.'], 403);
}

if (trim((string) ($_POST['company_url'] ?? '')) !== '') {
    otpJsonResponse(['success' => false, 'message' => 'Invalid request.'], 400);
}

try {
    $r = verifyPopupRegistrationOtp((string) ($_POST['otp'] ?? ''));

    if (!$r['success']) {
        unset($_SESSION[REG_OTP_VERIFIED_KEY]);
    }

    otpJsonResponse([
        'success'      => $r['success'],
        'message'      => $r['message'],
        'otp_verified' => $r['success'],
    ]);
} catch (Throwable $e) {
    otpJsonResponse(['success' => false, 'message' => 'Server error. Try again.'], 500);
}
