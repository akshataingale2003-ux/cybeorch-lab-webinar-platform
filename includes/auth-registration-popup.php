<?php
declare(strict_types=1);

/**
 * Shared dynamic registration popup: sign up (left, 2-column) + sign in (right).
 */
function authRegistrationSuccessUrl(string $redirectPath = ''): string
{
    $path = safeRedirectPath($redirectPath !== '' ? $redirectPath : 'index.php');
    if ($path === 'dashboard.php' || $path === 'index.php') {
        return url('dashboard.php?welcome=1&registered=1');
    }
    $sep = str_contains($path, '?') ? '&' : '?';

    return url($path . $sep . 'registered=1');
}

/** @return array<string, mixed> */
function buildAuthRegistrationPopupConfig(array $overrides = []): array
{
    startSession();

    $redirect = safeRedirectPath((string) ($overrides['login_redirect'] ?? $_GET['redirect'] ?? ''));
    if ($redirect === '') {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
        $redirect = in_array($script, ['login.php', 'signup.php', 'register-website.php'], true) ? 'index.php' : $script;
        if (!empty($_SERVER['QUERY_STRING'])) {
            $redirect .= '?' . $_SERVER['QUERY_STRING'];
        }
    }

    $refCode = sanitize((string) ($overrides['ref_code'] ?? $_GET['ref'] ?? ''));
    $prefillName = (string) ($overrides['prefill_name'] ?? '');
    $prefillEmail = (string) ($overrides['prefill_email'] ?? '');
    $prefillPhone = (string) ($overrides['prefill_phone'] ?? '');

    if ($prefillName === '' && $prefillEmail === '' && isLoggedIn()) {
        $prefillName = (string) ($_SESSION['user_name'] ?? '');
        $prefillEmail = (string) ($_SESSION['user_email'] ?? '');
    }

    $defaults = [
        'mandatory'              => false,
        'open'                   => false,
        'overlay_id'             => 'authRegModal',
        'csrf_token'             => generateCSRF(),
        'success_url'            => authRegistrationSuccessUrl($redirect),
        'ref_code'               => $refCode,
        'prefill_name'           => $prefillName,
        'prefill_email'          => $prefillEmail,
        'prefill_phone'          => $prefillPhone,
        'login_action'           => url('login.php'),
        'login_redirect'         => $redirect,
        'login_error'            => '',
        'login_from_modal'       => false,
        'website_error'          => '',
        'show_register_required' => !empty($_GET['register_required']),
        'banner_tagline'         => '',
        
    ];

    return array_merge($defaults, $overrides);
}

/** @param array<string, mixed> $config */
function authRegistrationPopupClientConfig(array $config): array
{
    return [
        'apiUrl'       => url('api/registration-popup.php'),
        'popupApiUrl'  => url('api/registration-popup.php'),
        'loginUrl'     => url('login.php'),
        'otpScriptUrl' => url('assets/js/registration-otp.js'),
        'enabled'      => isPublicAuthEnabled(),
    ];
}

/** Load OTP + popup controller scripts (order matters). */
function renderAuthRegistrationScriptTags(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<script src="' . htmlspecialchars(urlVersioned('assets/js/registration-otp.js')) . '"></script>' . "\n";
    echo '<script src="' . htmlspecialchars(urlVersioned('assets/js/auth-registration-popup.js')) . '"></script>' . "\n";
}

function renderAuthRegistrationPopupStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<link rel="stylesheet" href="' . htmlspecialchars(urlVersioned('assets/css/auth-registration-popup.css')) . '">' . "\n";
}

/** @param array<string, mixed> $config */
function captureAuthRegistrationPopupHtml(array $config): string
{
    ob_start();
    renderAuthRegistrationPopup($config);

    return (string) ob_get_clean();
}

/**
 * @param array<string, mixed> $config
 */
function renderAuthRegistrationPopup(array $config = []): void
{
    $config = buildAuthRegistrationPopupConfig($config);

    $mandatory = !empty($config['mandatory']);
    $open = $mandatory || !empty($config['open']);
    $overlayId = (string) $config['overlay_id'];
    $csrf = (string) $config['csrf_token'];
    $successUrl = (string) $config['success_url'];
    $refCode = (string) $config['ref_code'];
    $prefillName = (string) $config['prefill_name'];
    $prefillEmail = (string) $config['prefill_email'];
    $prefillPhone = (string) $config['prefill_phone'];
    $loginAction = (string) $config['login_action'];
    $loginRedirect = (string) $config['login_redirect'];
    $loginError = (string) $config['login_error'];
    $loginFromModal = !empty($config['login_from_modal']);
    $websiteError = (string) $config['website_error'];
    $showRegisterRequired = !empty($config['show_register_required']);
    $bannerTagline = (string) $config['banner_tagline'];
    $signupSubtitle = (string) $config['signup_subtitle'];

    $overlayClass = 'auth-reg-modal-overlay auth-reg-fit-window auth-reg-no-scroll';
    if ($mandatory) {
        $overlayClass .= ' auth-reg-modal-overlay--mandatory is-open';
    } elseif ($open) {
        $overlayClass .= ' is-open';
    }

    $hiddenAttr = ($mandatory || $open) ? '' : ' hidden';
    $ariaHidden = ($mandatory || $open) ? 'false' : 'true';
    $clientJson = htmlspecialchars(json_encode(authRegistrationPopupClientConfig($config), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
    ?>
<div class="<?= htmlspecialchars($overlayClass) ?>" id="<?= htmlspecialchars($overlayId) ?>"<?= $hiddenAttr ?> aria-hidden="<?= $ariaHidden ?>" role="dialog" aria-modal="true" aria-labelledby="authRegTitle" data-auth-reg-popup="1">
  <div class="auth-reg-modal-dialog auth-reg-popup-inner">
    <?php if (!$mandatory): ?>
    <button type="button" class="auth-reg-modal-close" id="authRegModalClose" aria-label="Close registration">&times;</button>
    <?php endif; ?>

    <?php if ($bannerTagline !== '' || $websiteError !== '' || $showRegisterRequired): ?>
    <div class="auth-reg-modal-header">
      <?php if ($bannerTagline !== ''): ?>
      <p class="auth-reg-modal-tagline"><?= htmlspecialchars($bannerTagline) ?></p>
      <?php endif; ?>
      <?php if ($websiteError !== ''): ?>
      <div class="alert alert-error" role="alert"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($websiteError) ?></div>
      <?php endif; ?>
      <?php if ($showRegisterRequired): ?>
      <div class="alert alert-info" role="status"><i class="fas fa-lock"></i>Please register to unlock webinars, programs, and your dashboard.</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="auth-reg-card-host">
    <div class="auth-split-card auth-reg-scale-target">
      <div class="auth-split-main">
        <div class="auth-card">
          <h1 class="auth-title" id="authRegTitle">Welcome to <span class="accent">CYBEORCH LABS</span></h1><br>
          <p class="auth-subtitle" data-reg-dynamic-subtitle><?= htmlspecialchars($signupSubtitle) ?></p>

          <div class="alert alert-info" id="cybeorchRegAjaxAlert" role="status" hidden></div>

          <form id="cybeorchRegForm" class="auth-reg-dynamic-form" novalidate
            data-send-url="<?= htmlspecialchars(url('send_otp.php')) ?>"
            data-verify-url="<?= htmlspecialchars(url('verify_otp.php')) ?>"
            data-register-url="<?= htmlspecialchars(url('register-website.php')) ?>"
            data-success-url="<?= htmlspecialchars($successUrl) ?>"
            data-reg-config="<?= $clientJson ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="text" name="company_url" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">

            <div class="auth-reg-form-grid" data-reg-current-step="profile">
              <div class="mb-3 auth-reg-step-panel is-active" data-reg-step="profile">
                <label class="form-label" for="regFullName"></label>
                <input type="text" name="full_name" id="regFullName" class="form-control" placeholder="Your full name" required minlength="2" maxlength="255" autocomplete="name" value="<?= htmlspecialchars($prefillName) ?>">
              </div>

              <div class="mb-3 auth-reg-step-panel is-active" data-reg-step="profile">
                <label class="form-label" for="regMobile"></label>
                <input type="tel" name="mobile" id="regMobile" class="form-control" placeholder="10-digit mobile number" required minlength="8" maxlength="20" autocomplete="tel" value="<?= htmlspecialchars($prefillPhone) ?>">
              </div>

              <div class="mb-3 auth-reg-span-full auth-reg-step-panel is-active" data-reg-step="profile">
                <label class="form-label" for="regEmail"></label>
                <div class="auth-inline-row">
                  <input type="email" name="email" id="regEmail" class="form-control" placeholder="you@email.com" required maxlength="255" autocomplete="email" value="<?= htmlspecialchars($prefillEmail) ?>">
                  <button type="button" class="btn-otp" id="cybeorchBtnSendOtp" data-action="send-otp">Send OTP</button>
                </div>
              </div>

              <div class="auth-otp-panel auth-reg-span-full auth-reg-step-panel is-active" data-reg-step="otp">
                <label class="form-label" for="regOtp">OTP <span class="auth-otp-hint">*</span></label>
                <div class="auth-inline-row">
                  <input type="text" name="otp" id="regOtp" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP" autocomplete="one-time-code" aria-required="true">
                  <button type="button" class="btn-otp" id="cybeorchBtnVerifyOtp">Verify</button>
                </div>
                <p class="auth-otp-timer" id="cybeorchOtpTimer" aria-live="polite"></p>
                <div class="auth-otp-actions">
                  <button type="button" class="btn-otp" id="cybeorchBtnResendOtp" disabled>Resend OTP</button>
                </div>
              </div>

              <div id="registerAfterOtp" class="register-after-otp auth-reg-after-otp-grid auth-reg-step-panel is-active" data-reg-step="password">
                <div class="mb-3">
                  <label class="form-label" for="regPassword">Password *</label>
                  <div class="input-group">
                    <input type="password" name="password" id="regPassword" class="form-control" placeholder="Min 8 chars, 1 uppercase, 1 number" autocomplete="new-password" disabled>
                    <button type="button" class="btn" onclick="toggleRegPass('regPassword','eyeRegPass')" aria-label="Show password"><i class="fas fa-eye" id="eyeRegPass"></i></button>
                  </div>
                </div>
                <div class="mb-3">
                  <label class="form-label" for="regConfirmPassword">Confirm Password *</label>
                  <div class="input-group">
                    <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" placeholder="Repeat password" autocomplete="new-password" disabled>
                    <button type="button" class="btn" onclick="toggleRegPass('regConfirmPassword','eyeRegConfirm')" aria-label="Show password"><i class="fas fa-eye" id="eyeRegConfirm"></i></button>
                  </div>
                </div>
              </div>

              <div class="auth-reg-submit-wrap auth-reg-span-full auth-reg-step-panel is-active" data-reg-step="password">
                <button type="submit" class="btn-submit" id="cybeorchRegSubmit" disabled aria-disabled="true"><i class="fas fa-user-plus me-2"></i>Sign Up</button><br><br>
              </div>
            </div>
          </form>

          <div class="auth-card-footer">
            <p class="auth-card-footer-note">By registering you agree to our <a href="<?= url('terms.php') ?>" target="_blank" rel="noopener">Terms</a> and <a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Privacy Policy</a>.</p>
          </div>
        </div>
      </div>

      <aside class="auth-split-side" aria-labelledby="authExistingMemberLabel">
        <h3 class="auth-title auth-title--signin" id="authSignInTitle"><span class="accent">Sign In</span></h3><br>

        <?php if ($loginError !== '' && $loginFromModal): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST"<?= $loginAction !== '' ? ' action="' . htmlspecialchars($loginAction) . '"' : '' ?>>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="redirect" value="<?= htmlspecialchars($loginRedirect) ?>">
          <input type="hidden" name="login_from" value="modal">
          <div class="mb-3">
            <label class="form-label" for="loginEmail">Email Address</label>
            <input type="email" name="email" id="loginEmail" class="form-control" placeholder="you@email.com" required autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label" for="loginPassword">Password</label>
              <a href="<?= url('forgot-password.php') ?>" class="forgot-link">Forgot?</a>
            </div>
            <div class="input-group">
              <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Your password" required autocomplete="current-password">
              <button type="button" class="btn" onclick="toggleRegPass('loginPassword','eyeIcon')" aria-label="Show password"><i class="fas fa-eye" id="eyeIcon"></i></button>
            </div>
          </div>
          <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
        </form>
      </aside>
    </div>
    </div>
  </div>
</div>
    <?php
}

/** Site-wide dynamic popup loader (skip when guest must register on index). */
function renderPublicAuthRegistrationAssets(bool $skipWhenMandatoryOnPage = false): void
{
    if (!isPublicAuthEnabled() || isLoggedIn()) {
        return;
    }
    if ($skipWhenMandatoryOnPage || !empty($GLOBALS['cybeorch_skip_public_reg_assets'])) {
        return;
    }

    renderAuthRegistrationPopupStyles();

    $boot = authRegistrationPopupClientConfig(buildAuthRegistrationPopupConfig());
    echo '<div id="authRegModalMount"></div>' . "\n";
    echo '<script>window.CYBEORCH_REG_POPUP=' . json_encode($boot, JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
    renderAuthRegistrationScriptTags();
}

function renderAuthRegistrationPopupScripts(bool $openOnLoad = false): void
{
    $boot = authRegistrationPopupClientConfig(buildAuthRegistrationPopupConfig());
    ?>
<script>window.CYBEORCH_REG_POPUP=<?= json_encode($boot, JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
function toggleRegPass(id, iconId) {
  var inp = document.getElementById(id);
  var icon = document.getElementById(iconId);
  if (!inp || !icon) return;
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
}
if (typeof togglePass === 'undefined') { window.togglePass = toggleRegPass; }
function cybeorchBootRegistrationPopup(openOnLoad) {
  var tries = 0;
  function run() {
    tries++;
    if (window.initCybeorchRegistrationOtp && document.getElementById('cybeorchRegForm')) {
      window.initCybeorchRegistrationOtp();
      if (typeof window.fitAuthRegToViewport === 'function') {
        window.fitAuthRegToViewport();
      }
    }
    if (openOnLoad && window.CybeorchAuthReg) {
      window.CybeorchAuthReg.open({ focus: true });
      return;
    }
    if (tries < 80) setTimeout(run, 50);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
}
</script>
<?php renderAuthRegistrationScriptTags(); ?>
    <?php if ($openOnLoad): ?>
<script>cybeorchBootRegistrationPopup(true);</script>
    <?php else: ?>
<script>cybeorchBootRegistrationPopup(false);</script>
    <?php endif;
}
