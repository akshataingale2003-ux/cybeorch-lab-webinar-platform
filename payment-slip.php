<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment-slip.php';

startSession();
requireLogin();

$paymentId = (int) ($_GET['id'] ?? 0);
$format    = strtolower(trim((string) ($_GET['format'] ?? 'html')));
$userId    = (int) ($_SESSION['user_id'] ?? 0);

$adminBypass = function_exists('isAdminLoggedIn') && isAdminLoggedIn();
$data = paymentSlipLoad($paymentId, $userId, $adminBypass);

if (!$data) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:2rem;text-align:center"><h1>Receipt not found</h1><p>This payment receipt is unavailable or you do not have access.</p><a href="' . htmlspecialchars(url('payment-history.php')) . '">Back to Payment History</a></body></html>';
    exit;
}

if ($format === 'pdf') {
    paymentSlipOutputPdf($data);
}

paymentSlipOutputHtml($data);
