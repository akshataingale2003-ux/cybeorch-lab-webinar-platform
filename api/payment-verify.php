<?php
// ============================================
// CYBEORCH LAB - Payment Verification API
// ============================================

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/payment.php';

startSession();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('')); exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    redirectWith('/dashboard.php', 'error', 'Invalid request. Please try again.');
}

$rzpOrderId   = trim($_POST['razorpay_order_id']   ?? '');
$rzpPaymentId = trim($_POST['razorpay_payment_id']  ?? '');
$rzpSignature = trim($_POST['razorpay_signature']   ?? '');

if (empty($rzpOrderId) || empty($rzpPaymentId) || empty($rzpSignature)) {
    redirectWith('dashboard.php', 'error', paymentUserMessage());
}

$result = Payment::processSuccess($rzpOrderId, $rzpPaymentId, $rzpSignature);

if ($result['success']) {
    // Mark webinar registration seat
    $payment = $result['data'];
    if ($payment['payment_for'] === 'webinar') {
        $regNo = generateRegNo('CYB-W');
        // Insert registration if not exists
        $existing = db()->fetchOne(
            "SELECT id FROM webinar_registrations WHERE user_id = ? AND webinar_id = ?",
            [$_SESSION['user_id'], $payment['reference_id']]
        );
        if (!$existing) {
            db()->execute(
                "INSERT INTO webinar_registrations (user_id, webinar_id, registration_no, payment_id, payment_status) VALUES (?,?,?,?,'paid')",
                [$_SESSION['user_id'], $payment['reference_id'], $regNo, $payment['id']]
            );
            db()->execute(
                "UPDATE webinars SET registered_seats = registered_seats + 1 WHERE id = ?",
                [$payment['reference_id']]
            );
        }
        header('Location: ' . url('payment-success.php?type=webinar&ref=' . urlencode($rzpPaymentId)));
    } else {
        $regNo = generateRegNo('CYB-B');
        $existing = db()->fetchOne(
            "SELECT id FROM bootcamp_enrollments WHERE user_id = ? AND bootcamp_id = ?",
            [$_SESSION['user_id'], $payment['reference_id']]
        );
        if (!$existing) {
            db()->execute(
                "INSERT INTO bootcamp_enrollments (user_id, bootcamp_id, enrollment_no, payment_id, payment_status) VALUES (?,?,?,?,'paid')",
                [$_SESSION['user_id'], $payment['reference_id'], $regNo, $payment['id']]
            );
            db()->execute(
                "UPDATE bootcamps SET enrolled_seats = enrolled_seats + 1 WHERE id = ?",
                [$payment['reference_id']]
            );
        }
        header('Location: ' . url('payment-success.php?type=bootcamp&ref=' . urlencode($rzpPaymentId)));
    }
    exit;
} else {
    redirectWith('dashboard.php', 'error', paymentUserMessage());
}
