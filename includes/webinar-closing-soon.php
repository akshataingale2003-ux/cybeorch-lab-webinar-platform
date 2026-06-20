<?php
declare(strict_types=1);

require_once __DIR__ . '/public-catalog.php';
require_once __DIR__ . '/webinar-register-helpers.php';
require_once __DIR__ . '/webinar-registration-service.php';

/** Auto-show popup when registration closes within this many hours (if admin flag is off). */
if (!defined('WEBINAR_CLOSING_SOON_THRESHOLD_HOURS')) {
    define('WEBINAR_CLOSING_SOON_THRESHOLD_HOURS', 72);
}

function ensureWebinarClosingSoonSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (!function_exists('adminTableHasColumn')) {
        require_once __DIR__ . '/admin-actions.php';
    }

    try {
        if (!adminTableHasColumn('webinars', 'registration_closes_at')) {
            db()->execute(
                'ALTER TABLE webinars ADD COLUMN registration_closes_at DATETIME NULL DEFAULT NULL AFTER scheduled_at'
            );
        }
        if (!adminTableHasColumn('webinars', 'closing_soon_enabled')) {
            db()->execute(
                'ALTER TABLE webinars ADD COLUMN closing_soon_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER registration_closes_at'
            );
        }
    } catch (Throwable $e) {
        // DB offline — popup skipped until schema is available.
    }
}

/** @param array<string, mixed> $webinar */
function webinarRegistrationClosingAt(array $webinar): ?DateTimeImmutable
{
    $raw = trim((string) ($webinar['registration_closes_at'] ?? ''));
    if ($raw !== '' && $raw !== '0000-00-00 00:00:00') {
        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable $e) {
            return null;
        }
    }

    $scheduled = trim((string) ($webinar['scheduled_at'] ?? ''));
    if ($scheduled === '' || $scheduled === '0000-00-00 00:00:00') {
        return null;
    }

    try {
        return new DateTimeImmutable($scheduled);
    } catch (Throwable $e) {
        return null;
    }
}

/** @param array<string, mixed> $webinar */
function webinarHasSeatsAvailable(array $webinar): bool
{
    $max = (int) ($webinar['max_seats'] ?? 0);
    if ($max < 1) {
        return true;
    }

    $registered = (int) ($webinar['registered_seats'] ?? 0);

    return $registered < $max;
}

function userIsRegisteredForWebinar(int $webinarId, ?int $userId = null): bool
{
    if ($webinarId < 1) {
        return false;
    }

    startSession();
    if ($userId === null) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    if ($userId < 1) {
        return false;
    }

    $row = dbTry(
        static fn () => fetchActiveWebinarRegistrationByUser($userId, $webinarId),
        null
    );

    return is_array($row);
}

/** @param array<string, mixed> $webinar */
function webinarIsClosingSoonEligible(array $webinar): bool
{
    ensureWebinarClosingSoonSchema();

    $status = (string) ($webinar['status'] ?? '');
    if (!in_array($status, ['upcoming', 'live'], true)) {
        return false;
    }

    if (!webinarHasSeatsAvailable($webinar)) {
        return false;
    }

    $webinarId = (int) ($webinar['id'] ?? 0);
    if ($webinarId > 0 && userIsRegisteredForWebinar($webinarId)) {
        return false;
    }

    $closesAt = webinarRegistrationClosingAt($webinar);
    if ($closesAt === null) {
        return false;
    }

    $now = new DateTimeImmutable('now');
    if ($now >= $closesAt) {
        return false;
    }

    if ((int) ($webinar['closing_soon_enabled'] ?? 0) === 1) {
        return true;
    }

    $hoursLeft = ($closesAt->getTimestamp() - $now->getTimestamp()) / 3600;

    return $hoursLeft > 0 && $hoursLeft <= (float) WEBINAR_CLOSING_SOON_THRESHOLD_HOURS;
}

/** @param array<string, mixed> $webinar */
function webinarClosingSoonMessage(array $webinar): string
{
    $title = trim(decodeStoredText((string) ($webinar['title'] ?? 'this webinar')));
    if ($title === '') {
        $title = 'this webinar';
    }

    return 'Limited seats remaining. Registration for ' . $title
        . ' will close soon. Register now to secure your spot.';
}

/** @param array<string, mixed> $webinar */
function webinarRegisterUrlForItem(array $webinar): string
{
    if (!empty($webinar['is_free'])) {
        $webinarId = (int) ($webinar['id'] ?? 0);
        if ($webinarId > 0) {
            return webinarRegistrationRegisterFreeUrl($webinarId);
        }

        return url('webinars.php');
    }

    if ((int) ($webinar['id'] ?? 0) > 0) {
        require_once __DIR__ . '/secure-payment-program.php';

        return securePaymentUrlForCatalogItem($webinar, 'webinar');
    }

    $slug = (string) ($webinar['slug'] ?? '');

    return url('secure-payment.php?type=webinar&program=' . rawurlencode($slug));
}

function webinarClosingSoonPageContextWebinar(): ?array
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== 'webinars.php') {
        return null;
    }

    $slug = trim((string) ($_GET['slug'] ?? ''));
    if ($slug === '') {
        return null;
    }

    $row = publicFetchWebinarBySlug($slug);

    return is_array($row) ? $row : null;
}

/**
 * Pick the webinar to promote in the closing-soon popup.
 *
 * @return array<string, mixed>|null
 */
function webinarClosingSoonPopupCandidate(): ?array
{
    ensureWebinarClosingSoonSchema();

    $context = webinarClosingSoonPageContextWebinar();
    if ($context !== null && webinarIsClosingSoonEligible($context)) {
        return $context;
    }

    $eligible = [];
    foreach (publicFetchWebinars(null, ['upcoming', 'live']) as $row) {
        if (webinarIsClosingSoonEligible($row)) {
            $eligible[] = $row;
        }
    }

    if ($eligible === []) {
        return null;
    }

    usort($eligible, static function (array $a, array $b): int {
        $aClose = webinarRegistrationClosingAt($a);
        $bClose = webinarRegistrationClosingAt($b);
        $aTs = $aClose ? $aClose->getTimestamp() : PHP_INT_MAX;
        $bTs = $bClose ? $bClose->getTimestamp() : PHP_INT_MAX;

        return $aTs <=> $bTs;
    });

    return $eligible[0];
}

/** Pages where the closing-soon welcome popup may appear (logged-in dashboard/home only). */
function closingSoonAllowedPages(): array
{
    return ['dashboard.php', 'index.php'];
}

/** Auth pages where the popup must never render. */
function closingSoonBlockedPages(): array
{
    return [
        'login.php',
        'signin.php',
        'signup.php',
        'register-website.php',
        'forgot-password.php',
        'reset-password.php',
        'oauth-callback.php',
        'oauth-start.php',
        'webinar-registration-confirm.php',
        'checkout.php',
        'secure-payment.php',
        'payment-success.php',
    ];
}

/** @return array<string, mixed>|null Client-safe popup payload from admin settings. */
function webinarClosingSoonPopupPayload(): ?array
{
    require_once __DIR__ . '/closing-soon-settings.php';

    if (!userShouldSeeClosingSoonPopup()) {
        return null;
    }

    $settings = getClosingSoonSettings();
    $deadlineRaw = trim((string) ($settings['closing_soon_deadline'] ?? ''));
    $closesAt = '';
    if ($deadlineRaw !== '' && $deadlineRaw !== '0000-00-00 00:00:00') {
        try {
            $closesAt = (new DateTimeImmutable($deadlineRaw))->format(DateTimeInterface::ATOM);
        } catch (Throwable $e) {
            $closesAt = '';
        }
    }

    $buttonPath = ltrim((string) ($settings['closing_soon_button_url'] ?? 'webinars.php'), '/');
    if ($buttonPath === '') {
        $buttonPath = 'webinars.php';
    }

    startSession();
    unset($_SESSION['cybeorch_wcs_show_after_register']);

    return [
        'popupId'       => 'site_closing_soon',
        'modalTitle'    => (string) ($settings['closing_soon_title'] ?? 'Registration Closing Soon'),
        'title'         => '',
        'message'       => (string) ($settings['closing_soon_message'] ?? ''),
        'registerUrl'   => url($buttonPath),
        'registerLabel' => (string) ($settings['closing_soon_button_text'] ?? 'Register Now'),
        'closesAt'      => $closesAt,
        'storageKey'    => 'cybeorch_wcs_dismiss_session',
    ];
}

function shouldRenderWebinarClosingSoonPopup(): bool
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (in_array($script, closingSoonBlockedPages(), true)) {
        return false;
    }

    if (!in_array($script, closingSoonAllowedPages(), true)) {
        return false;
    }

    startSession();

    return isLoggedIn();
}

function renderWebinarClosingSoonPopupAssets(): void
{
    static $markupDone = false;
    if ($markupDone || !shouldRenderWebinarClosingSoonPopup()) {
        return;
    }

    ensureWebinarClosingSoonSchema();

    $payload = webinarClosingSoonPopupPayload();
    if ($payload === null) {
        return;
    }

    $markupDone = true;
    $GLOBALS['cybeorch_wcs_payload'] = $payload;

    echo '<link rel="stylesheet" href="' . htmlspecialchars(urlVersioned('assets/css/webinar-closing-soon-popup.css')) . '">' . "\n";
    ?>
<div id="webinarClosingSoonModal" class="wcs-modal" role="dialog" aria-modal="true" aria-labelledby="wcsModalTitle" aria-hidden="true" hidden>
  <div class="wcs-modal__overlay" data-wcs-close tabindex="-1"></div>
  <div class="wcs-modal__panel">
    <button type="button" class="wcs-modal__close" data-wcs-close aria-label="Close">
      <i class="fas fa-times" aria-hidden="true"></i>
    </button>
    <div class="wcs-modal__brand">
      <span class="wcs-modal__brand-mark">CYBEORCH LABS</span>
      <span class="wcs-modal__brand-tag">Cybersecurity Training</span>
    </div>
    <div class="wcs-modal__icon" aria-hidden="true"><i class="fas fa-hourglass-half"></i></div>
    <h2 id="wcsModalTitle" class="wcs-modal__title" data-wcs-modal-title></h2>
    <p class="wcs-modal__webinar" data-wcs-webinar-title hidden></p>
    <p class="wcs-modal__message" data-wcs-message></p>
    <p class="wcs-modal__deadline" data-wcs-deadline hidden></p>
    <div class="wcs-modal__actions">
      <a href="#" class="wcs-modal__cta btn-primary-cyber" data-wcs-register>Register Now</a>
    </div>
  </div>
</div>
    <?php
}

/** Boot script — call near end of &lt;body&gt; after other scripts. */
function renderWebinarClosingSoonPopupBootScript(): void
{
    static $done = false;
    if ($done || empty($GLOBALS['cybeorch_wcs_payload']) || !shouldRenderWebinarClosingSoonPopup()) {
        return;
    }
    $done = true;

    $payload = $GLOBALS['cybeorch_wcs_payload'];
    ?>
<script>
window.CYBEORCH_WEBINAR_CLOSING = <?= json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= htmlspecialchars(urlVersioned('assets/js/webinar-closing-soon-popup.js')) ?>"></script>
    <?php
}
