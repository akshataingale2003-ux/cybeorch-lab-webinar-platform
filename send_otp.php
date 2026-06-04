<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/popup-registration.php';

rejectWhenPublicAuthDisabled(true);

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
    if (!empty($r['email_sent'])) {
        $out['email_sent'] = true;
        $out['otp_sent']   = true;
    }

    otpJsonResponse($out);
} catch (Throwable $e) {
    if (function_exists('mailLog')) {
        mailLog('error', 'send_otp', $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    error_log('OTP Error: ' . $e->getMessage());
    $msg = 'Server error. Try again.';
    if (function_exists('cybeorchIsLocalDev') && cybeorchIsLocalDev()) {
        $msg .= ' (' . $e->getMessage() . ')';
    }
    otpJsonResponse(['success' => false, 'message' => $msg], 500);
}
