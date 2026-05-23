<?php
// ============================================
// CYBEORCH LAB - Razorpay Payment Integration
// ============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** Generic message shown to users when payment cannot be completed (no internal details). */
function paymentUserMessage(): string
{
    return 'We\'re unable to process your payment right now. Please try again in a few minutes, or contact our support team if you need help.';
}

function razorpayIsConfigured(): bool
{
    $keyId = defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : '';
    $secret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : '';

    if ($keyId === '' || $secret === '') {
        return false;
    }

    return !str_contains($keyId, 'XXXX')
        && !str_contains($secret, 'XXXX')
        && $keyId !== 'rzp_test_XXXXXXXXXXXXXXX'
        && $secret !== 'XXXXXXXXXXXXXXXXXXXXXXXX';
}

class Payment {

    // Create Razorpay Order
    public static function createOrder(int $userId, float $amount, string $payFor, int $refId, array $notes = []): array {
        if (!razorpayIsConfigured()) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }
        $orderId = 'CYB_' . strtoupper(uniqid());
        $amountPaise = (int)($amount * 100); // Razorpay uses paise

        // Call Razorpay API to create order
        $payload = json_encode([
            'amount'   => $amountPaise,
            'currency' => RAZORPAY_CURRENCY,
            'receipt'  => $orderId,
            'notes'    => $notes,
        ]);

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }

        $rzpOrder = json_decode((string) $response, true);
        if (!is_array($rzpOrder) || empty($rzpOrder['id'])) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }

        // Save order in DB
        $invoiceNo = generateInvoiceNo();
        $paymentId = db()->insert(
            "INSERT INTO payments (user_id, order_id, razorpay_order_id, amount, payment_for, reference_id, invoice_no, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'created')",
            [$userId, $orderId, $rzpOrder['id'], $amount, $payFor, $refId, $invoiceNo]
        );

        return [
            'success'          => true,
            'order_id'         => $orderId,
            'razorpay_order_id'=> $rzpOrder['id'],
            'amount_paise'     => $amountPaise,
            'payment_db_id'    => $paymentId,
        ];
    }

    // Verify Razorpay Signature
    public static function verifyPayment(string $rzpOrderId, string $rzpPaymentId, string $rzpSignature): bool {
        $expectedSignature = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, RAZORPAY_KEY_SECRET);
        return hash_equals($expectedSignature, $rzpSignature);
    }

    // Process payment success
    public static function processSuccess(string $rzpOrderId, string $rzpPaymentId, string $rzpSignature): array {
        // Verify signature first
        if (!self::verifyPayment($rzpOrderId, $rzpPaymentId, $rzpSignature)) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }

        // Get payment record
        $payment = db()->fetchOne("SELECT * FROM payments WHERE razorpay_order_id = ?", [$rzpOrderId]);
        if (!$payment) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }
        if ($payment['status'] === 'paid')
            return ['success' => true, 'message' => 'Already processed.', 'data' => $payment];

        // Update payment record
        db()->execute(
            "UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'paid', paid_at = NOW() WHERE razorpay_order_id = ?",
            [$rzpPaymentId, $rzpSignature, $rzpOrderId]
        );

        $userId = $payment['user_id'];
        $refId  = $payment['reference_id'];

        // Update registration status
        if ($payment['payment_for'] === 'webinar') {
            db()->execute(
                "UPDATE webinar_registrations SET payment_id = ?, payment_status = 'paid' WHERE user_id = ? AND webinar_id = ?",
                [$payment['id'], $userId, $refId]
            );
            // Award NxL tokens preview (full award on attendance)
            sendNotification($userId, 'payment', 'Payment Confirmed ✓',
                'Your webinar registration payment of ₹' . $payment['amount'] . ' was successful!', $refId, 'webinar');

        } elseif ($payment['payment_for'] === 'bootcamp') {
            db()->execute(
                "UPDATE bootcamp_enrollments SET payment_id = ?, payment_status = 'paid' WHERE user_id = ? AND bootcamp_id = ?",
                [$payment['id'], $userId, $refId]
            );
            // Award bootcamp NxL tokens
            creditWallet($userId, NXL_BOOTCAMP_REWARD, 'bootcamp_reward', $refId, 'Enrolled in bootcamp - NxL reward!');
            sendNotification($userId, 'payment', 'Bootcamp Enrollment Confirmed! 🎓',
                'Payment successful! You are now enrolled. You earned ' . NXL_BOOTCAMP_REWARD . ' NxL tokens!', $refId, 'bootcamp');
        }

        // Process referral reward if applicable
        self::processReferralReward($userId);

        return ['success' => true, 'message' => 'Payment verified and processed successfully!', 'data' => $payment];
    }

    // Process referral bonus
    private static function processReferralReward(int $userId): void {
        $referral = db()->fetchOne(
            "SELECT r.*, u.full_name as referrer_name FROM referrals r JOIN users u ON u.id = r.referrer_id WHERE r.referred_id = ? AND r.status = 'pending'",
            [$userId]
        );
        if ($referral) {
            creditWallet($referral['referrer_id'], NXL_REFERRAL_BONUS, 'referral_bonus', $userId,
                "Referral bonus - your friend joined CYBEORCH LAB!");
            db()->execute("UPDATE referrals SET status = 'rewarded', rewarded_at = NOW() WHERE id = ?", [$referral['id']]);
            sendNotification($referral['referrer_id'], 'referral', '🎉 Referral Bonus Credited!',
                'You earned ' . NXL_REFERRAL_BONUS . ' NxL tokens for referring a friend!');
        }
    }

    /** Complete webinar/bootcamp registration after free checkout or local demo payment. */
    public static function completeRegistration(
        int $userId,
        string $type,
        int $refId,
        string $paymentStatus = 'paid',
        ?int $paymentId = null
    ): bool {
        if ($type === 'webinar') {
            $existing = db()->fetchOne(
                'SELECT id FROM webinar_registrations WHERE user_id = ? AND webinar_id = ?',
                [$userId, $refId]
            );
            if ($existing) {
                return false;
            }
            $regNo = generateRegNo('CYB-W');
            db()->execute(
                'INSERT INTO webinar_registrations (user_id, webinar_id, registration_no, payment_id, payment_status) VALUES (?,?,?,?,?)',
                [$userId, $refId, $regNo, $paymentId, $paymentStatus]
            );
            db()->execute('UPDATE webinars SET registered_seats = registered_seats + 1 WHERE id = ?', [$refId]);
            $title = db()->fetchOne('SELECT title FROM webinars WHERE id = ?', [$refId])['title'] ?? 'webinar';
            sendNotification($userId, 'registration', 'Registration confirmed', "You are registered for: {$title}");
            return true;
        }

        $existing = db()->fetchOne(
            'SELECT id FROM bootcamp_enrollments WHERE user_id = ? AND bootcamp_id = ?',
            [$userId, $refId]
        );
        if ($existing) {
            return false;
        }
        $regNo = generateRegNo('CYB-B');
        db()->execute(
            'INSERT INTO bootcamp_enrollments (user_id, bootcamp_id, enrollment_no, payment_id, payment_status) VALUES (?,?,?,?,?)',
            [$userId, $refId, $regNo, $paymentId, $paymentStatus]
        );
        db()->execute('UPDATE bootcamps SET enrolled_seats = enrolled_seats + 1 WHERE id = ?', [$refId]);
        creditWallet($userId, NXL_BOOTCAMP_REWARD, 'bootcamp_reward', $refId, 'Bootcamp enrollment NxL reward!');
        $title = db()->fetchOne('SELECT title FROM bootcamps WHERE id = ?', [$refId])['title'] ?? 'bootcamp';
        sendNotification($userId, 'payment', 'Enrollment confirmed', "You are enrolled in: {$title}");
        return true;
    }

    // Get payment history for user
    public static function getHistory(int $userId): array {
        return db()->fetchAll(
            "SELECT p.*, 
                CASE p.payment_for 
                    WHEN 'webinar' THEN (SELECT title FROM webinars WHERE id = p.reference_id)
                    WHEN 'bootcamp' THEN (SELECT title FROM bootcamps WHERE id = p.reference_id)
                    ELSE 'Wallet Top-up' 
                END as item_title
             FROM payments p WHERE p.user_id = ? ORDER BY p.created_at DESC",
            [$userId]
        );
    }
}
