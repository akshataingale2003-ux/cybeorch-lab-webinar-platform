<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/webinar-registration-service.php';
require_once __DIR__ . '/includes/webinar-register-helpers.php';

startSession();

$webinarId = (int) ($_GET['webinar_id'] ?? 0);
$webinar = $webinarId > 0 ? publicFetchWebinarById($webinarId) : null;
if (!$webinar || empty($webinar['is_free'])) {
    redirectWith('webinars.php', 'error', 'Invalid free webinar.');
}

$token = trim((string) ($_GET['token'] ?? ''));
$intake = null;
$showForm = false;

if ($token !== '') {
    $intake = resolveWebinarIntakeAccess($webinarId);
    if (!$intake) {
        redirectWith('webinar-registration-confirm.php?webinar_id=' . $webinarId, 'error', 'Registration session expired. Please submit your details again.');
    }
} else {
    $intake = ensureWebinarIntakeForConfirm($webinarId);
    if ($intake && trim((string) ($intake['confirm_token'] ?? '')) !== '') {
        header('Location: ' . webinarRegistrationConfirmUrl($webinarId, (string) $intake['confirm_token']));
        exit;
    }
    $showForm = true;
}

$scheduledLabel = !empty($webinar['scheduled_at'])
    ? date('d M Y, h:i A', strtotime((string) $webinar['scheduled_at']))
    : 'To be announced';
$csrf = generateCSRF();
$formPrefill = webinarRegisterPrefillFromSession();

if ($showForm) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Register Free — <?= htmlspecialchars((string) $webinar['title']) ?></title>
<?php renderAuthPageHead(); ?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/webinar-registration.css')) ?>">
</head>
<body class="wrc-page wrc-page--register">
<div class="wrc-wrap wrc-wrap--register">
  <div class="wrc-brand wrc-brand--compact"><?= brandMark() ?></div>
  <div class="wrc-card">
    <header class="wrc-header">
      <div class="wrc-header-icon"><i class="fas fa-video"></i></div>
      <div>
        <h1><?= htmlspecialchars((string) $webinar['title']) ?></h1>
        <div class="wrc-header-meta"><i class="far fa-calendar-alt"></i><span><?= htmlspecialchars($scheduledLabel) ?></span></div>
      </div>
    </header>
    <div class="wr-form-head">
      <h2>Free Webinar Registration</h2>
      <p style="color:var(--wrc-muted);font-size:.9rem;margin:0">Enter your details to continue to confirmation.</p>
    </div>
    <?php renderWebinarFeeNotice($webinar); ?>
    <?php renderWebinarRegisterFormFields([
        'webinar_id' => $webinarId,
        'csrf'       => $csrf,
        'prefill'    => $formPrefill,
    ]); ?>
  </div>
  <a href="<?= htmlspecialchars(url('webinars.php')) ?>" class="wrc-back"><i class="fas fa-arrow-left me-1"></i> Back to Webinars</a>
</div>
<?php renderSiteScripts(false); ?>
<script src="<?= htmlspecialchars(url('assets/js/webinar-register-form.js')) ?>"></script>
</body>
</html>
    <?php
    exit;
}

$userName = trim((string) ($intake['full_name'] ?? 'User'));
$userEmail = trim((string) ($intake['email'] ?? ''));
$alreadyRegistered = webinarEmailHasActiveRegistration($webinarId, $userEmail);
$confirmToken = trim((string) ($intake['confirm_token'] ?? ''));
$btnDisabled = $alreadyRegistered || $confirmToken === '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Confirm Registration — CYBEORCH LAB</title>
<?php renderAuthPageHead(); ?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/webinar-registration.css')) ?>">
</head>
<body class="wrc-page wrc-page--confirm">
<div class="wrc-wrap wrc-wrap--confirm">
  <div class="wrc-brand"><?= brandMark() ?></div>
  <div class="wrc-card wrc-card--confirm">
    <header class="wrc-header">
      <div class="wrc-header-icon"><i class="fas fa-video"></i></div>
      <div>
        <h1><?= htmlspecialchars((string) $webinar['title']) ?></h1>
        <div class="wrc-header-meta"><i class="far fa-calendar-alt"></i><span><?= htmlspecialchars($scheduledLabel) ?></span></div>
      </div>
    </header>
    <div class="wrc-body">
      <div id="wrcAlert" class="wrc-alert wrc-alert-info" <?= !$alreadyRegistered ? 'hidden' : '' ?>>
        <i class="fas fa-check-circle"></i><span>You are already registered for this webinar.</span>
      </div>
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="wrc-glass">
            <h2 class="wrc-glass-title">Registration</h2>
            <?php renderWebinarFeeNotice($webinar); ?>
          </div>
          <button type="button" class="wrc-cta" id="wrcConfirmBtn"
            data-api-url="<?= htmlspecialchars(url('api/webinar-registration-confirm.php')) ?>"
            data-csrf="<?= htmlspecialchars($csrf) ?>"
            data-webinar-id="<?= $webinarId ?>"
            data-token="<?= htmlspecialchars($confirmToken) ?>"
            data-disabled="<?= $btnDisabled ? '1' : '0' ?>"
            <?= $btnDisabled ? 'disabled' : '' ?>>
            <span class="wrc-spinner"></span><i class="fas fa-check-circle"></i>
            <span><?= $btnDisabled ? 'Already Registered' : 'Confirm Registration' ?></span>
          </button>
          <div class="wrc-security">
            <span><i class="fas fa-lock"></i> 256-bit SSL</span>
            <span><i class="fas fa-shield-halved"></i> Secure Registration</span>
            <span><i class="fas fa-envelope-circle-check"></i> Confirmation Email</span>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="wrc-glass">
            <h2 class="wrc-glass-title">What You'll Get</h2>
            <ul class="wrc-benefits">
              <li><i class="fas fa-check-circle"></i><span>Live session access on <?= htmlspecialchars($scheduledLabel) ?></span></li>
              <li><i class="fas fa-check-circle"></i><span>Session recording (if available)</span></li>
              <li><i class="fas fa-check-circle"></i><span>Q&A with instructor</span></li>
              <li><i class="fas fa-check-circle"></i><span>Attendance certificate</span></li>
              <li><i class="fas fa-check-circle"></i><span>Free learning resources</span></li>
            </ul>
          </div>
          <div class="wrc-glass">
            <h2 class="wrc-glass-title">Registering As</h2>
            <div class="wrc-user">
              <div class="wrc-avatar"><?= htmlspecialchars(strtoupper(substr($userName, 0, 1))) ?></div>
              <div><p class="wrc-user-name"><?= htmlspecialchars($userName) ?></p><p class="wrc-user-email"><?= htmlspecialchars($userEmail) ?></p></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <a href="<?= htmlspecialchars(url('webinars.php')) ?>" class="wrc-back"><i class="fas fa-arrow-left me-1"></i> Back to Webinars</a>
</div>
<?php renderSiteScripts(false); ?>
<script src="<?= htmlspecialchars(url('assets/js/webinar-registration-confirm.js')) ?>"></script>
</body>
</html>
