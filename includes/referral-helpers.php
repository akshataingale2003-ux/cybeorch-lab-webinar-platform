<?php
declare(strict_types=1);

/**
 * Referral link helpers and secure referral code validation.
 */
if (!function_exists('referralShareUrl')) {
    /** Full shareable referral signup URL (login page opens sign-up with ref code). */
    function referralShareUrl(string $referralCode): string
    {
        $code = normalizeReferralCodeInput($referralCode);
        $path = $code !== ''
            ? 'login.php?signup=1&ref=' . rawurlencode($code)
            : 'login.php?signup=1';

        if (!function_exists('absoluteUrl')) {
            require_once __DIR__ . '/helpers.php';
        }

        return absoluteUrl($path);
    }
}

/** Normalize user-entered referral code (A–Z, 0–9 only). */
function normalizeReferralCodeInput(string $code): string
{
    $code = strtoupper(preg_replace('/\s+/', '', trim($code)) ?? '');

    return substr(preg_replace('/[^A-Z0-9]/', '', $code) ?? '', 0, 20);
}

/**
 * Validate a referral code for a new signup.
 *
 * @return array{ok: bool, referrer_id: int|null, referral_code: string|null, message: string}
 */
function resolveReferrerFromCode(string $rawCode, string $newUserEmail): array
{
    $code = normalizeReferralCodeInput($rawCode);
    if ($code === '') {
        return ['ok' => true, 'referrer_id' => null, 'referral_code' => null, 'message' => ''];
    }

    if (strlen($code) < 4) {
        return ['ok' => false, 'referrer_id' => null, 'referral_code' => null, 'message' => 'Referral code must be at least 4 characters.'];
    }

    require_once __DIR__ . '/db.php';

    $newUserEmail = strtolower(trim($newUserEmail));
    $referrer = db()->fetchOne(
        'SELECT id, email, referral_code, is_blocked FROM users WHERE UPPER(referral_code) = ? LIMIT 1',
        [$code]
    );

    if (!$referrer) {
        return ['ok' => false, 'referrer_id' => null, 'referral_code' => null, 'message' => 'Invalid referral code.'];
    }

    if (!empty($referrer['is_blocked'])) {
        return ['ok' => false, 'referrer_id' => null, 'referral_code' => null, 'message' => 'Invalid referral code.'];
    }

    if ($newUserEmail !== '' && strtolower((string) $referrer['email']) === $newUserEmail) {
        return ['ok' => false, 'referrer_id' => null, 'referral_code' => null, 'message' => 'You cannot use your own referral code.'];
    }

    return [
        'ok'            => true,
        'referrer_id'   => (int) $referrer['id'],
        'referral_code' => (string) $referrer['referral_code'],
        'message'       => '',
    ];
}
