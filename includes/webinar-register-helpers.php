<?php
declare(strict_types=1);

/** Free webinar registration form + catalog CTAs. */
require_once __DIR__ . '/webinar-registration-service.php';

/** Fixed INR fee for all paid webinars (display and checkout). */
const WEBINAR_PAID_FEE_INR = 4750;

/** Fixed USD fee for all paid webinars (display and checkout). */
const WEBINAR_PAID_FEE_USD = 50;

/** @return list<string> */
function webinarSupportedCurrencies(): array
{
    return ['INR', 'USD'];
}

function webinarNormalizeCurrency(?string $currency): string
{
    $currency = strtoupper(trim((string) $currency));
    return in_array($currency, webinarSupportedCurrencies(), true) ? $currency : 'INR';
}

/** @param array{is_free?: bool|int} $item */
function webinarIsPaid(array $item): bool
{
    return empty($item['is_free']);
}

/** Compact paid label for cards, listings, and status display. */
function webinarPaidCompactLabel(): string
{
    return '$ 50 PAID';
}

/** @param array{is_free?: bool|int} $item */
function webinarCardStatusLabel(array $item): string
{
    return webinarIsPaid($item) ? webinarPaidCompactLabel() : 'FREE';
}

/** @param array{is_free?: bool|int} $item */
function webinarFeeDisplayText(array $item): string
{
    return webinarIsPaid($item) ? webinarPaidCompactLabel() : 'FREE';
}

/**
 * @param array{is_free?: bool|int} $item
 */
function webinarCheckoutFee(array $item, string $currency = 'INR'): float
{
    if (!webinarIsPaid($item)) {
        return 0.0;
    }

    return webinarNormalizeCurrency($currency) === 'USD'
        ? (float) WEBINAR_PAID_FEE_USD
        : (float) WEBINAR_PAID_FEE_INR;
}

/** @param array{is_free?: bool|int} $item */
function webinarCheckoutFeeInr(array $item): float
{
    return webinarCheckoutFee($item, 'INR');
}

function webinarFormatCheckoutAmount(string $currency, float $amount): string
{
    return webinarNormalizeCurrency($currency) === 'USD'
        ? '$' . number_format($amount, 0) . ' USD'
        : '₹' . number_format($amount);
}

/**
 * @param array{is_free?: bool|int} $item
 */
function renderWebinarFeeNotice(array $item, string $wrapClass = 'webinar-fee-notice'): void
{
    $wrapClass = htmlspecialchars($wrapClass, ENT_QUOTES, 'UTF-8');

    if (!webinarIsPaid($item)) {
        return;
    }

    echo '<p class="' . $wrapClass . ' webinar-fee-notice--paid webinar-paid-compact" role="status">'
        . htmlspecialchars(webinarPaidCompactLabel(), ENT_QUOTES, 'UTF-8') . '</p>';
}

/**
 * Currency selector for webinar registration / checkout (INR or USD).
 */
function renderWebinarCurrencySelector(string $selectedCurrency, string $fieldName = 'payment_currency', array $opts = []): void
{
    $selectedCurrency = webinarNormalizeCurrency($selectedCurrency);
    $fieldName = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8');
    $label = (string) ($opts['label'] ?? 'Pay in');
    $wrapClass = htmlspecialchars((string) ($opts['wrap_class'] ?? 'webinar-currency-select'), ENT_QUOTES, 'UTF-8');
    $autoSubmit = !empty($opts['auto_submit']);
    ?>
<fieldset class="<?= $wrapClass ?>">
  <legend class="webinar-currency-label"><?= htmlspecialchars($label) ?></legend>
  <div class="webinar-currency-options" role="radiogroup" aria-label="<?= htmlspecialchars($label) ?>">
    <?php
    $feeResolver = $opts['fee_resolver'] ?? null;
    foreach (webinarSupportedCurrencies() as $code):
        $feeAmount = is_callable($feeResolver)
            ? (float) $feeResolver($code)
            : webinarCheckoutFee(['is_free' => 0], $code);
        $feeLabel = is_callable($opts['format_resolver'] ?? null)
            ? (string) $opts['format_resolver']($code, $feeAmount)
            : webinarFormatCheckoutAmount($code, $feeAmount);
    ?>
    <label class="webinar-currency-option<?= $selectedCurrency === $code ? ' is-selected' : '' ?>">
      <input type="radio" name="<?= $fieldName ?>" value="<?= htmlspecialchars($code) ?>"<?= $selectedCurrency === $code ? ' checked' : '' ?><?= $autoSubmit ? ' data-auto-submit="1"' : '' ?>>
      <span class="webinar-currency-code"><?= htmlspecialchars($code) ?></span>
      <span class="webinar-currency-amount"><?= htmlspecialchars($feeLabel) ?></span>
      <?php if ($code === 'INR'): ?>
      <span class="webinar-currency-note">Inclusive of all taxes</span>
      <?php endif; ?>
    </label>
    <?php endforeach; ?>
  </div>
</fieldset>
    <?php
}

function webinarCurrencySelectorStylesCss(): string
{
    return '.webinar-paid-compact{font-size:.88rem;font-weight:600;color:var(--cyber-gold-dark,#b8860b);margin:0 0 .75rem;letter-spacing:.02em}'
        . '.webinar-currency-select{border:0;margin:0 0 1rem;padding:0}'
        . '.webinar-currency-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--cyber-muted,#7a8fa6);font-weight:700;margin-bottom:.5rem;display:block}'
        . '.webinar-currency-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}'
        . '.webinar-currency-option{position:relative;display:flex;flex-direction:column;align-items:flex-start;gap:.15rem;padding:.85rem;border:1px solid var(--cyber-border,rgba(0,212,255,.2));border-radius:10px;cursor:pointer;background:rgba(0,0,0,.2);transition:border-color .2s,background .2s}'
        . '.webinar-currency-option:hover{border-color:rgba(0,212,255,.45)}'
        . '.webinar-currency-option.is-selected{border-color:var(--cyber-accent,#00d4ff);background:rgba(0,212,255,.08);box-shadow:0 0 0 1px rgba(0,212,255,.25)}'
        . '.webinar-currency-option input{position:absolute;opacity:0;pointer-events:none}'
        . '.webinar-currency-code{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--cyber-accent,#00d4ff)}'
        . '.webinar-currency-amount{font-family:\'Rajdhani\',sans-serif;font-size:1.15rem;font-weight:700;color:#fff}'
        . '.webinar-currency-note{font-size:.68rem;color:var(--cyber-muted,#7a8fa6);line-height:1.3}'
        . '@media(max-width:575px){.webinar-currency-options{grid-template-columns:1fr}}';
}

function renderWebinarCurrencySelectorStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style>' . webinarCurrencySelectorStylesCss() . '</style>';
}

/** @param array<string, mixed> $opts */
function renderWebinarRegisterFormFields(array $opts): void
{
    $formId = (string) ($opts['form_id'] ?? 'webinarRegisterForm');
    $apiUrl = (string) ($opts['api_url'] ?? url('api/webinar-register-intake.php'));
    $csrf = (string) ($opts['csrf'] ?? generateCSRF());
    $webinarId = (int) ($opts['webinar_id'] ?? 0);
    $prefill = is_array($opts['prefill'] ?? null) ? $opts['prefill'] : [];
    $wrapClass = (string) ($opts['wrap_class'] ?? 'wr-form-body');

    $name = htmlspecialchars((string) ($prefill['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string) ($prefill['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $mobile = htmlspecialchars((string) ($prefill['mobile'] ?? ''), ENT_QUOTES, 'UTF-8');
    $city = htmlspecialchars((string) ($prefill['city'] ?? ''), ENT_QUOTES, 'UTF-8');
    $org = htmlspecialchars((string) ($prefill['organization'] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
<form id="<?= htmlspecialchars($formId) ?>" class="webinar-register-form" novalidate
      data-api-url="<?= htmlspecialchars($apiUrl) ?>" data-csrf="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="webinar_id" value="<?= $webinarId ?>">
  <div class="<?= htmlspecialchars($wrapClass) ?>">
    <div class="wrc-alert wrc-alert-error wr-form-alert" role="alert" hidden></div>
    <div class="wr-field" data-field="full_name">
      <label for="<?= $formId ?>_name">Full Name <span class="req">*</span></label>
      <input type="text" class="form-control" id="<?= $formId ?>_name" name="full_name" value="<?= $name ?>" required autocomplete="name">
      <div class="wr-field-error"></div>
    </div>
    <div class="wr-field" data-field="email">
      <label for="<?= $formId ?>_email">Email Address <span class="req">*</span></label>
      <input type="email" class="form-control" id="<?= $formId ?>_email" name="email" value="<?= $email ?>" required autocomplete="email">
      <div class="wr-field-error"></div>
    </div>
    <div class="wr-field" data-field="mobile">
      <label for="<?= $formId ?>_mobile">Mobile Number <span class="req">*</span></label>
      <input type="tel" class="form-control" id="<?= $formId ?>_mobile" name="mobile" value="<?= $mobile ?>" required inputmode="numeric" maxlength="14">
      <div class="wr-field-error"></div>
    </div>
    <div class="wr-field" data-field="organization">
      <label for="<?= $formId ?>_org">College / Company Name</label>
      <input type="text" class="form-control" id="<?= $formId ?>_org" name="organization" value="<?= $org ?>">
      <div class="wr-field-error"></div>
    </div>
    <div class="wr-field" data-field="city">
      <label for="<?= $formId ?>_city">City <span class="req">*</span></label>
      <input type="text" class="form-control" id="<?= $formId ?>_city" name="city" value="<?= $city ?>" required autocomplete="address-level2">
      <div class="wr-field-error"></div>
    </div>
    <button type="submit" class="wr-submit">
      <i class="fas fa-check-circle me-1" aria-hidden="true"></i> Register Free
    </button>
  </div>
</form>
    <?php
}

/** @return array{full_name: string, email: string, mobile: string, city: string, organization: string} */
function webinarRegisterPrefillFromSession(): array
{
    $prefill = ['full_name' => '', 'email' => '', 'mobile' => '', 'city' => '', 'organization' => ''];
    $profile = webinarIntakeProfileFromSession();
    if ($profile) {
        return array_merge($prefill, $profile);
    }
    return $prefill;
}

/** @param string $extraClass Extra CSS classes on the link */
function renderWebinarRegisterFreeButton(int $webinarId, string $title, string $extraClass = '', string $inlineStyle = ''): void
{
    $class = 'btn-primary-cyber ' . trim($extraClass);
    $styleAttr = $inlineStyle !== '' ? ' style="' . htmlspecialchars($inlineStyle) . '"' : '';

    if ($webinarId < 1) {
        echo '<a href="' . htmlspecialchars(url('webinars.php')) . '" class="' . htmlspecialchars($class) . '"' . $styleAttr
            . '><i class="fas fa-bolt me-1"></i>Register Free</a>';
        return;
    }

    $href = webinarRegistrationRegisterFreeUrl($webinarId);
    echo '<a href="' . htmlspecialchars($href) . '" class="' . htmlspecialchars($class) . '"' . $styleAttr
        . ' title="' . htmlspecialchars('Register for ' . $title, ENT_QUOTES) . '"'
        . '><i class="fas fa-bolt me-1"></i>Register Free</a>';
}

/**
 * Paid webinar label for dropdowns and admin lists.
 */
function webinarPaidOptionPriceLabel(): string
{
    return webinarPaidCompactLabel();
}

/**
 * Unified Register Free / Register Now CTA for homepage and webinars.php.
 *
 * @param array{id?: int, slug?: string, title?: string, is_free?: bool|int, fee?: float|int, webinar_slug?: string} $item
 */
function renderWebinarCardRegisterCta(
    array $item,
    string $extraClass = 'w-100 text-center',
    string $inlineStyle = 'font-size:0.9rem; padding:0.65rem'
): void {
    $title = (string) ($item['title'] ?? 'Webinar');
    $class = 'btn-primary-cyber ' . trim($extraClass);
    $styleAttr = $inlineStyle !== '' ? ' style="' . htmlspecialchars($inlineStyle) . '"' : '';

    if (!empty($item['is_free'])) {
        $webinarId = isset($item['id']) && (int) $item['id'] > 0
            ? (int) $item['id']
            : publicResolveWebinarIdForCatalogCard($item);
        renderWebinarRegisterFreeButton($webinarId, $title, $extraClass, $inlineStyle);
        return;
    }

    if (isset($item['id']) && (int) $item['id'] > 0) {
        $href = url('secure-payment.php?type=webinar&id=' . (int) $item['id']);
    } else {
        $slug = (string) ($item['slug'] ?? '');
        $href = url('secure-payment.php?type=webinar&program=' . rawurlencode($slug));
    }

    echo '<a href="' . htmlspecialchars($href) . '" class="' . htmlspecialchars($class) . '"' . $styleAttr
        . ' title="' . htmlspecialchars('Register for ' . $title, ENT_QUOTES) . '"'
        . '><i class="fas fa-ticket me-1"></i>Register Now</a>';
}

/** CYBEORCH LAB Program Path funnel slugs → Secure Payment URL. */
function bootcampProgramPathPaymentUrl(string $programSlug): string
{
    $allowed = ['30-day-bootcamp', '90-day-bootcamp', 'premium-industry-lab'];
    if (!in_array($programSlug, $allowed, true)) {
        return url('secure-payment.php?type=bootcamp');
    }
    $row = publicFetchBootcampBySlug($programSlug);

    return $row ? bootcampSecurePaymentUrl($row) : url('secure-payment.php?type=bootcamp&slug=' . rawurlencode($programSlug));
}

/** Secure Payment URL for a bootcamp (same entry point as paid webinars). */
function bootcampSecurePaymentUrl(array $bootcamp): string
{
    $id = (int) ($bootcamp['id'] ?? 0);
    if ($id > 0) {
        return url('secure-payment.php?type=bootcamp&id=' . $id);
    }
    $slug = trim((string) ($bootcamp['slug'] ?? ''));

    return $slug !== ''
        ? url('secure-payment.php?type=bootcamp&slug=' . rawurlencode($slug))
        : url('secure-payment.php?type=bootcamp');
}

/**
 * Enquire & Enroll CTA for bootcamp cards (Secure Payment flow when fee applies).
 *
 * @param array{id?: int, slug?: string, title?: string, discounted_fee?: float|int} $item
 */
function renderBootcampCardRegisterCta(
    array $item,
    string $extraClass = 'w-100 text-center',
    string $inlineStyle = 'font-size:0.9rem; padding:0.65rem'
): void {
    $title = (string) ($item['title'] ?? 'Bootcamp');
    $class = 'btn-primary-cyber ' . trim($extraClass);
    $styleAttr = $inlineStyle !== '' ? ' style="' . htmlspecialchars($inlineStyle) . '"' : '';
    $fee = (float) ($item['discounted_fee'] ?? 0);

    $href = $fee > 0
        ? bootcampSecurePaymentUrl($item)
        : url('enquire-enroll.php' . (($slug = trim((string) ($item['slug'] ?? ''))) !== '' ? '?bootcamp=' . rawurlencode($slug) : ''));

    echo '<a href="' . htmlspecialchars($href) . '" class="' . htmlspecialchars($class) . '"' . $styleAttr
        . ' title="' . htmlspecialchars('Enquire and enroll in ' . $title, ENT_QUOTES) . '"'
        . '><i class="fas fa-rocket me-2"></i>Enquire &amp; Enroll</a>';
}
