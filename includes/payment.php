<?php
// ============================================
// CYBEORCH LABS - Razorpay Payment Integration
// ============================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payment-schema.php';
require_once __DIR__ . '/payment-slip.php';

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
    public static function createOrder(
        int $userId,
        float $amount,
        string $payFor,
        int $refId,
        array $notes = [],
        string $currency = 'INR',
        float $nxlTokensUsed = 0,
        float $totalBeforeWallet = 0
    ): array {
        ensurePaymentReceiptSchema();
        if (!razorpayIsConfigured()) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }

        if ($amount <= 0) {
            return ['success' => false, 'message' => paymentUserMessage()];
        }

        $currency = strtoupper(trim($currency));
        if (!in_array($currency, ['INR', 'USD'], true)) {
            $currency = 'INR';
        }

        $orderId = 'CYB_' . strtoupper(uniqid());
        $amountPaise = (int) round($amount * 100);

        // Call Razorpay API to create order
        $payload = json_encode([
            'amount'   => $amountPaise,
            'currency' => $currency,
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

        $invoiceNo  = generateInvoiceNo();
        $receiptNo  = paymentSchemaGenerateReceiptNo();
        $totalFee   = $totalBeforeWallet > 0 ? $totalBeforeWallet : $amount;
        $nxlTokens  = round(max(0, $nxlTokensUsed), 2);
        $nxlInr     = round($nxlTokens * paymentSlipNxlInrValue(), 2);
        $payType    = ($nxlTokens > 0 && $amount > 0) ? 'mixed' : 'online';

        $paymentId = db()->insert(
            "INSERT INTO payments (user_id, order_id, razorpay_order_id, amount, currency, payment_for, reference_id, invoice_no, receipt_no, status, payment_type, nxl_tokens_used, nxl_inr_value, cash_amount, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'created', ?, ?, ?, ?, 'razorpay')",
            [$userId, $orderId, $rzpOrder['id'], $totalFee, $currency, $payFor, $refId, $invoiceNo, $receiptNo, $payType, $nxlTokens, $nxlInr, $amount]
        );

        return [
            'success'          => true,
            'order_id'         => $orderId,
            'razorpay_order_id'=> $rzpOrder['id'],
            'amount_paise'     => $amountPaise,
            'currency'         => $currency,
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

        $userId = (int) $payment['user_id'];
        $refId  = (int) $payment['reference_id'];
        $nxlUsed = (float) ($payment['nxl_tokens_used'] ?? 0);
        $walletTxId = null;
        $nxlRef = null;

        if ($nxlUsed > 0 && empty($payment['wallet_transaction_id'])) {
            require_once __DIR__ . '/nxl-wallet.php';
            $programTitle = self::programTitleForPayment($payment);
            $debit = debitNxlForPayment($userId, $nxlUsed, (string) $payment['payment_for'], $refId, $programTitle);
            if (!$debit['success']) {
                return ['success' => false, 'message' => paymentUserMessage()];
            }
            $walletTxId = (int) ($debit['transaction_id'] ?? 0);
            $nxlRef = paymentSchemaGenerateNxlTransactionRef($walletTxId);
        }

        $payType = paymentSlipResolveType($payment);
        if ($nxlUsed > 0 && (float) ($payment['cash_amount'] ?? 0) > 0) {
            $payType = 'mixed';
        }

        db()->execute(
            "UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'paid', paid_at = NOW(),
             payment_method = 'razorpay', payment_type = ?,
             wallet_transaction_id = COALESCE(wallet_transaction_id, ?),
             nxl_transaction_ref = COALESCE(NULLIF(nxl_transaction_ref, ''), ?)
             WHERE razorpay_order_id = ?",
            [$rzpPaymentId, $rzpSignature, $payType, $walletTxId, $nxlRef, $rzpOrderId]
        );

        $payment = db()->fetchOne('SELECT * FROM payments WHERE razorpay_order_id = ?', [$rzpOrderId]);

        // Update registration status
        if ($payment['payment_for'] === 'webinar') {
            db()->execute(
                "UPDATE webinar_registrations SET payment_id = ?, payment_status = 'paid' WHERE user_id = ? AND webinar_id = ?",
                [$payment['id'], $userId, $refId]
            );
            // Award NxL tokens preview (full award on attendance)
            $paidLabel = (($payment['currency'] ?? 'INR') === 'USD')
                ? '$' . number_format((float) $payment['amount'], 0) . ' USD'
                : '₹' . number_format((float) $payment['amount']);
            sendNotification($userId, 'payment', 'Payment Confirmed ✓',
                'Your webinar registration payment of ' . $paidLabel . ' was successful!', $refId, 'webinar');

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

        self::syncRegistrationNumbers($payment);

        if ($payment) {
            require_once __DIR__ . '/form-submissions.php';
            $user = db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id = ?', [$userId]);
            recordPaymentFormSubmission($payment, $user ?: null);
        }

        return ['success' => true, 'message' => 'Payment verified and processed successfully!', 'data' => $payment];
    }

    /** Record a completed NXL wallet (or wallet-only) payment. */
    public static function recordWalletPayment(
        int $userId,
        string $payFor,
        int $refId,
        float $totalAmountInr,
        float $nxlTokensUsed,
        ?int $walletTransactionId = null,
        string $registrationNo = ''
    ): array {
        ensurePaymentReceiptSchema();

        $nxlTokens = round(max(0, $nxlTokensUsed), 2);
        $nxlInr    = round($nxlTokens * paymentSlipNxlInrValue(), 2);
        $orderId   = 'CYB_WLT_' . strtoupper(uniqid());
        $invoiceNo = generateInvoiceNo();
        $receiptNo = paymentSchemaGenerateReceiptNo();
        $nxlRef    = $walletTransactionId
            ? paymentSchemaGenerateNxlTransactionRef($walletTransactionId)
            : paymentSchemaGenerateNxlTransactionRef();

        $paymentId = db()->insert(
            "INSERT INTO payments (user_id, order_id, amount, currency, payment_for, reference_id, status, invoice_no, receipt_no,
             payment_method, payment_type, nxl_tokens_used, nxl_inr_value, cash_amount, wallet_transaction_id, nxl_transaction_ref, registration_no, paid_at)
             VALUES (?, ?, ?, 'INR', ?, ?, 'paid', ?, ?, 'nxl_wallet', 'nxl_token', ?, ?, 0, ?, ?, ?, NOW())",
            [
                $userId, $orderId, $totalAmountInr, $payFor, $refId, $invoiceNo, $receiptNo,
                $nxlTokens, $nxlInr, $walletTransactionId, $nxlRef, $registrationNo ?: null,
            ]
        );

        return [
            'success'    => true,
            'payment_id' => $paymentId,
            'receipt_no' => $receiptNo,
            'order_id'   => $orderId,
        ];
    }

    private static function programTitleForPayment(array $payment): string
    {
        $for = (string) ($payment['payment_for'] ?? '');
        $ref = (int) ($payment['reference_id'] ?? 0);
        if ($for === 'webinar' && $ref > 0) {
            return (string) (db()->fetchOne('SELECT title FROM webinars WHERE id = ?', [$ref])['title'] ?? 'Webinar');
        }
        if ($for === 'bootcamp' && $ref > 0) {
            return (string) (db()->fetchOne('SELECT title FROM bootcamps WHERE id = ?', [$ref])['title'] ?? 'Bootcamp');
        }
        return 'Program';
    }

    private static function syncRegistrationNumbers(?array $payment): void
    {
        if (!$payment || empty($payment['id'])) {
            return;
        }
        $pid = (int) $payment['id'];
        $regNo = '';
        if ($payment['payment_for'] === 'webinar') {
            $row = db()->fetchOne('SELECT registration_no FROM webinar_registrations WHERE payment_id = ?', [$pid]);
            $regNo = (string) ($row['registration_no'] ?? '');
        } elseif ($payment['payment_for'] === 'bootcamp') {
            $row = db()->fetchOne('SELECT enrollment_no FROM bootcamp_enrollments WHERE payment_id = ?', [$pid]);
            $regNo = (string) ($row['enrollment_no'] ?? '');
        }
        if ($regNo !== '') {
            db()->execute('UPDATE payments SET registration_no = ? WHERE id = ? AND (registration_no IS NULL OR registration_no = \'\')', [$regNo, $pid]);
        }
    }

    private static function processReferralReward(int $userId): void
    {
        require_once __DIR__ . '/nxl-wallet.php';
        processReferralRewardForReferredUser($userId, true);
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
            require_once __DIR__ . '/webinar-registration-service.php';
            $existing = fetchActiveWebinarRegistrationByUser($userId, $refId);
            if ($existing) {
                return false;
            }
            $regNo = generateRegNo('CYB-W');
            $regId = db()->insert(
                'INSERT INTO webinar_registrations (user_id, webinar_id, registration_no, payment_id, payment_status) VALUES (?,?,?,?,?)',
                [$userId, $refId, $regNo, $paymentId, $paymentStatus]
            );
            // Seats booked must be derived from webinar_registrations (no caching).
            $title = db()->fetchOne('SELECT title FROM webinars WHERE id = ?', [$refId])['title'] ?? 'webinar';
            sendNotification($userId, 'registration', 'Registration confirmed', "You are registered for: {$title}");
            $user = db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id = ?', [$userId]);
            require_once __DIR__ . '/form-submissions.php';
            recordWebinarRegistrationForm($regId, [
                'full_name'       => $user['full_name'] ?? '',
                'email'           => $user['email'] ?? '',
                'phone'           => $user['phone'] ?? '',
                'title'           => $title,
                'registration_no' => $regNo,
                'payment_status'  => $paymentStatus,
                'webinar_id'      => $refId,
            ]);
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
        $enrollId = db()->insert(
            'INSERT INTO bootcamp_enrollments (user_id, bootcamp_id, enrollment_no, payment_id, payment_status) VALUES (?,?,?,?,?)',
            [$userId, $refId, $regNo, $paymentId, $paymentStatus]
        );
        db()->execute('UPDATE bootcamps SET enrolled_seats = enrolled_seats + 1 WHERE id = ?', [$refId]);
        creditWallet($userId, NXL_BOOTCAMP_REWARD, 'bootcamp_reward', $refId, 'Bootcamp enrollment NxL reward!');
        $title = db()->fetchOne('SELECT title FROM bootcamps WHERE id = ?', [$refId])['title'] ?? 'bootcamp';
        sendNotification($userId, 'payment', 'Enrollment confirmed', "You are enrolled in: {$title}");
        $user = db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id = ?', [$userId]);
        require_once __DIR__ . '/form-submissions.php';
        recordBootcampRegistrationForm($enrollId, [
            'full_name'     => $user['full_name'] ?? '',
            'email'         => $user['email'] ?? '',
            'phone'         => $user['phone'] ?? '',
            'title'         => $title,
            'enrollment_no' => $regNo,
            'payment_status'=> $paymentStatus,
            'bootcamp_id'   => $refId,
        ]);
        return true;
    }

    // Get payment history for user (paid & refunded receipts only)
    public static function getHistory(int $userId): array
    {
        ensurePaymentReceiptSchema();
        ensureAdminActionsSchema();

        $deletedClause = adminTableHasColumn('payments', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';

        return db()->fetchAll(
            "SELECT p.*,
                CASE p.payment_for
                    WHEN 'webinar' THEN (SELECT title FROM webinars WHERE id = p.reference_id)
                    WHEN 'bootcamp' THEN (SELECT title FROM bootcamps WHERE id = p.reference_id)
                    ELSE 'Wallet Top-up'
                END AS item_title
             FROM payments p
             WHERE p.user_id = ?
               AND p.status IN ('paid', 'refunded')
               {$deletedClause}
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC",
            [$userId]
        );
    }
}
