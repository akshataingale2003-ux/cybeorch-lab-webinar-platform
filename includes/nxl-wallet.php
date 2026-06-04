<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/** @return array<string, float> */
function nxlRewardAmounts(): array
{
    return [
        'signup_bonus'    => (float) NXL_SIGNUP_BONUS,
        'webinar_reward'  => (float) NXL_WEBINAR_REWARD,
        'referral_bonus'  => (float) NXL_REFERRAL_BONUS,
        'bootcamp_reward' => (float) NXL_BOOTCAMP_REWARD,
        'special_reward'  => (float) NXL_BOOTCAMP_REWARD,
    ];
}

/** @return array<string, string> */
function nxlRewardTypeLabels(): array
{
    return [
        'signup_bonus'    => 'Sign Up Bonus',
        'webinar_reward'  => 'Webinar Attended',
        'referral_bonus'  => 'Successful Referral',
        'bootcamp_reward' => 'Bootcamp Enrollment',
        'special_reward'  => 'Special Reward',
        'admin_credit'    => 'Admin Credit',
        'redemption'      => 'Redemption',
        'cashback'        => 'Cashback',
    ];
}

function ensureNxlWalletSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute('CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL DEFAULT \'wallet\',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        user_id INT DEFAULT NULL,
        amount DECIMAL(10,2) DEFAULT NULL,
        reward_type VARCHAR(50) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_notif_read (is_read, created_at),
        INDEX idx_admin_notif_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    foreach (['reward_type' => 'VARCHAR(50) DEFAULT NULL', 'remarks' => 'VARCHAR(500) DEFAULT NULL'] as $col => $def) {
        try {
            $exists = (int) (db()->fetchOne(
                'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [DB_NAME, 'wallet_transactions', $col]
            )['c'] ?? 0);
            if ($exists === 0) {
                db()->execute("ALTER TABLE wallet_transactions ADD COLUMN {$col} {$def}");
            }
        } catch (Throwable $e) {
            // ignore migration races
        }
    }

    try {
        db()->execute("ALTER TABLE wallet_transactions MODIFY COLUMN reason ENUM(
            'webinar_reward','referral_bonus','bootcamp_reward','admin_credit',
            'redemption','cashback','signup_bonus','special_reward'
        ) NOT NULL");
    } catch (Throwable $e) {
        // existing DB may already match
    }
}

function nxlRewardLabel(string $reason): string
{
    return nxlRewardTypeLabels()[$reason] ?? ucwords(str_replace('_', ' ', $reason));
}

function nxlRewardPopupMessage(string $reason, float $amount): string
{
    $n = (int) round($amount);
    return match ($reason) {
        'signup_bonus'   => "🎉 Congratulations! {$n} NxL Tokens have been added to your wallet.",
        'webinar_reward' => "🎉 {$n} NxL Tokens credited for webinar participation.",
        'referral_bonus' => "🎉 {$n} NxL Tokens credited for successful referral.",
        'bootcamp_reward', 'special_reward' => "🎉 {$n} NxL Tokens credited to your wallet.",
        default          => "🎉 {$n} NxL Tokens have been added to your wallet.",
    };
}

function hasNxlRewardBeenGranted(int $userId, string $reason, ?int $referenceId = null): bool
{
    ensureNxlWalletSchema();
    $sql = 'SELECT id FROM wallet_transactions WHERE user_id = ? AND reason = ? AND type = ?';
    $params = [$userId, $reason, 'credit'];
    if ($referenceId !== null) {
        $sql .= ' AND reference_id = ?';
        $params[] = $referenceId;
    } elseif (in_array($reason, ['signup_bonus'], true)) {
        $sql .= ' AND (reference_id IS NULL OR reference_id = 0)';
    }
    $row = db()->fetchOne($sql . ' LIMIT 1', $params);

    return (bool) $row;
}

function queueNxlRewardPopup(string $message): void
{
    startSession();
    if (!isset($_SESSION['nxl_reward_popups']) || !is_array($_SESSION['nxl_reward_popups'])) {
        $_SESSION['nxl_reward_popups'] = [];
    }
    $_SESSION['nxl_reward_popups'][] = $message;
}

/** @return list<string> */
function consumeNxlRewardPopups(): array
{
    startSession();
    $popups = $_SESSION['nxl_reward_popups'] ?? [];
    unset($_SESSION['nxl_reward_popups']);
    if (!is_array($popups)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $popups)));
}

function notifyAdminNxlCredit(int $userId, float $amount, string $reason, ?string $remarks = null): void
{
    ensureNxlWalletSchema();
    $user = db()->fetchOne('SELECT full_name FROM users WHERE id = ?', [$userId]);
    $name = trim((string) ($user['full_name'] ?? 'A user'));
    $label = nxlRewardLabel($reason);
    $n = (int) round($amount);
    $message = "{$name} earned {$n} NxL Tokens ({$label}).";
    if ($remarks !== null && $remarks !== '') {
        $message .= ' ' . $remarks;
    }

    db()->execute(
        'INSERT INTO admin_notifications (type, title, message, user_id, amount, reward_type, is_read) VALUES (?,?,?,?,?,?,0)',
        ['wallet', 'NxL Tokens Credited', $message, $userId, $amount, $reason]
    );
}

function getAdminUnreadNotificationCount(): int
{
    ensureNxlWalletSchema();

    return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM admin_notifications WHERE is_read = 0')['c'] ?? 0);
}

/** @return list<array<string, mixed>> */
function fetchRecentAdminNotifications(int $limit = 10): array
{
    ensureNxlWalletSchema();
    $limit = max(1, min(50, $limit));

    return db()->fetchAll(
        'SELECT * FROM admin_notifications ORDER BY created_at DESC LIMIT ' . $limit
    );
}

/**
 * Credit wallet with full audit trail (internal).
 *
 * @return array{transaction_id: int, balance_after: float}
 */
function recordWalletTransaction(
    int $userId,
    string $type,
    float $amount,
    string $reason,
    ?int $referenceId,
    string $description,
    ?string $rewardType = null,
    ?string $remarks = null
): array {
    ensureNxlWalletSchema();
    $wallet = db()->fetchOne('SELECT * FROM wallet WHERE user_id = ?', [$userId]);
    if (!$wallet) {
        db()->execute('INSERT INTO wallet (user_id, balance) VALUES (?, 0)', [$userId]);
        $wallet = db()->fetchOne('SELECT * FROM wallet WHERE user_id = ?', [$userId]);
    }

    $amount = round(max(0, $amount), 2);
    $rewardType = $rewardType ?? $reason;

    if ($type === 'credit') {
        $newBalance = (float) $wallet['balance'] + $amount;
        db()->execute(
            'UPDATE wallet SET balance = ?, total_earned = total_earned + ? WHERE user_id = ?',
            [$newBalance, $amount, $userId]
        );
    } else {
        $newBalance = max(0, (float) $wallet['balance'] - $amount);
        db()->execute(
            'UPDATE wallet SET balance = ?, total_spent = total_spent + ? WHERE user_id = ?',
            [$newBalance, $amount, $userId]
        );
    }

    $txId = db()->insert(
        'INSERT INTO wallet_transactions (user_id, type, amount, reason, reference_id, description, balance_after, reward_type, remarks)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [$userId, $type, $amount, $reason, $referenceId, $description, $newBalance, $rewardType, $remarks]
    );

    return ['transaction_id' => (int) $txId, 'balance_after' => $newBalance];
}

/**
 * Grant a standard NxL reward once per user/reason/reference.
 *
 * @return array{granted: bool, amount: float, message: string, popup: string, balance_after: float, transaction_id: int|null}
 */
function grantNxlReward(
    int $userId,
    string $reason,
    ?int $referenceId = null,
    ?string $remarks = null,
    bool $queuePopup = true,
    bool $notifyUser = true
): array {
    ensureNxlWalletSchema();
    $amounts = nxlRewardAmounts();
    if (!isset($amounts[$reason]) && $reason !== 'admin_credit') {
        return [
            'granted'         => false,
            'amount'          => 0.0,
            'message'         => 'Unknown reward type.',
            'popup'           => '',
            'balance_after'   => getWalletBalance($userId),
            'transaction_id'  => null,
        ];
    }

    $amount = $amounts[$reason] ?? 0.0;
    if ($amount <= 0) {
        return [
            'granted'         => false,
            'amount'          => 0.0,
            'message'         => 'Invalid reward amount.',
            'popup'           => '',
            'balance_after'   => getWalletBalance($userId),
            'transaction_id'  => null,
        ];
    }

    if (hasNxlRewardBeenGranted($userId, $reason, $referenceId)) {
        return [
            'granted'         => false,
            'amount'          => 0.0,
            'message'         => 'Reward already credited.',
            'popup'           => '',
            'balance_after'   => getWalletBalance($userId),
            'transaction_id'  => null,
        ];
    }

    $label = nxlRewardLabel($reason);
    $description = $remarks ?? "{$label} — {$amount} NxL";
    $tx = recordWalletTransaction($userId, 'credit', $amount, $reason, $referenceId, $description, $reason, $remarks);
    $popup = nxlRewardPopupMessage($reason, $amount);

    if ($notifyUser) {
        sendNotification(
            $userId,
            $reason === 'referral_bonus' ? 'referral' : 'wallet',
            'NxL Tokens Credited',
            $popup,
            $referenceId,
            $reason
        );
    }

    notifyAdminNxlCredit($userId, $amount, $reason, $remarks);

    if ($queuePopup) {
        queueNxlRewardPopup($popup);
    }

    return [
        'granted'        => true,
        'amount'         => $amount,
        'message'        => $popup,
        'popup'          => $popup,
        'balance_after'  => $tx['balance_after'],
        'transaction_id' => $tx['transaction_id'],
    ];
}

function processReferralRewardForReferredUser(int $referredUserId, bool $queuePopup = true): array
{
    $referral = db()->fetchOne(
        'SELECT * FROM referrals WHERE referred_id = ? AND status = ? LIMIT 1',
        [$referredUserId, 'pending']
    );
    if (!$referral) {
        return ['granted' => false, 'amount' => 0.0, 'message' => 'No pending referral.'];
    }

    $referrerId = (int) $referral['referrer_id'];
    $result = grantNxlReward($referrerId, 'referral_bonus', $referredUserId, 'Referral bonus — friend joined CYBEORCH', $queuePopup);
    if ($result['granted']) {
        db()->execute(
            'UPDATE referrals SET status = ?, rewarded_at = NOW(), bonus_amount = ? WHERE id = ?',
            ['rewarded', $result['amount'], (int) $referral['id']]
        );
    }

    return $result;
}

/** @return array{success: bool, message: string, granted: bool} */
function markWebinarRegistrationAttended(int $registrationId): array
{
    $reg = db()->fetchOne(
        'SELECT wr.*, w.title AS webinar_title FROM webinar_registrations wr
         JOIN webinars w ON w.id = wr.webinar_id WHERE wr.id = ?',
        [$registrationId]
    );
    if (!$reg) {
        return ['success' => false, 'message' => 'Registration not found.', 'granted' => false];
    }

    $userId = (int) $reg['user_id'];
    $webinarId = (int) $reg['webinar_id'];

    if (!(int) ($reg['attended'] ?? 0)) {
        db()->execute('UPDATE webinar_registrations SET attended = 1 WHERE id = ?', [$registrationId]);
    }

    $remarks = 'Webinar attendance: ' . (string) ($reg['webinar_title'] ?? 'Webinar');
    $reward = grantNxlReward($userId, 'webinar_reward', $webinarId, $remarks, true);

    if ((int) ($reg['nxl_credited'] ?? 0) === 0 && $reward['granted']) {
        try {
            db()->execute('UPDATE webinar_registrations SET nxl_credited = 1 WHERE id = ?', [$registrationId]);
        } catch (Throwable $e) {
            // column may be absent on older DBs
        }
    }

    return [
        'success' => true,
        'message' => $reward['granted']
            ? $reward['popup']
            : ((int) ($reg['attended'] ?? 0) ? 'Already marked attended.' : 'Marked attended.') . ' ' . ($reward['message'] ?? ''),
        'granted' => $reward['granted'],
    ];
}

function getWalletSummaryForUser(int $userId): array
{
    ensureNxlWalletSchema();
    $wallet = db()->fetchOne('SELECT * FROM wallet WHERE user_id = ?', [$userId]);
    $referralsMade = (int) (db()->fetchOne(
        'SELECT COUNT(*) AS c FROM referrals WHERE referrer_id = ? AND status = ?',
        [$userId, 'rewarded']
    )['c'] ?? 0);
    $referralEarnings = (float) (db()->fetchOne(
        "SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions WHERE user_id = ? AND reason = 'referral_bonus' AND type = 'credit'",
        [$userId]
    )['s'] ?? 0);

    return [
        'balance'           => $wallet ? (float) $wallet['balance'] : 0.0,
        'total_earned'      => $wallet ? (float) $wallet['total_earned'] : 0.0,
        'total_spent'       => $wallet ? (float) $wallet['total_spent'] : 0.0,
        'referrals_made'    => $referralsMade,
        'referral_earnings' => $referralEarnings,
    ];
}

function nxlInrValuePerToken(): float
{
    return defined('NXL_INR_VALUE') ? (float) NXL_INR_VALUE : 1.0;
}

function nxlInrValueLabel(): string
{
    $value = nxlInrValuePerToken();
    $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

    return '₹' . number_format($value, $decimals);
}

function nxlMaxWalletBalanceSpendPercent(): int
{
    if (defined('NXL_MAX_WALLET_BALANCE_PERCENT')) {
        return max(1, min(100, (int) NXL_MAX_WALLET_BALANCE_PERCENT));
    }
    if (defined('NXL_MAX_CHECKOUT_PERCENT')) {
        return max(1, min(100, (int) NXL_MAX_CHECKOUT_PERCENT));
    }

    return 50;
}

function nxlWalletSpendLimitMessage(): string
{
    $pct = nxlMaxWalletBalanceSpendPercent();

    return 'You can use up to ' . $pct . '% of your available NXL Wallet balance.';
}

/**
 * Maximum NXL spendable from wallet balance in one payment (50% of balance by default).
 */
function nxlMaxTokensFromWalletBalance(float $availableBalance, bool $wholeTokensOnly = true): float
{
    $availableBalance = round(max(0, $availableBalance), 2);
    $cap = $availableBalance * (nxlMaxWalletBalanceSpendPercent() / 100);

    return $wholeTokensOnly ? (float) floor($cap) : round($cap, 2);
}

function nxlMaxUsableTokens(float $feeInr, float $availableBalance): float
{
    $feeInr = round(max(0, $feeInr), 2);
    $maxFromBalance = nxlMaxTokensFromWalletBalance($availableBalance, true);

    return round(min($maxFromBalance, inrDiscountToNxlTokens($feeInr), $availableBalance), 2);
}

function nxlTokensToInrDiscount(float $tokens): float
{
    return round(max(0, $tokens) * nxlInrValuePerToken(), 2);
}

function inrDiscountToNxlTokens(float $inr): float
{
    $rate = nxlInrValuePerToken();
    if ($rate <= 0) {
        return 0.0;
    }

    return round(max(0, $inr) / $rate, 2);
}

/**
 * @return array{
 *   valid: bool,
 *   error: string,
 *   tokens_to_use: float,
 *   inr_discount: float,
 *   remaining_payable: float,
 *   remaining_balance: float,
 *   max_tokens_allowed: float
 * }
 */
function calculateNxlRedemption(float $feeInr, float $availableBalance, float $requestedTokens): array
{
    $feeInr = round(max(0, $feeInr), 2);
    $availableBalance = round(max(0, $availableBalance), 2);
    $requestedTokens = round(max(0, $requestedTokens), 2);

    $maxFromBalanceCap = nxlMaxTokensFromWalletBalance($availableBalance, false);
    $maxTokensAllowed = nxlMaxUsableTokens($feeInr, $availableBalance);
    $limitMessage = nxlWalletSpendLimitMessage();

    if ($feeInr <= 0 || $availableBalance <= 0) {
        return [
            'valid'               => true,
            'error'               => '',
            'limit_message'       => $limitMessage,
            'tokens_to_use'       => 0.0,
            'inr_discount'        => 0.0,
            'remaining_payable'   => $feeInr,
            'remaining_balance'   => $availableBalance,
            'max_tokens_allowed'  => 0.0,
            'max_from_balance_cap'=> 0.0,
        ];
    }

    if ($requestedTokens > $availableBalance + 0.001) {
        return [
            'valid'               => false,
            'error'               => 'You cannot use more NxL tokens than your available balance.',
            'limit_message'       => $limitMessage,
            'tokens_to_use'       => 0.0,
            'inr_discount'        => 0.0,
            'remaining_payable'   => $feeInr,
            'remaining_balance'   => $availableBalance,
            'max_tokens_allowed'  => $maxTokensAllowed,
            'max_from_balance_cap'=> $maxFromBalanceCap,
        ];
    }

    if ($requestedTokens > $maxFromBalanceCap + 0.001) {
        return [
            'valid'               => false,
            'error'               => $limitMessage,
            'limit_message'       => $limitMessage,
            'tokens_to_use'       => 0.0,
            'inr_discount'        => 0.0,
            'remaining_payable'   => $feeInr,
            'remaining_balance'   => $availableBalance,
            'max_tokens_allowed'  => $maxTokensAllowed,
            'max_from_balance_cap'=> $maxFromBalanceCap,
        ];
    }

    $tokensToUse = min($requestedTokens, $maxTokensAllowed);
    $inrDiscount = min(nxlTokensToInrDiscount($tokensToUse), $feeInr);
    $tokensToUse = min($tokensToUse, inrDiscountToNxlTokens($inrDiscount));

    if ($requestedTokens > $tokensToUse + 0.001) {
        return [
            'valid'               => false,
            'error'               => $limitMessage,
            'limit_message'       => $limitMessage,
            'tokens_to_use'       => $tokensToUse,
            'inr_discount'        => $inrDiscount,
            'remaining_payable'   => round(max(0, $feeInr - $inrDiscount), 2),
            'remaining_balance'   => round(max(0, $availableBalance - $tokensToUse), 2),
            'max_tokens_allowed'  => $maxTokensAllowed,
            'max_from_balance_cap'=> $maxFromBalanceCap,
        ];
    }

    // 1 NXL = ₹1 — discount must equal tokens used
    if ($tokensToUse > 0 && abs($inrDiscount - $tokensToUse) > 0.001) {
        $inrDiscount = $tokensToUse;
    }

    return [
        'valid'               => true,
        'error'               => '',
        'limit_message'       => $limitMessage,
        'tokens_to_use'       => $tokensToUse,
        'inr_discount'        => $inrDiscount,
        'remaining_payable'   => round(max(0, $feeInr - $inrDiscount), 2),
        'remaining_balance'   => round(max(0, $availableBalance - $tokensToUse), 2),
        'max_tokens_allowed'  => $maxTokensAllowed,
        'max_from_balance_cap'=> $maxFromBalanceCap,
    ];
}

/**
 * @return array{success: bool, message: string, balance_after: float}
 */
function debitNxlForPayment(
    int $userId,
    float $tokens,
    string $programType,
    int $referenceId,
    string $programTitle
): array {
    $tokens = round(max(0, $tokens), 2);
    if ($tokens <= 0) {
        return ['success' => true, 'message' => '', 'balance_after' => getWalletBalance($userId)];
    }

    $balance = getWalletBalance($userId);
    if ($tokens > $balance + 0.001) {
        return [
            'success'       => false,
            'message'       => 'Insufficient NxL wallet balance.',
            'balance_after' => $balance,
        ];
    }

    $description = 'Payment for ' . ucfirst($programType) . ': ' . $programTitle;
    $tx = recordWalletTransaction($userId, 'debit', $tokens, 'redemption', $referenceId, $description, 'redemption', $description);
    sendNotification(
        $userId,
        'wallet',
        'NxL Tokens Used',
        (int) round($tokens) . ' NxL tokens applied toward ' . $programTitle . '.',
        $referenceId,
        $programType
    );

    return [
        'success'       => true,
        'message'       => 'Wallet debited successfully.',
        'balance_after' => $tx['balance_after'],
    ];
}

/**
 * Complete a program purchase paid fully via NxL wallet on secure payment.
 *
 * @return array{success: bool, message: string}
 */
function completeSecurePaymentWithNxlWallet(
    int $userId,
    string $programType,
    int $programId,
    float $feeInr,
    float $requestedTokens,
    string $programTitle,
    array $userRow
): array {
    if (!in_array($programType, ['webinar', 'bootcamp'], true) || $programId < 1) {
        return ['success' => false, 'message' => 'Select a scheduled program with a valid ID to pay via NxL wallet.'];
    }

    $balance = getWalletBalance($userId);
    $redemption = calculateNxlRedemption($feeInr, $balance, $requestedTokens);
    if (!$redemption['valid']) {
        return ['success' => false, 'message' => $redemption['error']];
    }
    if ($redemption['tokens_to_use'] <= 0) {
        return ['success' => false, 'message' => 'Enter how many NxL tokens you want to use.'];
    }
    if ($redemption['remaining_payable'] > 0.01) {
        return [
            'success' => false,
            'message' => 'NxL tokens do not cover the full amount (₹' . number_format($redemption['remaining_payable'], 2) . ' remaining). Reduce tokens or use Razorpay checkout for partial wallet payment.',
        ];
    }

    $debit = debitNxlForPayment($userId, $redemption['tokens_to_use'], $programType, $programId, $programTitle);
    if (!$debit['success']) {
        return ['success' => false, 'message' => $debit['message']];
    }

    require_once __DIR__ . '/form-submissions.php';
    $regNo = generateRegNo($programType === 'webinar' ? 'CYB-W' : 'CYB-B');

    if ($programType === 'webinar') {
        $existing = db()->fetchOne(
            'SELECT id FROM webinar_registrations WHERE user_id = ? AND webinar_id = ?',
            [$userId, $programId]
        );
        if ($existing) {
            return ['success' => true, 'message' => 'You are already registered for this webinar.'];
        }
        $regId = db()->insert(
            "INSERT INTO webinar_registrations (user_id, webinar_id, registration_no, payment_status) VALUES (?,?,?,'paid')",
            [$userId, $programId, $regNo]
        );
        recordWebinarRegistrationForm($regId, [
            'full_name'       => $userRow['full_name'] ?? '',
            'email'           => $userRow['email'] ?? '',
            'phone'           => $userRow['phone'] ?? '',
            'title'           => $programTitle,
            'registration_no' => $regNo,
            'payment_status'  => 'paid',
            'webinar_id'      => $programId,
            'payment_method'  => 'nxl_wallet',
            'nxl_tokens_used' => (int) round($redemption['tokens_to_use']),
        ]);
    } else {
        $existing = db()->fetchOne(
            'SELECT id FROM bootcamp_enrollments WHERE user_id = ? AND bootcamp_id = ?',
            [$userId, $programId]
        );
        if ($existing) {
            return ['success' => true, 'message' => 'You are already enrolled in this bootcamp.'];
        }
        $enrollId = db()->insert(
            "INSERT INTO bootcamp_enrollments (user_id, bootcamp_id, enrollment_no, payment_status) VALUES (?,?,?,'paid')",
            [$userId, $programId, $regNo]
        );
        db()->execute('UPDATE bootcamps SET enrolled_seats = enrolled_seats + 1 WHERE id = ?', [$programId]);
        grantNxlReward($userId, 'bootcamp_reward', $programId, 'Bootcamp enrollment NxL reward!', false);
        recordBootcampRegistrationForm($enrollId, [
            'full_name'       => $userRow['full_name'] ?? '',
            'email'           => $userRow['email'] ?? '',
            'phone'           => $userRow['phone'] ?? '',
            'title'           => $programTitle,
            'enrollment_no'   => $regNo,
            'payment_status'  => 'paid',
            'bootcamp_id'     => $programId,
            'payment_method'  => 'nxl_wallet',
            'nxl_tokens_used' => (int) round($redemption['tokens_to_use']),
        ]);
    }

    sendNotification($userId, 'payment', 'Payment Successful ✓', 'Paid ' . (int) round($redemption['tokens_to_use']) . ' NxL tokens for: ' . $programTitle);

    return ['success' => true, 'message' => 'Payment completed with NxL wallet.'];
}

function renderNxlRewardPopups(): void
{
    $popups = consumeNxlRewardPopups();
    if ($popups === []) {
        return;
    }
    foreach ($popups as $msg) {
        echo '<div class="nxl-reward-popup" role="alert" data-nxl-popup>'
            . '<button type="button" class="nxl-reward-popup-close" aria-label="Close">&times;</button>'
            . '<p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p></div>';
    }
    static $scriptDone = false;
    if ($scriptDone) {
        return;
    }
    $scriptDone = true;
    echo '<style>'
        . '.nxl-reward-popup{position:fixed;top:1.25rem;right:1.25rem;z-index:10050;max-width:min(420px,calc(100vw - 2rem));'
        . 'background:linear-gradient(135deg,rgba(0,255,136,0.15),rgba(0,212,255,0.12));border:1px solid rgba(0,255,136,0.45);'
        . 'border-radius:12px;padding:1rem 2.5rem 1rem 1rem;box-shadow:0 16px 48px rgba(0,0,0,0.45);animation:nxlPopIn .35s ease;margin-bottom:.5rem}'
        . '.nxl-reward-popup p{margin:0;font-size:.9rem;line-height:1.5;color:#e0e8f0}'
        . '.nxl-reward-popup-close{position:absolute;top:.35rem;right:.5rem;background:none;border:none;color:#7a8fa6;font-size:1.35rem;cursor:pointer;line-height:1}'
        . '@keyframes nxlPopIn{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}'
        . '</style><script>'
        . 'document.querySelectorAll("[data-nxl-popup] .nxl-reward-popup-close").forEach(function(btn){'
        . 'btn.addEventListener("click",function(){btn.closest("[data-nxl-popup]")?.remove();});});'
        . 'setTimeout(function(){document.querySelectorAll("[data-nxl-popup]").forEach(function(el,i){'
        . 'setTimeout(function(){el.remove();},6000+i*400);});},8000);'
        . '</script>';
}
