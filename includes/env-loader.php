<?php
declare(strict_types=1);

/**
 * Load project .env (same keys as Node: EMAIL_USER, EMAIL_PASS).
 */
function cybeorchEnvPath(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
}

function cybeorchLoadDotEnv(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $path = $path ?? cybeorchEnvPath();
    if (!is_readable($path)) {
        return;
    }
    $loaded = true;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}

function cybeorchEnv(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === null) {
        return $default;
    }
    return trim((string) $v);
}

/** Stackmail outgoing SMTP (hosting panel: smtp.stackmail.com, port 465 SSL). */
function cybeorchSmtpPreset(): array
{
    return [
        'host'      => 'smtp.stackmail.com',
        'port'      => 465,
        'secure'    => 'ssl',
        'user'      => 'info@xyz.com',
        'from_name' => 'CYBEORCH LAB',
    ];
}

/** Infer SMTP host/port/secure from the mailbox when not set explicitly. */
function cybeorchSmtpSettingsForEmail(string $email, ?string $host = null, ?int $port = null, ?string $secure = null): array
{
    $preset = cybeorchSmtpPreset();
    $email  = strtolower(trim($email));

    if ($host === null || $host === '') {
        if (
            str_ends_with($email, '@gmail.com')
            || str_ends_with($email, '@googlemail.com')
        ) {
            $host = 'smtp.gmail.com';
        } else {
            $host = $preset['host'];
        }
    }
    if ($port === null || $port <= 0) {
        $hostLower = strtolower($host);
        $usePreset = $host === $preset['host']
            || str_contains($hostLower, 'stackmail')
            || str_contains($hostLower, 'cybeorch');
        $port = $usePreset ? (int) $preset['port'] : 587;
    }
    if ($secure === null || $secure === '') {
        $secure = ($port === 465) ? 'ssl' : 'tls';
    }

    return ['host' => $host, 'port' => $port, 'secure' => strtolower($secure)];
}

/** Map EMAIL_USER / EMAIL_PASS from .env into SMTP_* constants. */
function cybeorchApplySmtpFromEnv(): void
{
    $user = cybeorchEnv('EMAIL_USER');
    $pass = preg_replace('/\s+/', '', cybeorchEnv('EMAIL_PASS')) ?? '';

    if ($user !== '' && !defined('SMTP_USER')) {
        define('SMTP_USER', strtolower($user));
    }
    if ($pass !== '' && !defined('SMTP_PASS')) {
        define('SMTP_PASS', $pass);
    }
    if ($user !== '' && !defined('SMTP_FROM_EMAIL')) {
        define('SMTP_FROM_EMAIL', strtolower($user));
    }
    if (!defined('SMTP_HOST') && ($h = cybeorchEnv('SMTP_HOST')) !== '') {
        define('SMTP_HOST', $h);
    }
    if (!defined('SMTP_PORT') && ($p = cybeorchEnv('SMTP_PORT')) !== '') {
        define('SMTP_PORT', (int) $p);
    }
    if (!defined('SMTP_SECURE') && ($s = cybeorchEnv('SMTP_SECURE')) !== '') {
        define('SMTP_SECURE', $s);
    }
    if (!defined('SMTP_FROM_NAME') && ($n = cybeorchEnv('SMTP_FROM_NAME')) !== '') {
        define('SMTP_FROM_NAME', $n);
    }
}

/** @return array{ok: bool, message: string} */
function cybeorchWriteDotEnv(
    string $email,
    string $appPassword,
    string $fromName = 'CYBEORCH LAB',
    ?string $smtpHost = null,
    ?int $smtpPort = null,
    ?string $smtpSecure = null
): array {
    $email = strtolower(trim($email));
    $pass  = trim($appPassword);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid EMAIL_USER.'];
    }

    $settings  = cybeorchSmtpSettingsForEmail($email, $smtpHost, $smtpPort, $smtpSecure);
    $smtpHost  = $settings['host'];
    $smtpPort  = $settings['port'];
    $smtpSecure = $settings['secure'];
    $gmailSmtp = str_contains(strtolower($smtpHost), 'gmail');
    if ($gmailSmtp) {
        $pass = preg_replace('/\s+/', '', $pass) ?? '';
        if (!preg_match('/^[a-zA-Z]{16}$/', $pass)) {
            return ['ok' => false, 'message' => 'Gmail SMTP requires a 16-letter App Password (no spaces).'];
        }
    } elseif (strlen($pass) < 8) {
        return ['ok' => false, 'message' => 'EMAIL_PASS is too short for custom SMTP.'];
    }

    $fromName = $fromName !== '' ? $fromName : 'CYBEORCH LAB';
    $content  = "# CYBEORCH SMTP — do not commit to git\n"
        . "EMAIL_USER={$email}\n"
        . "EMAIL_PASS={$pass}\n"
        . "SMTP_HOST={$smtpHost}\n"
        . "SMTP_PORT={$smtpPort}\n"
        . "SMTP_SECURE={$smtpSecure}\n"
        . "SMTP_FROM_NAME={$fromName}\n";

    $path = cybeorchEnvPath();
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Could not write .env file.'];
    }

    cybeorchLoadDotEnv($path);
    cybeorchApplySmtpFromEnv();

    return ['ok' => true, 'message' => '.env saved.'];
}

/** Write .env from SMTP_* constants (e.g. config.local.php). */
function cybeorchWriteDotEnvFromConstants(): array
{
    if (!defined('SMTP_USER') || !defined('SMTP_PASS')) {
        return ['ok' => false, 'message' => 'SMTP constants are not defined (copy config.local.php.example).'];
    }
    $pass = trim((string) SMTP_PASS);
    $user = trim((string) SMTP_USER);
    $placeholders = ['your_smtp_password', 'your_app_password', 'your_email@gmail.com', ''];
    if ($user === '' || $pass === '' || in_array($pass, $placeholders, true)) {
        return ['ok' => false, 'message' => 'Set SMTP_PASS in includes/config.local.php first.'];
    }

    return cybeorchWriteDotEnv(
        SMTP_USER,
        SMTP_PASS,
        defined('SMTP_FROM_NAME') ? (string) SMTP_FROM_NAME : 'CYBEORCH LAB',
        defined('SMTP_HOST') ? (string) SMTP_HOST : null,
        defined('SMTP_PORT') ? (int) SMTP_PORT : null,
        defined('SMTP_SECURE') ? (string) SMTP_SECURE : null
    );
}
