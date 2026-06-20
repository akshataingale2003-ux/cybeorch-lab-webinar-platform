<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** @return array<string, string> */
function closingSoonSettingsDefaults(): array
{
    return [
        'closing_soon_enabled'        => '0',
        'closing_soon_title'          => 'Registration Closing Soon',
        'closing_soon_message'        => 'Limited seats remaining. Complete your registration now to secure your spot in upcoming programs.',
        'closing_soon_deadline'       => '',
        'closing_soon_button_text'    => 'Register Now',
        'closing_soon_button_url'     => 'webinars.php',
    ];
}

function ensureClosingSoonSettingsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        db()->execute('CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) {
        // DB offline — settings unavailable until connection is restored.
    }
}

/** @return array<string, string> */
function getClosingSoonSettings(): array
{
    ensureClosingSoonSettingsSchema();
    $defaults = closingSoonSettingsDefaults();
    $keys = array_keys($defaults);

    try {
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = db()->fetchAll(
            "SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)",
            $keys
        );
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($key !== '' && array_key_exists($key, $defaults)) {
                $defaults[$key] = (string) ($row['setting_value'] ?? '');
            }
        }
    } catch (Throwable $e) {
        // Use defaults when DB is unavailable.
    }

    return $defaults;
}

/** @param array<string, mixed> $input */
function saveClosingSoonSettings(array $input): array
{
    ensureClosingSoonSettingsSchema();

    $deadlineRaw = trim((string) ($input['closing_soon_deadline'] ?? ''));
    $deadline = '';
    if ($deadlineRaw !== '') {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $deadlineRaw)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $deadlineRaw);
        if (!$dt) {
            return ['success' => false, 'message' => 'Invalid registration deadline date/time.'];
        }
        $deadline = $dt->format('Y-m-d H:i:s');
    }

    $buttonUrl = trim((string) ($input['closing_soon_button_url'] ?? ''));
    if ($buttonUrl === '') {
        $buttonUrl = 'webinars.php';
    }
    if (preg_match('#^[a-z]+://#i', $buttonUrl)) {
        return ['success' => false, 'message' => 'Button link must be a site path (e.g. webinars.php), not an external URL.'];
    }
    $buttonUrl = ltrim($buttonUrl, '/');
    if (str_contains($buttonUrl, '..')) {
        return ['success' => false, 'message' => 'Invalid button link.'];
    }

    $title = trim((string) ($input['closing_soon_title'] ?? ''));
    $message = trim((string) ($input['closing_soon_message'] ?? ''));
    $buttonText = trim((string) ($input['closing_soon_button_text'] ?? ''));
    if ($title === '' || $message === '' || $buttonText === '') {
        return ['success' => false, 'message' => 'Title, message, and button text are required.'];
    }

    $values = [
        'closing_soon_enabled'     => !empty($input['closing_soon_enabled']) ? '1' : '0',
        'closing_soon_title'       => $title,
        'closing_soon_message'     => $message,
        'closing_soon_deadline'    => $deadline,
        'closing_soon_button_text' => $buttonText,
        'closing_soon_button_url'  => $buttonUrl,
    ];

    try {
        foreach ($values as $key => $value) {
            db()->execute(
                'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
                [$key, $value]
            );
        }
    } catch (Throwable $e) {
        return ['success' => false, 'message' => 'Could not save settings. Check database connection.'];
    }

    return ['success' => true, 'message' => 'Registration Closing Soon popup settings saved.'];
}

function closingSoonSettingsEnabled(): bool
{
    $settings = getClosingSoonSettings();

    return ($settings['closing_soon_enabled'] ?? '0') === '1';
}

function closingSoonDeadlinePassed(): bool
{
    $settings = getClosingSoonSettings();
    $raw = trim((string) ($settings['closing_soon_deadline'] ?? ''));
    if ($raw === '' || $raw === '0000-00-00 00:00:00') {
        return false;
    }

    try {
        return new DateTimeImmutable('now') >= new DateTimeImmutable($raw);
    } catch (Throwable $e) {
        return false;
    }
}

/** Mark popup as pending for the current session (call after successful registration). */
function flagClosingSoonPopupAfterRegistration(): void
{
    startSession();
    $_SESSION['cybeorch_wcs_show_after_register'] = true;
}

function userShouldSeeClosingSoonPopup(): bool
{
    return isLoggedIn()
        && closingSoonSettingsEnabled()
        && !closingSoonDeadlinePassed();
}
