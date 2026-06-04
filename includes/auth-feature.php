<?php
declare(strict_types=1);

/**
 * Public-site authentication toggle.
 *
 * Set PUBLIC_AUTH_ENABLED to true in config.php to restore:
 * - Login / register / OTP flows
 * - Homepage registration popup
 * - Protected routes (dashboard, checkout, etc.)
 * - Navbar Sign In and account menu
 */
function isPublicAuthEnabled(): bool
{
    return defined('PUBLIC_AUTH_ENABLED') && PUBLIC_AUTH_ENABLED;
}

/** Guest-facing account CTA when auth is off (enquiry instead of register). */
function publicGuestAccountUrl(): string
{
    return isPublicAuthEnabled() ? url('signup.php') : url('enquire-enroll.php');
}

/** Block auth-only endpoints when public auth is turned off. */
function rejectWhenPublicAuthDisabled(bool $json = false): void
{
    if (isPublicAuthEnabled()) {
        return;
    }
    if ($json) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account registration is temporarily unavailable.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . url('index.php'));
    exit;
}
