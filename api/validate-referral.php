<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/referral-helpers.php';

rejectWhenPublicAuthDisabled(true);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'message' => 'Method not allowed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

startSession();

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['valid' => false, 'message' => 'Invalid session. Refresh the page.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$code = (string) ($_POST['referral_code'] ?? '');
$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if (normalizeReferralCodeInput($code) === '') {
    echo json_encode(['valid' => true, 'message' => ''], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = resolveReferrerFromCode($code, $email);

echo json_encode([
    'valid'   => $result['ok'],
    'message' => $result['ok'] ? 'Referral code looks good.' : $result['message'],
], JSON_UNESCAPED_UNICODE);
