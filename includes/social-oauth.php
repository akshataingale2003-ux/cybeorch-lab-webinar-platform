<?php
declare(strict_types=1);

require_once __DIR__ . '/register-email-auth.php';

function oauthGoogleConfigured(): bool
{
    return defined('GOOGLE_OAUTH_CLIENT_ID') && GOOGLE_OAUTH_CLIENT_ID !== ''
        && defined('GOOGLE_OAUTH_CLIENT_SECRET') && GOOGLE_OAUTH_CLIENT_SECRET !== '';
}

function oauthCallbackUrl(): string
{
    return rtrim(SITE_URL, '/') . '/oauth-callback.php';
}

function oauthHttpPost(string $url, array $fields, array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('OAuth request failed.');
    }
    $json = json_decode($body, true);
    if (!is_array($json) || $code >= 400) {
        $msg = is_array($json) ? ($json['error_description'] ?? $json['error'] ?? 'OAuth error') : 'OAuth error';
        throw new RuntimeException(is_string($msg) ? $msg : 'OAuth provider error.');
    }
    return $json;
}

function oauthHttpGet(string $url, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException('OAuth profile request failed.');
    }
    $json = json_decode($body, true);
    if (!is_array($json) || $code >= 400) {
        throw new RuntimeException('Could not load Google profile.');
    }
    return $json;
}

function oauthBeginGoogle(string $pendingEmail, bool $popup): void
{
    startSession();
    if (!oauthGoogleConfigured()) {
        oauthAbort('Google Sign-In is not configured. Add GOOGLE_OAUTH_* in includes/config.php.', $popup);
    }
    $pendingEmail = strtolower(trim($pendingEmail));
    if (!registerEmailIsAutoAuthorizable($pendingEmail)) {
        oauthAbort(registerEmailAuthHint($pendingEmail), $popup);
    }
    setRegisterPendingEmail($pendingEmail);

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_popup'] = $popup ? '1' : '0';

    $params = [
        'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
        'redirect_uri'  => oauthCallbackUrl(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
        'login_hint'    => $pendingEmail,
    ];
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

function oauthHandleGoogleCallback(): void
{
    startSession();
    $expectedState = $_SESSION['oauth_state'] ?? '';
    $popup = ($_SESSION['oauth_popup'] ?? '0') === '1';

    if (!empty($_GET['error'])) {
        oauthAbort('Google sign-in was cancelled.', $popup);
    }
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    if ($code === '' || $state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
        oauthAbort('Invalid OAuth session. Please try again.', $popup);
    }

    try {
        $profile = oauthFetchGoogleProfile($code);
    } catch (Throwable $e) {
        oauthAbort($e->getMessage(), $popup);
    }

    oauthClearSessionState();

    $email = strtolower(trim($profile['email']));
    $existing = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        oauthAbort('This email is already registered. Please sign in.', $popup);
    }

    try {
        $kind = registerEmailAuthKind($email);
        setRegisterEmailVerified('google', $profile, $kind);
    } catch (InvalidArgumentException $e) {
        oauthAbort($e->getMessage(), $popup);
    }

    if ($popup) {
        oauthRenderPopupSuccess($email, $profile['full_name'], $kind);
        exit;
    }
    header('Location: ' . url('signup.php?email_verified=1'));
    exit;
}

function oauthFetchGoogleProfile(string $code): array
{
    $token = oauthHttpPost('https://oauth2.googleapis.com/token', [
        'code'          => $code,
        'client_id'     => GOOGLE_OAUTH_CLIENT_ID,
        'client_secret' => GOOGLE_OAUTH_CLIENT_SECRET,
        'redirect_uri'  => oauthCallbackUrl(),
        'grant_type'    => 'authorization_code',
    ]);
    $access = $token['access_token'] ?? '';
    if ($access === '') {
        throw new RuntimeException('Google did not return an access token.');
    }
    $user = oauthHttpGet('https://openidconnect.googleapis.com/v1/userinfo', [
        'Authorization: Bearer ' . $access,
    ]);
    return [
        'provider_id' => (string) ($user['sub'] ?? ''),
        'email'       => strtolower(trim((string) ($user['email'] ?? ''))),
        'full_name'   => trim((string) ($user['name'] ?? 'Google User')),
    ];
}

function oauthRenderPopupSuccess(string $email, string $name, string $kind): void
{
    $payload = json_encode([
        'type'  => 'cybeorch-email-verified',
        'email' => $email,
        'name'  => $name,
        'kind'  => $kind,
    ], JSON_UNESCAPED_UNICODE);
    ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Authorized</title></head>
<body style="font-family:system-ui,sans-serif;background:#050b18;color:#00ff88;text-align:center;padding:2rem;">
<p>Email authorized. Closing…</p>
<script>
(function () {
  var p = <?= $payload ?>;
  try { if (window.opener && !window.opener.closed) window.opener.postMessage(p, window.location.origin); } catch (e) {}
  setTimeout(function () { window.close(); }, 350);
})();
</script></body></html>
    <?php
}

function oauthClearSessionState(): void
{
    unset($_SESSION['oauth_state'], $_SESSION['oauth_popup']);
}

function oauthAbort(string $message, bool $popup): void
{
    oauthClearSessionState();
    clearRegisterPendingEmail();
    if ($popup) {
        $payload = json_encode(['type' => 'cybeorch-email-error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        ?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Error</title></head>
<body style="font-family:sans-serif;background:#050b18;color:#ff8888;padding:2rem;text-align:center;">
<p><?= htmlspecialchars($message) ?></p>
<script>
try { if (window.opener) window.opener.postMessage(<?= $payload ?>, window.location.origin); } catch(e) {}
setTimeout(function(){ window.close(); }, 2200);
</script></body></html>
        <?php
        exit;
    }
    $_SESSION['oauth_error'] = $message;
    header('Location: ' . url('signup.php'));
    exit;
}
