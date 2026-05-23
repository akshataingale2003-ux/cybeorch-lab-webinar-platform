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
    $r = sendPopupRegistrationOtp(
        (string) ($_POST['full_name'] ?? ''),
        (string) ($_POST['email'] ?? ''),
        (string) ($_POST['mobile'] ?? ''),
        !empty($_POST['resend'])
    );

    $out = [
        'success' => $r['success'],
        'message' => $r['message'],
    ];
    if (!empty($r['expires_at'])) {
        $out['expires_at'] = (int) $r['expires_at'];
    }
    if (!empty($r['dev_otp'])) {
        $out['dev_otp'] = $r['dev_otp'];
    }
    if ($r['success']) {
        $out['otp_sent'] = true;
    }

    otpJsonResponse($out);
} catch (Throwable $e) {
    otpJsonResponse(['success' => false, 'message' => 'Server error. Try again.'], 500);
}
