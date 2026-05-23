<?php
declare(strict_types=1);

const REGISTER_EMAIL_VERIFIED_KEY = 'register_email_verified';
const REGISTER_PENDING_EMAIL_KEY = 'register_pending_email';

/** @return list<string> Lowercase hostnames (no @) */
function registerAllowedCompanyDomains(): array
{
    $domains = ['cybeorch.com'];
    if (defined('REGISTER_COMPANY_EMAIL_DOMAINS') && REGISTER_COMPANY_EMAIL_DOMAINS !== '') {
        foreach (explode(',', REGISTER_COMPANY_EMAIL_DOMAINS) as $d) {
            $d = strtolower(trim($d));
            if ($d !== '') {
                $domains[] = ltrim($d, '@');
            }
        }
    }
    $siteDomain = strtolower((string) preg_replace('/^.*@/', '', SITE_EMAIL));
    if ($siteDomain !== '') {
        $domains[] = $siteDomain;
    }
    return array_values(array_unique($domains));
}

function registerEmailDomain(string $email): string
{
    $email = strtolower(trim($email));
    $at = strrpos($email, '@');
    return $at !== false ? substr($email, $at + 1) : '';
}

/** gmail | company | other */
function registerEmailAuthKind(string $email): string
{
    if (!isValidEmail($email)) {
        return 'other';
    }
    $domain = registerEmailDomain($email);
    if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
        return 'gmail';
    }
    if (in_array($domain, registerAllowedCompanyDomains(), true)) {
        return 'company';
    }
    return 'other';
}

function registerEmailIsAutoAuthorizable(string $email): bool
{
    return in_array(registerEmailAuthKind($email), ['gmail', 'company'], true);
}

function registerEmailAuthHint(string $email): string
{
    $kind = registerEmailAuthKind($email);
    if ($kind === 'gmail') {
        return 'Gmail detected — verify with Google to authorize this address.';
    }
    if ($kind === 'company') {
        return 'Company email detected — verify with Google (Workspace) to authorize.';
    }
    $domains = implode(', @', registerAllowedCompanyDomains());
    return 'Use a Gmail address or an approved company email (@' . $domains . ').';
}

/** @return array{email: string, kind: string, provider: string, full_name: string, token: string, verified_at: int}|null */
function getRegisterEmailVerified(): ?array
{
    startSession();
    $data = $_SESSION[REGISTER_EMAIL_VERIFIED_KEY] ?? null;
    if (!is_array($data) || empty($data['email']) || empty($data['token'])) {
        return null;
    }
    if ((time() - (int) ($data['verified_at'] ?? 0)) > 3600) {
        unset($_SESSION[REGISTER_EMAIL_VERIFIED_KEY]);
        return null;
    }
    return $data;
}

function isRegisterEmailVerified(): bool
{
    return getRegisterEmailVerified() !== null;
}

function registerEmailMatches(string $email): bool
{
    $v = getRegisterEmailVerified();
    if (!$v) {
        return false;
    }
    return strtolower(trim($email)) === strtolower(trim($v['email']));
}

function setRegisterPendingEmail(string $email): void
{
    startSession();
    $email = strtolower(trim($email));
    if (!registerEmailIsAutoAuthorizable($email)) {
        throw new InvalidArgumentException(registerEmailAuthHint($email));
    }
    $_SESSION[REGISTER_PENDING_EMAIL_KEY] = $email;
}

function getRegisterPendingEmail(): ?string
{
    startSession();
    $e = $_SESSION[REGISTER_PENDING_EMAIL_KEY] ?? null;
    return is_string($e) && $e !== '' ? $e : null;
}

function clearRegisterPendingEmail(): void
{
    startSession();
    unset($_SESSION[REGISTER_PENDING_EMAIL_KEY]);
}

/** @param array{email: string, full_name?: string, provider_id?: string} $profile */
function setRegisterEmailVerified(string $provider, array $profile, string $kind): void
{
    startSession();
    $email = strtolower(trim($profile['email'] ?? ''));
    if ($email === '' || !isValidEmail($email)) {
        throw new InvalidArgumentException('Invalid verified email.');
    }
    if (!registerEmailIsAutoAuthorizable($email)) {
        throw new InvalidArgumentException(registerEmailAuthHint($email));
    }
    $pending = getRegisterPendingEmail();
    if ($pending !== null && $pending !== $email) {
        throw new InvalidArgumentException('Google account email must match the address you entered.');
    }
    $_SESSION[REGISTER_EMAIL_VERIFIED_KEY] = [
        'email'       => $email,
        'kind'        => $kind,
        'provider'    => $provider,
        'provider_id' => (string) ($profile['provider_id'] ?? ''),
        'full_name'   => trim((string) ($profile['full_name'] ?? '')),
        'verified_at' => time(),
        'token'       => bin2hex(random_bytes(16)),
    ];
    clearRegisterPendingEmail();
}

function clearRegisterEmailVerified(): void
{
    startSession();
    unset($_SESSION[REGISTER_EMAIL_VERIFIED_KEY]);
    clearRegisterPendingEmail();
}

function requireRegisterEmailVerifiedOnPost(string $postedEmail): ?string
{
    $postedEmail = strtolower(trim($postedEmail));
    if (!registerEmailIsAutoAuthorizable($postedEmail)) {
        return registerEmailAuthHint($postedEmail);
    }
    $v = getRegisterEmailVerified();
    if (!$v) {
        return 'Authorize your email with Google (Gmail or company address) before registering.';
    }
    if (!hash_equals($v['token'], $_POST['email_verify_token'] ?? '')) {
        return 'Email authorization expired. Please verify again with Google.';
    }
    if (!registerEmailMatches($postedEmail)) {
        return 'Submitted email must match your Google-authorized address.';
    }
    return null;
}
