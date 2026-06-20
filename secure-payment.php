<?php
require_once __DIR__ . '/includes/public-enquiry.php';
require_once __DIR__ . '/includes/public-catalog.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/includes/webinar-register-helpers.php';
require_once __DIR__ . '/includes/secure-payment-program.php';
require_once __DIR__ . '/includes/nxl-wallet.php';

startSession();

$pageTitle = 'Secure Payment';
$navActive = '';

$paidWebinarTracks = [
    'cybersecurity' => [
        'title' => 'Cybersecurity', 'desc' => 'Ethical hacking, SOC workflows, and cloud security.',
        'fee' => 2999, 'duration' => '120 min', 'schedule' => '07 Jun 2026 · 11:00 AM', 'instructor' => 'Kanchan',
    ],
    'ai-ml' => [
        'title' => 'AI / ML', 'desc' => 'Machine learning, AI tooling, and production automation.',
        'fee' => 2999, 'duration' => '120 min', 'schedule' => '20 Jun 2026 · 11:00 AM', 'instructor' => 'Kanchan',
    ],
    'blockchain' => [
        'title' => 'Blockchain', 'desc' => 'Web3, smart contracts, and wallet security.',
        'fee' => 2999, 'duration' => '120 min', 'schedule' => '27 Jun 2026 · 11:00 AM', 'instructor' => 'Kanchan',
    ],
    'devops' => [
        'title' => 'DevOps', 'desc' => 'CI/CD, containers, Kubernetes, and pipeline security.',
        'fee' => 2999, 'duration' => '120 min', 'schedule' => '07 Jun 2026 · 11:00 AM', 'instructor' => 'Kanchan',
    ],
];

$paidBootcamps = array_values(array_filter(
    publicFetchDisplayBootcamps(),
    static fn (array $b): bool => bootcampFeeInr(bootcampRowWithProgramDefaults($b)) > 0
));
$paidWebinarsDb = array_values(array_filter(
    publicFetchWebinars(),
    static fn (array $w): bool => webinarIsPaid($w)
));

$paymentCurrency = webinarNormalizeCurrency($_GET['currency'] ?? $_POST['payment_currency'] ?? 'INR');

$programResolved = resolveSecurePaymentProgram($paidWebinarTracks, $paymentCurrency);
$selectedType       = $programResolved['selectedType'];
$selectedSlug       = $programResolved['selectedSlug'];
$selectedId         = $programResolved['selectedId'];
$row                = $programResolved['row'];
$programLabel       = $programResolved['programLabel'];
$programDesc        = $programResolved['programDesc'];
$programMeta        = $programResolved['programMeta'];
$programFee         = $programResolved['programFee'];
$programPickerValue = $programResolved['programPickerValue'];
$programPathOptions = securePaymentProgramPathDropdownOptions();

$bootcampPricingRow = ($selectedType === 'bootcamp' && is_array($row))
    ? bootcampRowWithProgramDefaults($row)
    : [];
$webinarPricingRow = ($selectedType === 'webinar' && is_array($row)) ? $row : [];
$webinarDualCurrency = $selectedType === 'webinar' && webinarIsPaid($webinarPricingRow);
$bootcampDualCurrency = $selectedType === 'bootcamp' && bootcampShowsDualCurrency($bootcampPricingRow);
$bootcampAmountInr = $bootcampDualCurrency
    ? bootcampFormatCheckoutAmount('INR', bootcampCheckoutFee($bootcampPricingRow, 'INR'), $bootcampPricingRow)
    : '';
$bootcampAmountUsd = $bootcampDualCurrency
    ? bootcampFormatCheckoutAmount('USD', bootcampCheckoutFee($bootcampPricingRow, 'USD'), $bootcampPricingRow)
    : '';
$bootcampDisplayAmount = $selectedType === 'bootcamp'
    ? bootcampFormatCheckoutAmount($paymentCurrency, $programFee, $bootcampPricingRow)
    : '';
$webinarAmountInr = $webinarDualCurrency
    ? webinarFormatCheckoutAmount('INR', webinarCheckoutFee($webinarPricingRow, 'INR'), webinarPriceDecimals($webinarPricingRow))
    : '';
$webinarAmountUsd = $webinarDualCurrency
    ? webinarFormatCheckoutAmount('USD', webinarCheckoutFee($webinarPricingRow, 'USD'))
    : '';

$paymentMethods = cybeorchPaymentMethodOptions();

$walletSummary = null;
$walletBalance = 0.0;
$walletMaxTokens = 0.0;
$nxlTokensApplied = 0.0;
$walletInrDiscount = 0.0;
$payableAfterWallet = $programFee;
$walletGatewayEnabled = false;
$nxlWalletSectionEnabled = false;
$walletLoggedIn = isLoggedIn();

if ($walletLoggedIn) {
    $walletSummary = getWalletSummaryForUser((int) $_SESSION['user_id']);
    $walletBalance = (float) $walletSummary['balance'];
    $nxlWalletSectionEnabled = $paymentCurrency === 'INR' && $programFee > 0;
    $walletGatewayEnabled = $nxlWalletSectionEnabled && $walletBalance > 0;
    if ($nxlWalletSectionEnabled) {
        $walletMaxTokens = calculateNxlRedemption($programFee, $walletBalance, 0)['max_tokens_allowed'];
    }
    $nxlTokensRequested = (float) ($_POST['nxl_tokens'] ?? $_GET['nxl_tokens'] ?? 0);
    if ($walletGatewayEnabled && $nxlTokensRequested > 0) {
        $walletRedemption = calculateNxlRedemption($programFee, $walletBalance, $nxlTokensRequested);
        if ($walletRedemption['valid']) {
            $nxlTokensApplied = $walletRedemption['tokens_to_use'];
            $walletInrDiscount = $walletRedemption['inr_discount'];
            $payableAfterWallet = $walletRedemption['remaining_payable'];
        }
    }
}

$selectedGateway = (string) ($_POST['payment_gateway'] ?? $_GET['gateway'] ?? 'razorpay');
if (!isset($paymentMethods[$selectedGateway])) {
    $selectedGateway = 'razorpay';
}

$bankDetails = cybeorchBankPaymentDetails();
$paymentRef = cybeorchPaymentReferenceCode($selectedSlug, $programFee);
$razorpayReady = razorpayIsConfigured();
$phonepeReady = defined('PHONEPE_MERCHANT_ID') && PHONEPE_MERCHANT_ID !== '';

$prefillName = trim((string) ($_GET['name'] ?? ''));
$prefillEmail = trim((string) ($_GET['email'] ?? ''));
$prefillPhone = trim((string) ($_GET['phone'] ?? ''));
if (isLoggedIn()) {
    $u = dbTry(fn () => db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id=?', [(int) $_SESSION['user_id']]), null);
    if ($u) {
        $prefillName = $prefillName !== '' ? $prefillName : (string) ($u['full_name'] ?? '');
        $prefillEmail = $prefillEmail !== '' ? $prefillEmail : (string) ($u['email'] ?? '');
        $prefillPhone = $prefillPhone !== '' ? $prefillPhone : (string) ($u['phone'] ?? '');
    }
}

$checkoutUrl = '';
if ($walletLoggedIn && isPublicAuthEnabled() && $selectedId > 0 && $programFee > 0 && $razorpayReady) {
    $checkoutUrl = url('checkout.php?type=' . rawurlencode($selectedType) . '&id=' . $selectedId . '&proceed=1');
    if ($selectedType === 'webinar' || $bootcampDualCurrency) {
        $checkoutUrl .= '&currency=' . rawurlencode($paymentCurrency);
    }
    if ($nxlTokensApplied > 0 && $paymentCurrency === 'INR') {
        $checkoutUrl .= '&nxl_tokens=' . rawurlencode((string) (int) round($nxlTokensApplied));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $gatewayLabel = $paymentMethods[$selectedGateway]['label'] ?? $selectedGateway;
    $txnRef = trim((string) ($_POST['transaction_id'] ?? ''));
    $manualGateways = ['upi', 'netbanking'];
    $postedGateway = (string) ($_POST['payment_gateway'] ?? $selectedGateway);
    $postedNxlTokens = (float) ($_POST['nxl_tokens'] ?? 0);

    if ($postedGateway === 'wallet' && $postedNxlTokens > 0) {
        if (!$walletLoggedIn) {
            setFlash('error', 'Please sign in to pay with your NxL wallet.');
        } elseif ($paymentCurrency !== 'INR') {
            setFlash('error', 'NxL wallet can only be used for INR payments.');
        } elseif ($walletBalance <= 0) {
            setFlash('error', 'Insufficient Wallet Balance.');
        } elseif ($selectedId < 1) {
            setFlash('error', 'Select a scheduled webinar or bootcamp from the list to pay with NxL wallet.');
        } else {
            $userRow = db()->fetchOne('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['user_id']]) ?: [];
            $result = completeSecurePaymentWithNxlWallet(
                (int) $_SESSION['user_id'],
                $selectedType,
                $selectedId,
                $programFee,
                $postedNxlTokens,
                $programLabel,
                $userRow
            );
            if ($result['success']) {
                redirectToRegistrationSuccess($selectedType, $selectedId);
            }
            setFlash('error', $result['message']);
        }
    } elseif (in_array($selectedGateway, $manualGateways, true) && ($txnRef === '' || strlen($txnRef) < 4)) {
        setFlash('error', 'Please enter your UTR / transaction ID after completing payment.');
    } else {
        handlePublicEnquiryPost('secure-payment.php', 'Secure Payment – Webinar / Bootcamp', [
            'Program Type'      => ucfirst($selectedType),
            'Program'           => $programLabel,
            'Program Slug'      => $selectedSlug !== '' ? $selectedSlug : '—',
            'Program ID'        => $selectedId > 0 ? (string) $selectedId : '—',
            'Amount'            => $selectedType === 'webinar'
                ? webinarFormatCheckoutAmount($paymentCurrency, $programFee)
                : bootcampFormatCheckoutAmount($paymentCurrency, $programFee, $bootcampPricingRow),
            'Payment Currency'  => ($selectedType === 'webinar' || $bootcampDualCurrency) ? $paymentCurrency : 'INR',
            'NxL Tokens Used'   => $postedNxlTokens > 0 ? (int) round($postedNxlTokens) . ' NxL' : 'None',
            'Remaining Payable' => $postedNxlTokens > 0 && $paymentCurrency === 'INR'
                ? ($selectedType === 'webinar'
                    ? webinarFormatCheckoutAmount($paymentCurrency, max(0, $programFee - nxlTokensToInrDiscount($postedNxlTokens)))
                    : bootcampFormatCheckoutAmount($paymentCurrency, max(0, $programFee - nxlTokensToInrDiscount($postedNxlTokens)), $bootcampPricingRow))
                : ($selectedType === 'webinar'
                    ? webinarFormatCheckoutAmount($paymentCurrency, $programFee)
                    : bootcampFormatCheckoutAmount($paymentCurrency, $programFee, $bootcampPricingRow)),
            'Payment Gateway'   => $gatewayLabel,
            'Payment Reference' => $paymentRef,
            'Transaction ID'    => $txnRef,
            'Status'            => $razorpayReady && $selectedGateway === 'razorpay'
                ? 'Awaiting Razorpay confirmation'
                : 'Pending verification',
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LABS</title>
<meta name="description" content="Secure payment for CYBEORCH LABS webinars and bootcamps.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
<style>
.secure-pay-hero-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:.35rem .75rem;border-radius:999px;background:rgba(0,255,136,.12);border:1px solid rgba(0,255,136,.35);color:var(--cyber-green);margin-bottom:1rem}
.course-summary{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:14px;padding:1.5rem;position:sticky;top:1rem}
.course-summary h3{font-family:'Rajdhani',sans-serif;font-size:1.35rem;font-weight:700;color:#fff;margin:0 0 .5rem}
.course-type{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--cyber-accent);font-weight:700}
.course-meta{list-style:none;padding:0;margin:1rem 0 0}
.course-meta li{font-size:.86rem;color:var(--cyber-muted);padding:.3rem 0;display:flex;gap:.5rem}
.course-meta i{color:var(--cyber-accent);width:1rem}
.amount-box{margin-top:1.25rem;padding:1rem;border-radius:10px;background:rgba(0,0,0,.25);border:1px solid var(--cyber-border)}
.amount-label{font-size:.8rem;color:var(--cyber-muted)}
.amount-value{font-family:'Rajdhani',sans-serif;font-size:2.1rem;font-weight:700;color:var(--cyber-green);line-height:1.1}
.webinar-fee-notice{font-size:.88rem;line-height:1.5;color:var(--cyber-text);margin:1rem 0 0}
.webinar-fee-notice strong{font-weight:700;color:#fff}
<?= webinarCurrencySelectorStylesCss() ?>
.secure-strip{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1rem;font-size:.78rem;color:var(--cyber-muted)}
.secure-strip span{display:inline-flex;align-items:center;gap:.35rem}
.pay-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:14px;padding:1.75rem}
.pay-step-label{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--cyber-accent);font-weight:700;margin-bottom:.35rem}
.gateway-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem;margin:1rem 0}
.gateway-tile{position:relative;border:1px solid var(--cyber-border);border-radius:10px;padding:.85rem .75rem;cursor:pointer;transition:all .2s;background:rgba(255,255,255,.02);min-height:100%}
.gateway-tile:hover{border-color:rgba(0,212,255,.45)}
.gateway-tile.is-active{border-color:var(--cyber-accent);background:rgba(0,212,255,.08);box-shadow:0 0 0 1px rgba(0,212,255,.25)}
.gateway-tile input{position:absolute;opacity:0;pointer-events:none}
.gateway-icon{width:36px;height:36px;border-radius:8px;background:rgba(0,212,255,.1);display:flex;align-items:center;justify-content:center;color:var(--cyber-accent);font-size:1rem;margin-bottom:.5rem}
.gateway-name{font-weight:700;font-size:.88rem;color:var(--cyber-text)}
.gateway-desc{font-size:.72rem;color:var(--cyber-muted);line-height:1.4;margin-top:.25rem}
.gateway-badge{position:absolute;top:.5rem;right:.5rem;font-size:.62rem;font-weight:700;padding:.15rem .45rem;border-radius:4px;background:rgba(0,212,255,.15);color:var(--cyber-accent)}
.gateway-panel{margin-top:1rem;padding:1rem;border-radius:10px;border:1px dashed rgba(0,212,255,.35);background:rgba(0,212,255,.04);font-size:.85rem;color:var(--cyber-muted);line-height:1.6}
.gateway-panel strong{color:var(--cyber-accent)}
.gateway-placeholder-tag{display:inline-block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:.2rem .5rem;border-radius:4px;background:rgba(255,107,53,.15);color:var(--cyber-orange);border:1px solid rgba(255,107,53,.3);margin-left:.35rem}
.pay-btn-main{width:100%;margin-top:1rem;padding:1rem 1.25rem;border:none;border-radius:10px;font-weight:800;font-size:1rem;cursor:pointer;background:linear-gradient(135deg,var(--cyber-accent),#00a8cc);color:#050b18;transition:transform .2s,box-shadow .2s}
.pay-btn-main:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 12px 32px rgba(0,212,255,.35)}
.pay-btn-main:disabled{opacity:.5;cursor:not-allowed}
.gateway-tile.is-disabled{opacity:.45;cursor:not-allowed;pointer-events:none;filter:grayscale(.35)}
.gateway-tile .nxl-balance-tag{display:block;margin-top:.35rem;font-family:'Rajdhani',sans-serif;font-size:.95rem;font-weight:700;color:var(--cyber-green)}
.nxl-wallet-panel .nxl-stat-row{display:flex;justify-content:space-between;align-items:center;padding:.35rem 0;font-size:.84rem;color:var(--cyber-muted)}
.nxl-wallet-panel .nxl-stat-row strong{color:#fff}
.nxl-wallet-panel .nxl-stat-row .green{color:var(--cyber-green);font-weight:600}
.nxl-wallet-panel .nxl-balance-hero{margin:.75rem 0 1rem;padding:.85rem;border-radius:8px;background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.25)}
.nxl-wallet-panel .nxl-balance-hero .lbl{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--cyber-muted)}
.nxl-wallet-panel .nxl-balance-hero .val{font-family:'Rajdhani',sans-serif;font-size:1.35rem;font-weight:700;color:var(--cyber-green)}
.nxl-wallet-panel input[type=number]{width:100%;background:rgba(0,0,0,.25);border:1px solid var(--cyber-border);border-radius:8px;color:var(--cyber-text);padding:.55rem .75rem;margin:.5rem 0}
.nxl-wallet-panel .nxl-use-max{display:inline-block;margin-bottom:.75rem;background:rgba(0,255,136,.12);border:1px solid rgba(0,255,136,.3);color:var(--cyber-green);padding:.35rem .75rem;border-radius:6px;font-size:.78rem;cursor:pointer;border:none}
.nxl-wallet-panel .nxl-insufficient{color:#ff8888;font-size:.84rem;margin:.5rem 0 0}
.nxl-wallet-panel .nxl-signin{color:var(--cyber-muted);font-size:.84rem}
.nxl-wallet-panel .nxl-signin a{color:var(--cyber-accent)}
.nxl-wallet-addon{margin-top:1rem;padding-top:1rem;border-top:1px dashed rgba(0,255,136,.25)}
.nxl-wallet-addon-title{font-family:'Rajdhani',sans-serif;font-size:.95rem;font-weight:700;color:var(--cyber-green);margin:0 0 .65rem;display:flex;align-items:center;gap:.4rem}
.nxl-wallet-panel .nxl-checkout-link{display:inline-block;margin-top:.65rem;color:var(--cyber-accent);font-size:.82rem}
.nxl-wallet-panel .nxl-spend-limit-notice{font-size:.78rem;color:var(--cyber-muted);margin:.5rem 0 0;line-height:1.4}
.nxl-wallet-panel .nxl-spend-limit-error{font-size:.78rem;color:#ff8888;margin:.35rem 0 0;display:none}
.upi-qr-mock{width:140px;height:140px;border-radius:10px;border:1px solid var(--cyber-border);background:rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;margin:.75rem 0;color:var(--cyber-muted);font-size:.75rem;text-align:center;padding:.5rem}
@media(max-width:991px){.course-summary{position:static;margin-bottom:1.5rem}}
@media(max-width:575px){.gateway-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container text-center">
    <div class="secure-pay-hero-badge"><i class="fas fa-lock" aria-hidden="true"></i> SSL Secured Checkout</div>
    <h1>Secure <span class="accent">Payment</span></h1>
    <p>Pay for webinars or bootcamps using Razorpay, PhonePe, UPI, cards, net banking, or wallets.</p>
  </div>
</header>

<main class="section pt-0">
  <div class="container">
    <div class="row g-4 align-items-start">
      <div class="col-lg-5">
        <aside class="course-summary">
          <div class="course-type"><?= $selectedType === 'bootcamp' ? 'Bootcamp' : 'Webinar' ?></div>
          <h3><?= htmlspecialchars($programLabel) ?></h3>
          <?php if ($programDesc !== ''): ?>
          <p style="font-size:.88rem;color:var(--cyber-muted);line-height:1.65;margin:0"><?= htmlspecialchars($programDesc) ?></p>
          <?php endif; ?>
          <ul class="course-meta">
            <?php foreach ($programMeta as $label => $val): ?>
            <?php if (trim((string) $val) !== ''): ?>
            <li><i class="fas fa-circle-check" aria-hidden="true"></i><span><strong><?= htmlspecialchars($label) ?>:</strong> <?= htmlspecialchars((string) $val) ?></span></li>
            <?php endif; ?>
            <?php endforeach; ?>
            <li><i class="fas fa-hashtag" aria-hidden="true"></i><span>Ref: <code style="color:var(--cyber-green)"><?= htmlspecialchars($paymentRef) ?></code></span></li>
          </ul>
          <div class="amount-box">
            <div class="amount-label">Total amount<?= ($webinarDualCurrency || $bootcampDualCurrency) ? ' (' . htmlspecialchars($paymentCurrency) . ')' : ' (INR)' ?></div>
            <div class="amount-value" id="summaryAmountValue"><?= htmlspecialchars($selectedType === 'webinar' ? webinarFormatCheckoutAmount($paymentCurrency, $programFee, webinarPriceDecimals($webinarPricingRow)) : $bootcampDisplayAmount) ?></div>
            <?php if ($webinarDualCurrency || $bootcampDualCurrency): ?>
            <div class="bootcamp-dual-price-summary" id="summaryDualPrice" style="font-size:.88rem;color:var(--cyber-muted);margin-top:.5rem;line-height:1.5">
              <div id="summaryDualInr"><?= htmlspecialchars($selectedType === 'webinar' ? $webinarAmountInr : $bootcampAmountInr) ?></div>
              <div id="summaryDualUsd">(<?= htmlspecialchars($selectedType === 'webinar' ? $webinarAmountUsd : $bootcampAmountUsd) ?>)</div>
            </div>
            <?php endif; ?>
            <div style="font-size:.78rem;color:var(--cyber-muted);margin-top:.35rem" id="summaryAmountNote"><?= ($webinarDualCurrency || $bootcampDualCurrency) ? ($paymentCurrency === 'INR' ? 'Inclusive of all taxes' : 'USD calculated from INR fee') : 'GST included · INR' ?></div>
          </div>
          <div class="secure-strip">
            <span><i class="fas fa-shield-halved"></i>256-bit encryption</span>
            <span><i class="fas fa-lock"></i>PCI-ready gateways</span>
            <span><i class="fas fa-receipt"></i>Instant receipt</span>
          </div>
        </aside>
      </div>

      <div class="col-lg-7">
        <div class="pay-card">
          <?= showFlash() ?>

          <?php if ($checkoutUrl !== ''): ?>
          <div class="info-card mb-4" style="border-color:rgba(0,255,136,.35)">
            <p style="font-size:.88rem;color:var(--cyber-muted);margin:0 0 .75rem">Signed in — use live Razorpay checkout for this item.</p>
            <a href="<?= htmlspecialchars($checkoutUrl) ?>" class="btn-primary-cyber"><i class="fas fa-lock me-2"></i>Quick Razorpay Checkout</a>
          </div>
          <?php endif; ?>

          <form method="POST" action="<?= url('secure-payment.php') ?>" id="securePayForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="program_type" value="<?= htmlspecialchars($selectedType) ?>">
            <input type="hidden" name="program_slug" value="<?= htmlspecialchars($selectedSlug) ?>">
            <input type="hidden" name="program_id" value="<?= $selectedId ?>">
            <input type="hidden" name="require_phone" value="1">
            <input type="hidden" name="payment_currency" id="paymentCurrencyField" value="<?= htmlspecialchars($paymentCurrency) ?>">

            <div class="pay-step-label">Step 1 — Program</div>
            <select class="form-select mb-4" id="programPicker" aria-label="Select program">
              <optgroup label="Webinar tracks">
                <?php foreach ($paidWebinarTracks as $slug => $t):
                    $pickerVal = 'webinar:' . $slug;
                ?>
                <option value="<?= htmlspecialchars($pickerVal) ?>"<?= securePaymentProgramOptionSelected($pickerVal, $programPickerValue, $selectedType, $selectedId, $selectedSlug) ? ' selected' : '' ?>>
                  <?= htmlspecialchars($t['title']) ?> — <?= htmlspecialchars(webinarPaidOptionPriceLabel(['is_free' => 0, 'fee' => (float) ($t['fee'] ?? WEBINAR_PAID_FEE_INR)])) ?>
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php if ($paidWebinarsDb): ?>
              <optgroup label="Scheduled webinars">
                <?php foreach ($paidWebinarsDb as $w):
                    $pickerVal = 'webinar-id:' . (int) $w['id'];
                ?>
                <option value="<?= htmlspecialchars($pickerVal) ?>"<?= securePaymentProgramOptionSelected($pickerVal, $programPickerValue, $selectedType, $selectedId, $selectedSlug) ? ' selected' : '' ?>>
                  <?= escHtml((string) $w['title']) ?> — <?= htmlspecialchars(webinarPaidOptionPriceLabel($w)) ?>
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
              <?php if ($programPathOptions): ?>
              <optgroup label="Program tracks">
                <?php foreach ($programPathOptions as $pathOpt): ?>
                <option value="<?= htmlspecialchars($pathOpt['pickerVal']) ?>"<?= securePaymentProgramOptionSelected($pathOpt['pickerVal'], $programPickerValue, $selectedType, $selectedId, $selectedSlug, $pathOpt['slug']) ? ' selected' : '' ?>>
                  <?= htmlspecialchars($pathOpt['label']) ?>
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
              <?php if ($paidBootcamps): ?>
              <optgroup label="Bootcamps">
                <?php foreach ($paidBootcamps as $b):
                    $b = bootcampRowWithProgramDefaults($b);
                    $pickerVal = 'bootcamp-id:' . (int) $b['id'];
                    $bootSlug = (string) ($b['slug'] ?? '');
                ?>
                <option value="<?= htmlspecialchars($pickerVal) ?>"<?= securePaymentProgramOptionSelected($pickerVal, $programPickerValue, $selectedType, $selectedId, $selectedSlug, $bootSlug) ? ' selected' : '' ?>>
                  <?= htmlspecialchars($b['title']) ?> — <?= htmlspecialchars(bootcampOptionPriceLabel($b)) ?>
                </option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
            </select>

            <?php if ($webinarDualCurrency || $bootcampDualCurrency): ?>
            <div class="pay-step-label mt-4">Step 2 — Payment currency</div>
            <?php
            $currencyOpts = ['label' => 'Choose INR or USD before payment'];
            if ($bootcampDualCurrency) {
                $currencyOpts['fee_resolver'] = static fn (string $code): float => bootcampCheckoutFee($bootcampPricingRow, $code);
                $currencyOpts['format_resolver'] = static fn (string $code, float $amt): string => bootcampFormatCheckoutAmount($code, $amt, $bootcampPricingRow);
            } elseif ($webinarDualCurrency) {
                $currencyOpts['fee_resolver'] = static fn (string $code): float => webinarCheckoutFee($webinarPricingRow, $code);
                $currencyOpts['format_resolver'] = static fn (string $code, float $amt): string => webinarFormatCheckoutAmount($code, $amt, webinarPriceDecimals($webinarPricingRow));
            }
            renderWebinarCurrencySelector($paymentCurrency, 'payment_currency', $currencyOpts);
            ?>
            <?php endif; ?>

            <div class="pay-step-label<?= ($webinarDualCurrency || $bootcampDualCurrency) ? ' mt-4' : '' ?>">Step <?= ($webinarDualCurrency || $bootcampDualCurrency) ? '3' : '2' ?> — Your details</div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label">Full name *</label>
                <input type="text" name="name" class="form-control" required value="<?= postVal('name', $prefillName) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="payEmail">Gmail *</label>
                <input type="email" name="email" id="payEmail" class="form-control" required
                  pattern="[a-zA-Z0-9]([a-zA-Z0-9._%+-]{0,62}[a-zA-Z0-9])?@gmail\.com"
                  title="<?= htmlspecialchars(GMAIL_VALIDATION_MESSAGE) ?>"
                  value="<?= postVal('email', $prefillEmail) ?>">
                <div id="payEmailError" class="field-error" role="alert" hidden><?= htmlspecialchars(GMAIL_VALIDATION_MESSAGE) ?></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone *</label>
                <input type="tel" name="phone" class="form-control" required value="<?= postVal('phone', $prefillPhone) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Organization</label>
                <input type="text" name="organization" class="form-control" value="<?= postVal('organization') ?>">
              </div>
            </div>

            <div class="pay-step-label">Step <?= ($selectedType === 'webinar' || $bootcampDualCurrency) ? '4' : '3' ?> — Payment method</div>
            <div class="gateway-grid" role="radiogroup" aria-label="Payment method">
              <?php foreach ($paymentMethods as $key => $method): ?>
              <label class="gateway-tile<?= $selectedGateway === $key ? ' is-active' : '' ?>">
                <input type="radio" name="payment_gateway" value="<?= htmlspecialchars($key) ?>"<?= $selectedGateway === $key ? ' checked' : '' ?>>
                <?php if ($method['badge'] !== ''): ?><span class="gateway-badge"><?= htmlspecialchars($method['badge']) ?></span><?php endif; ?>
                <div class="gateway-icon"><i class="fas <?= htmlspecialchars($method['icon']) ?>" aria-hidden="true"></i></div>
                <div class="gateway-name"><?= htmlspecialchars($method['label']) ?></div>
                <div class="gateway-desc"><?= htmlspecialchars($method['desc']) ?></div>
                <?php if ($key === 'wallet' && $walletLoggedIn && $paymentCurrency === 'INR'): ?>
                <span class="nxl-balance-tag"><?= number_format($walletBalance, 0) ?> NXL available</span>
                <?php endif; ?>
              </label>
              <?php endforeach; ?>
            </div>

            <div class="gateway-panel nxl-wallet-panel" id="gatewayPanel">
              <!-- filled by JS -->
            </div>

            <input type="hidden" name="nxl_tokens" id="nxlTokensField" value="<?= (int) round($nxlTokensApplied) ?>">

            <div id="txnFieldWrap" hidden>
              <label class="form-label" for="transactionId">UTR / Transaction ID *</label>
              <input type="text" name="transaction_id" id="transactionId" class="form-control" placeholder="After payment, paste reference here" value="<?= postVal('transaction_id') ?>" minlength="4" maxlength="64">
            </div>

            <button type="submit" class="pay-btn-main" id="paySubmitBtn" disabled>
              <i class="fas fa-lock me-2"></i><span id="paySubmitText">Pay <?= htmlspecialchars($selectedType === 'webinar' ? webinarFormatCheckoutAmount($paymentCurrency, $programFee) : $bootcampDisplayAmount) ?> Securely</span>
            </button>
            <p class="checkout-hint mt-2 mb-0" style="font-size:.82rem">
              By paying you agree to our <a href="<?= url('terms.php') ?>" style="color:var(--cyber-accent)">Terms</a>.
              Need help? <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">Contact support</a>.
            </p>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderSiteScripts(true); ?>
<script src="<?= url('assets/js/gmail-email-validation.js') ?>"></script>
<script>
(function () {
  var baseUrl = <?= json_encode(url('secure-payment.php')) ?>;
  var isWebinar = <?= $selectedType === 'webinar' ? 'true' : 'false' ?>;
  var isDualCurrency = <?= ($webinarDualCurrency || $bootcampDualCurrency) ? 'true' : 'false' ?>;
  var webinarAmounts = { INR: <?= json_encode($webinarAmountInr !== '' ? $webinarAmountInr : webinarFormatCheckoutAmount('INR', (float) WEBINAR_PAID_FEE_INR)) ?>, USD: <?= json_encode($webinarAmountUsd !== '' ? $webinarAmountUsd : webinarFormatCheckoutAmount('USD', cybeorchConvertInrToUsd((float) WEBINAR_PAID_FEE_INR))) ?> };
  var bootcampAmounts = { INR: <?= json_encode($bootcampAmountInr) ?>, USD: <?= json_encode($bootcampAmountUsd) ?> };
  var amount = <?= json_encode($selectedType === 'webinar' ? webinarFormatCheckoutAmount($paymentCurrency, $programFee) : $bootcampDisplayAmount) ?>;
  var feeInr = <?= json_encode($paymentCurrency === 'INR' ? $programFee : 0) ?>;
  var walletBalance = <?= json_encode($walletBalance) ?>;
  var walletMaxTokens = <?= json_encode((int) floor($walletMaxTokens)) ?>;
  var walletSpendMaxPercent = <?= json_encode(nxlMaxWalletBalanceSpendPercent()) ?>;
  var walletSpendLimitMsg = <?= json_encode(nxlWalletSpendLimitMessage()) ?>;
  var nxlInrValue = <?= json_encode(nxlInrValuePerToken()) ?>;
  var walletLoggedIn = <?= $walletLoggedIn ? 'true' : 'false' ?>;
  var walletGatewayEnabled = <?= $walletGatewayEnabled ? 'true' : 'false' ?>;
  var nxlWalletSectionEnabled = <?= $nxlWalletSectionEnabled ? 'true' : 'false' ?>;
  var currencyCode = <?= json_encode($paymentCurrency) ?>;
  var checkoutUrlBase = <?= json_encode($checkoutUrl) ?>;
  var loginUrl = <?= json_encode(url('login.php')) ?>;
  var selectedProgramId = <?= (int) $selectedId ?>;
  var paymentRef = <?= json_encode($paymentRef) ?>;
  var upiId = <?= json_encode($bankDetails['upi_id']) ?>;
  var razorpayReady = <?= $razorpayReady ? 'true' : 'false' ?>;
  var phonepeReady = <?= $phonepeReady ? 'true' : 'false' ?>;
  var initialNxlTokens = <?= (int) round($nxlTokensApplied) ?>;

  var panels = {
    razorpay: '<strong>Razorpay Checkout</strong> <span class="gateway-placeholder-tag">Integration</span><br>Opens secure modal for cards, UPI, net banking &amp; wallets. Connect <code>RAZORPAY_KEY_ID</code> in config to enable live payments.',
    phonepe: '<strong>PhonePe Payment Gateway</strong> <span class="gateway-placeholder-tag">Integration</span><br>Redirect to PhonePe app or hosted checkout. Set <code>PHONEPE_MERCHANT_ID</code> in config.',
    upi: '<strong>UPI Payment</strong><br>Pay to <strong style="color:var(--cyber-green)">' + (upiId || 'UPI ID not configured') + '</strong><br>Reference: <code>' + paymentRef + '</code><div class="upi-qr-mock"><i class="fas fa-qrcode fa-2x mb-1"></i><br>QR placeholder</div>Scan &amp; pay, then enter transaction ID below.',
    card: '<strong>Debit / Credit Card</strong> <span class="gateway-placeholder-tag">Via Razorpay</span><br>Visa, Mastercard, RuPay processed through Razorpay secure checkout once keys are configured.',
    netbanking: '<strong>Net Banking</strong><br>Transfer to CYBEORCH bank account (NEFT/IMPS). Use reference <code>' + paymentRef + '</code> in remarks, then submit UTR below.',
    wallet: '<strong>Wallets</strong> <span class="gateway-placeholder-tag">Via Razorpay</span><br>Paytm, Mobikwik, Amazon Pay &amp; more — available when Razorpay checkout is connected.'
  };

  function formatInr(val) {
    return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  }

  function maxTokensFromWalletBalanceCap() {
    return Math.floor(walletBalance * (walletSpendMaxPercent / 100));
  }

  function calcWalletSplit(tokens) {
    tokens = Math.max(0, Math.floor(Number(tokens) || 0));
    if (!walletGatewayEnabled || feeInr <= 0) {
      return { tokens: 0, discount: 0, remaining: feeInr, balanceAfter: walletBalance, valid: true, error: '' };
    }
    if (tokens > walletBalance) {
      return { tokens: 0, discount: 0, remaining: feeInr, balanceAfter: walletBalance, valid: false, error: 'You cannot use more NxL tokens than your available balance.' };
    }
    var maxFromBalance = maxTokensFromWalletBalanceCap();
    var maxTokens = Math.min(maxFromBalance, Math.floor(walletBalance), Math.floor(feeInr / nxlInrValue));
    if (tokens > maxFromBalance) {
      return { tokens: 0, discount: 0, remaining: feeInr, balanceAfter: walletBalance, valid: false, error: walletSpendLimitMsg };
    }
    tokens = Math.min(tokens, maxTokens);
    var discount = Math.min(tokens * nxlInrValue, feeInr);
    discount = Math.round(discount * 100) / 100;
    var remaining = Math.max(0, Math.round((feeInr - discount) * 100) / 100);
    return { tokens: tokens, discount: discount, remaining: remaining, balanceAfter: Math.max(0, walletBalance - tokens), valid: true, error: '' };
  }

  function buildNxlWalletAddonHtml() {
    if (!nxlWalletSectionEnabled) {
      return '';
    }
    if (!walletLoggedIn) {
      return '<div class="nxl-wallet-addon"><div class="nxl-wallet-addon-title"><i class="fas fa-coins"></i> NxL Token Wallet</div>'
        + '<p class="nxl-signin">Please <a href="' + loginUrl + '">sign in</a> to view your NxL balance and apply tokens toward this payment.</p></div>';
    }
    if (currencyCode !== 'INR') {
      return '<div class="nxl-wallet-addon"><div class="nxl-wallet-addon-title"><i class="fas fa-coins"></i> NxL Token Wallet</div>'
        + '<p class="nxl-insufficient">NxL tokens can only be applied to INR payments.</p></div>';
    }
    if (walletBalance <= 0) {
      return '<div class="nxl-wallet-addon"><div class="nxl-wallet-addon-title"><i class="fas fa-coins"></i> NxL Token Wallet</div>'
        + '<p class="nxl-insufficient"><i class="fas fa-exclamation-circle me-1"></i>Insufficient Wallet Balance.</p></div>';
    }
    var tokensNeeded = Math.ceil(feeInr / nxlInrValue);
    var partialHint = selectedProgramId < 1
      ? '<p class="nxl-insufficient">Select a scheduled webinar or bootcamp from the list to pay fully with NxL tokens.</p>'
      : '';
    return '<div class="nxl-wallet-addon">'
      + '<div class="nxl-wallet-addon-title"><i class="fas fa-coins"></i> NxL Token Wallet</div>'
      + '<div class="nxl-balance-hero"><div class="lbl">Available Wallet Balance</div><div class="val" id="nxlPanelAvailable">' + walletBalance.toLocaleString('en-IN') + ' NXL</div></div>'
      + '<p class="nxl-spend-limit-notice" id="nxlSpendLimitNotice">' + walletSpendLimitMsg + '</p>'
      + '<label for="nxlTokensInput" style="font-size:.82rem;color:var(--cyber-muted)">NxL Tokens to use (optional)</label>'
      + '<input type="number" id="nxlTokensInput" min="0" max="' + walletMaxTokens + '" step="1" value="' + initialNxlTokens + '">'
      + '<p class="nxl-spend-limit-error" id="nxlSpendLimitError" role="alert"></p>'
      + '<button type="button" class="nxl-use-max" id="nxlUseMaxBtn">Use Max (' + walletSpendMaxPercent + '%)</button>'
      + '<div class="nxl-stat-row"><span>Available NXL Tokens</span><strong id="nxlStatAvailable">' + walletBalance.toLocaleString('en-IN') + ' NXL</strong></div>'
      + '<div class="nxl-stat-row"><span>Tokens Being Used</span><strong id="nxlStatUsed">0 NXL</strong></div>'
      + '<div class="nxl-stat-row"><span>Remaining Wallet Balance</span><strong id="nxlStatRemainingBal">' + walletBalance.toLocaleString('en-IN') + ' NXL</strong></div>'
      + '<div class="nxl-stat-row"><span>Wallet Deduction Amount</span><span class="green" id="nxlStatDeduction">-' + formatInr(0) + '</span></div>'
      + '<div class="nxl-stat-row"><span>Remaining Payable Amount</span><strong class="green" id="nxlStatPayable">' + formatInr(feeInr) + '</strong></div>'
      + partialHint
      + '<p style="font-size:.78rem;color:var(--cyber-muted);margin:.65rem 0 0">1 NXL = ' + formatInr(1) + ' discount · ' + tokensNeeded.toLocaleString('en-IN') + ' NXL covers ' + formatInr(feeInr) + '</p>'
      + '</div>';
  }

  function buildWalletPanelHtml() {
    return (panels.wallet || '') + buildNxlWalletAddonHtml();
  }

  function bindNxlWalletPanelEvents() {
    document.getElementById('nxlTokensInput')?.addEventListener('input', function () {
      syncWalletPanel();
      syncGatewayUiPayButton();
    });
    document.getElementById('nxlUseMaxBtn')?.addEventListener('click', function () {
      var inp = document.getElementById('nxlTokensInput');
      if (inp) inp.value = String(walletMaxTokens);
      syncWalletPanel();
      syncGatewayUiPayButton();
    });
    syncWalletPanel();
  }

  function syncWalletPanel() {
    var input = document.getElementById('nxlTokensInput');
    var hidden = document.getElementById('nxlTokensField');
    if (!input) {
      if (hidden) hidden.value = '0';
      return { tokens: 0, discount: 0, remaining: feeInr, balanceAfter: walletBalance, valid: true };
    }
    var split = calcWalletSplit(input.value);
    if (!split.valid) {
      if (hidden) hidden.value = '0';
    } else if (Number(input.value) > split.tokens) {
      input.value = split.tokens;
      split = calcWalletSplit(input.value);
    }
    if (split.valid && hidden) hidden.value = String(split.tokens);
    var errEl = document.getElementById('nxlSpendLimitError');
    if (errEl) {
      errEl.textContent = split.valid ? '' : (split.error || walletSpendLimitMsg);
      errEl.style.display = split.valid ? 'none' : 'block';
    }
    var usedEl = document.getElementById('nxlStatUsed');
    var remBalEl = document.getElementById('nxlStatRemainingBal');
    var dedEl = document.getElementById('nxlStatDeduction');
    var payEl = document.getElementById('nxlStatPayable');
    if (usedEl) usedEl.textContent = split.tokens.toLocaleString('en-IN') + ' NXL';
    if (remBalEl) remBalEl.textContent = split.balanceAfter.toLocaleString('en-IN') + ' NXL';
    if (dedEl) dedEl.textContent = '-' + formatInr(split.discount);
    if (payEl) payEl.textContent = formatInr(split.remaining);
    return split;
  }

  var gmailField = window.CybeorchGmailValidation?.bindGmailEmailField(
    document.getElementById('payEmail'),
    document.getElementById('payEmailError'),
    document.getElementById('paySubmitBtn')
  );

  function selectedGateway() {
    var r = document.querySelector('input[name="payment_gateway"]:checked');
    return r ? r.value : 'razorpay';
  }

  function syncGatewayUi() {
    var gw = selectedGateway();
    document.querySelectorAll('.gateway-tile').forEach(function (tile) {
      tile.classList.toggle('is-active', tile.querySelector('input') === document.querySelector('input[name="payment_gateway"]:checked'));
    });
    var panel = document.getElementById('gatewayPanel');
    if (panel) {
      if (gw === 'wallet') {
        panel.innerHTML = buildWalletPanelHtml();
        bindNxlWalletPanelEvents();
      } else {
        panel.innerHTML = panels[gw] || '';
        var hidden = document.getElementById('nxlTokensField');
        if (hidden) hidden.value = '0';
      }
    }
    var txn = document.getElementById('txnFieldWrap');
    var txnInput = document.getElementById('transactionId');
    var needsTxn = gw === 'upi' || gw === 'netbanking';
    if (txn) txn.hidden = !needsTxn;
    if (txnInput) txnInput.required = needsTxn;
    syncGatewayUiPayButton();
  }

  function syncGatewayUiPayButton() {
    var gw = selectedGateway();
    var btnText = document.getElementById('paySubmitText');
    if (!btnText) return;
    var split = syncWalletPanel();
    if (gw === 'wallet') {
      if (split.tokens > 0) {
        if (split.remaining <= 0.01 && selectedProgramId > 0) {
          btnText.textContent = 'Pay ' + split.tokens.toLocaleString('en-IN') + ' NXL Tokens';
        } else if (split.remaining > 0.01 && checkoutUrlBase) {
          btnText.textContent = 'Pay ' + split.tokens.toLocaleString('en-IN') + ' NXL + ' + formatInr(split.remaining) + ' via Checkout';
        } else {
          btnText.textContent = 'Apply ' + split.tokens.toLocaleString('en-IN') + ' NXL — Submit ' + formatInr(split.remaining);
        }
      } else if (razorpayReady) {
        btnText.textContent = 'Continue to Razorpay Wallets — ' + amount;
      } else {
        btnText.textContent = 'Complete Registration — ' + amount;
      }
      return;
    }
    var payableLabel = split.tokens > 0 && currencyCode === 'INR'
      ? formatInr(split.remaining)
      : amount;
    var needsTxn = gw === 'upi' || gw === 'netbanking';
    if (needsTxn) btnText.textContent = 'I Have Paid — Submit ' + payableLabel;
    else if (gw === 'razorpay' && razorpayReady) btnText.textContent = 'Continue to Razorpay — ' + payableLabel;
    else btnText.textContent = 'Complete Registration — ' + payableLabel;
  }

  document.querySelectorAll('input[name="payment_gateway"]').forEach(function (radio) {
    radio.addEventListener('change', syncGatewayUi);
  });
  syncGatewayUi();

  function selectedPayCurrency() {
    var r = document.querySelector('input[name="payment_currency"]:checked');
    return r ? r.value : 'INR';
  }

  function syncPayCurrencyUi() {
    if (!isDualCurrency) return;
    var code = selectedPayCurrency();
    var hidden = document.getElementById('paymentCurrencyField');
    if (hidden) hidden.value = code;
    document.querySelectorAll('.webinar-currency-option').forEach(function (tile) {
      var input = tile.querySelector('input[name="payment_currency"]');
      tile.classList.toggle('is-selected', input && input.checked);
    });
    var summaryVal = document.getElementById('summaryAmountValue');
    var summaryNote = document.getElementById('summaryAmountNote');
    var amounts = isWebinar ? webinarAmounts : bootcampAmounts;
    if (summaryVal && amounts[code]) summaryVal.textContent = amounts[code];
    if (summaryNote) summaryNote.textContent = code === 'INR' ? 'Inclusive of all taxes' : 'USD calculated from INR fee';
    amount = amounts[code] || amount;
    syncGatewayUi();
  }

  document.querySelectorAll('input[name="payment_currency"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!isDualCurrency) return;
      var code = selectedPayCurrency();
      var params = new URLSearchParams(window.location.search);
      if (params.get('currency') === code) {
        syncPayCurrencyUi();
        return;
      }
      params.set('currency', code);
      window.location.search = params.toString();
    });
  });
  syncPayCurrencyUi();

  document.getElementById('programPicker')?.addEventListener('change', function () {
    var v = this.value || '';
    var cur = isDualCurrency ? '&currency=' + encodeURIComponent(selectedPayCurrency()) : '';
    if (v.indexOf('webinar-id:') === 0) {
      location.href = baseUrl + '?type=webinar&id=' + encodeURIComponent(v.split(':')[1]) + cur;
    } else if (v.indexOf('webinar:') === 0) {
      location.href = baseUrl + '?type=webinar&program=' + encodeURIComponent(v.split(':')[1]) + cur;
    } else if (v.indexOf('bootcamp-id:') === 0) {
      location.href = baseUrl + '?type=bootcamp&id=' + encodeURIComponent(v.split(':')[1]) + cur;
    } else if (v.indexOf('bootcamp:') === 0) {
      location.href = baseUrl + '?type=bootcamp&slug=' + encodeURIComponent(v.split(':')[1]) + cur;
    }
  });

  document.getElementById('securePayForm')?.addEventListener('submit', function (e) {
    if (!gmailField || gmailField.validate()) {
      var gw = selectedGateway();
      var split = syncWalletPanel();
      if (!split.valid) {
        e.preventDefault();
        document.getElementById('nxlTokensInput')?.focus();
        return;
      }
      if (split.tokens > 0 && split.remaining > 0.01 && checkoutUrlBase && selectedProgramId > 0 && (gw === 'wallet' || gw === 'razorpay')) {
        e.preventDefault();
        window.location.href = checkoutUrlBase.split('&nxl_tokens=')[0] + (split.tokens > 0 ? '&nxl_tokens=' + encodeURIComponent(String(split.tokens)) : '');
        return;
      }
      return;
    }
    e.preventDefault();
    document.getElementById('payEmail')?.focus();
  });
})();
</script>
</body>
</html>
