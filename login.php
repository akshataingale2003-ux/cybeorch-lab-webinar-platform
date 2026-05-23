<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

startSession();
if (isLoggedIn()) {
    header('Location: ' . url(loginSuccessRedirectPath()));
    exit;
}

$error = '';
$flash = getFlash();
if (isset($_GET['logout'])) {
    $flash = ['type' => 'success', 'message' => 'You have been logged out successfully.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $result = Auth::login($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: ' . url(loginSuccessRedirectPath()));
            exit;
        }
        $error = $result['message'];
    }
}

$refCode = sanitize($_GET['ref'] ?? '');
$csrfToken = generateCSRF();
$openSignupModal = !empty($_GET['signup']) || !empty($_GET['register_required']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Login – CYBEORCH LAB</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.3);color-scheme:dark;}
html,body.auth-page{overflow-x:hidden;overscroll-behavior-x:none;scrollbar-width:none;-ms-overflow-style:none;}
html::-webkit-scrollbar,body.auth-page::-webkit-scrollbar{display:none;width:0;height:0;}
body.auth-page{margin:0;box-sizing:border-box;background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;max-width:100%;width:100%;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.auth-container{width:100%;max-width:450px;min-width:0;position:relative;z-index:1;}
.auth-card,.auth-modal-dialog,.auth-modal-overlay{max-width:100%;}
.auth-logo{font-size:2rem;text-align:center;text-decoration:none;display:block;margin-bottom:0.25rem;}
.auth-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;padding:2.5rem;backdrop-filter:blur(10px);margin-top:1.5rem;}
.auth-title{font-family:'Rajdhani',sans-serif;font-size:1.6rem;font-weight:700;text-align:center;margin-bottom:0.25rem;}
.auth-title .accent{color:var(--cyber-accent);}
.auth-subtitle{color:var(--cyber-muted);font-size:0.88rem;text-align:center;margin-bottom:2rem;}
.form-label{color:var(--cyber-muted);font-size:0.85rem;margin-bottom:0.4rem;display:block;}
.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:8px!important;padding:0.75rem 1rem!important;width:100%;box-sizing:border-box;}
.form-control:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.1)!important;outline:none!important;}
.form-control::placeholder{color:var(--cyber-muted)!important;}
.form-control:disabled{opacity:0.55;cursor:not-allowed;}
.input-group{display:flex;width:100%;max-width:100%;}
.input-group .form-control{border-radius:8px 0 0 8px!important;flex:1;min-width:0;max-width:100%;}
.auth-inline-row{max-width:100%;}
.input-group .btn{background:rgba(255,255,255,0.05);border:1px solid var(--cyber-border);border-left:none;color:var(--cyber-muted);border-radius:0 8px 8px 0;padding:0 0.85rem;cursor:pointer;}
.input-group .btn:hover{color:var(--cyber-accent);}
.btn-submit{background:var(--cyber-accent);color:#050b18;border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;transition:all 0.2s;margin-top:0.5rem;cursor:pointer;}
.btn-submit:hover:not(:disabled){background:var(--cyber-green);transform:translateY(-1px);}
.btn-submit:disabled{opacity:0.5;cursor:not-allowed;transform:none;}
.auth-footer{text-align:center;margin-top:1.5rem;color:var(--cyber-muted);font-size:0.88rem;}
.auth-footer a,.auth-footer button.auth-link-btn{color:var(--cyber-accent);text-decoration:none;font-weight:500;background:none;border:none;padding:0;font:inherit;cursor:pointer;}
.auth-footer a:hover,.auth-footer button.auth-link-btn:hover{color:var(--cyber-green);}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;}
.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:#00ff88;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.alert-info{background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);color:var(--cyber-accent);}
.forgot-link{font-size:0.82rem;color:var(--cyber-accent);text-decoration:none;float:right;}
.mb-3{margin-bottom:1rem;}
.auth-inline-row{display:flex;gap:0.5rem;align-items:stretch;}
.auth-inline-row .form-control{flex:1;min-width:0;}
.btn-otp{flex-shrink:0;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);color:var(--cyber-accent);border-radius:8px;padding:0.65rem 0.85rem;font-weight:600;font-size:0.82rem;cursor:pointer;white-space:nowrap;transition:all 0.2s;}
.btn-otp:hover:not(:disabled){background:rgba(0,212,255,0.2);transform:translateY(-1px);}
.btn-otp:disabled{opacity:0.5;cursor:wait;}
.auth-otp-panel{padding:0.85rem 1rem;border-radius:8px;border:1px solid var(--cyber-border);background:rgba(0,212,255,0.05);margin-bottom:1rem;}
.auth-otp-hint{font-weight:400;font-size:0.78rem;color:var(--cyber-muted);}
.auth-otp-timer{font-size:0.8rem;color:var(--cyber-muted);margin:0.35rem 0 0;}
.auth-otp-actions{margin-top:0.5rem;}
.register-after-otp{opacity:0.45;pointer-events:none;transition:opacity 0.25s ease;}
.register-after-otp.is-ready{opacity:1;pointer-events:auto;}
.auth-card-footer{text-align:center;margin-top:1.25rem;}
.auth-card-footer-note{font-size:0.7rem;color:#5a6d82;margin:0 0 0.5rem;line-height:1.35;}
.auth-card-footer-note:last-child{margin-bottom:0;}
.auth-card-footer-note a,.auth-card-footer-note button.auth-link-btn{color:var(--cyber-accent);text-decoration:none;background:none;border:none;padding:0;font:inherit;cursor:pointer;font-size:inherit;}
html.auth-modal-open,body.auth-modal-open{overflow:hidden!important;height:100%;touch-action:none;}
body.auth-modal-open{position:fixed;width:100%;left:0;right:0;}
.auth-modal-overlay{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:1rem;overflow:hidden;overscroll-behavior:none;background:rgba(2,8,20,0.75);backdrop-filter:blur(14px) saturate(1.15);-webkit-backdrop-filter:blur(14px) saturate(1.15);}
.auth-modal-overlay.is-open{display:flex;animation:authModalFadeIn 0.25s ease;}
.auth-modal-overlay[hidden]{display:none!important;}
.auth-modal-dialog{width:100%;max-width:450px;max-height:min(90dvh,calc(100dvh - 2rem));overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;position:relative;margin:auto;animation:authModalSlideUp 0.3s ease;-webkit-overflow-scrolling:touch;scrollbar-width:none;-ms-overflow-style:none;}
.auth-modal-dialog::-webkit-scrollbar{display:none;width:0;height:0;}
.auth-modal-close{position:absolute;top:0.65rem;right:0.65rem;z-index:2;width:2rem;height:2rem;border:none;border-radius:8px;background:rgba(255,255,255,0.08);color:var(--cyber-muted);font-size:1.35rem;line-height:1;cursor:pointer;transition:color 0.2s,background 0.2s;}
.auth-modal-close:hover{color:var(--cyber-text);background:rgba(255,255,255,0.12);}
.auth-modal-card{margin-top:0;}
@keyframes authModalFadeIn{from{opacity:0}to{opacity:1}}
@keyframes authModalSlideUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@media (max-width:480px){
  .auth-inline-row{flex-direction:column;}
  .btn-otp{width:100%;}
  body.auth-page{align-items:flex-start;padding:1.25rem 1rem;}
}
</style>
</head>
<body class="auth-page">
<div class="auth-container">
  <a href="<?= url('index.php') ?>" class="auth-logo"><?= brandMark() ?></a>
  <div class="auth-card">
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Sign in to your CYBEORCH LAB account</p>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-<?= $flash['type'] === 'success' ? 'check' : 'info' ?>-circle"></i><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@email.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <label class="form-label">Password</label>
          <a href="<?= url('forgot-password.php') ?>" class="forgot-link">Forgot password?</a>
        </div>
        <div class="input-group">
          <input type="password" name="password" id="loginPassword" class="form-control" placeholder="Your password" required>
          <button type="button" class="btn" onclick="togglePass('loginPassword','eyeIcon')"><i class="fas fa-eye" id="eyeIcon"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
    </form>
  </div>
  <div class="auth-footer">
    Don't have an account? <button type="button" class="auth-link-btn" id="openRegModal">Sign Up</button>
  </div>
</div>

<div class="auth-modal-overlay" id="authRegModal" hidden aria-hidden="true">
  <div class="auth-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="authRegModalTitle">
    <button type="button" class="auth-modal-close" id="authRegModalClose" aria-label="Close registration">&times;</button>
    <div class="auth-card auth-modal-card">
      <h1 class="auth-title" id="authRegModalTitle">Welcome to <span class="accent">CYBEORCH</span></h1>
      <p class="auth-subtitle" style="margin-bottom:1.5rem">Create your CYBEORCH LAB account</p>

      <div class="alert alert-info" id="cybeorchRegAjaxAlert" role="status" hidden></div>

      <form id="cybeorchRegForm" novalidate
        data-send-url="<?= htmlspecialchars(url('send_otp.php')) ?>"
        data-verify-url="<?= htmlspecialchars(url('verify_otp.php')) ?>"
        data-register-url="<?= htmlspecialchars(url('register-website.php')) ?>"
        data-success-url="<?= htmlspecialchars(url('dashboard.php?welcome=1&registered=1')) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="text" name="company_url" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0">
        <?php if ($refCode !== ''): ?>
        <input type="hidden" name="referral_code" value="<?= htmlspecialchars($refCode) ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="regFullName">Full Name</label>
          <input type="text" name="full_name" id="regFullName" class="form-control" placeholder="Your full name" required minlength="3" autocomplete="name">
        </div>

        <div class="mb-3">
          <label class="form-label" for="regMobile">Contact Number</label>
          <input type="tel" name="mobile" id="regMobile" class="form-control" placeholder="10-digit mobile number" required autocomplete="tel">
        </div>

        <div class="mb-3">
          <label class="form-label" for="regEmail">Email Address</label>
          <div class="auth-inline-row">
            <input type="email" name="email" id="regEmail" class="form-control" placeholder="you@email.com" required autocomplete="email">
            <button type="button" class="btn-otp" id="cybeorchBtnSendOtp" data-action="send-otp">Send OTP</button>
          </div>
        </div>

        <div class="auth-otp-panel">
          <label class="form-label" for="regOtp">OTP <span class="auth-otp-hint">(Email Verification)</span></label>
          <div class="auth-inline-row">
            <input type="text" name="otp" id="regOtp" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP" autocomplete="one-time-code">
            <button type="button" class="btn-otp" id="cybeorchBtnVerifyOtp">Verify</button>
          </div>
          <p class="auth-otp-timer" id="cybeorchOtpTimer" aria-live="polite"></p>
          <div class="auth-otp-actions">
            <button type="button" class="btn-otp" id="cybeorchBtnResendOtp" disabled>Resend OTP</button>
          </div>
        </div>

        <div id="registerAfterOtp" class="register-after-otp">
          <div class="mb-3">
            <label class="form-label" for="regPassword">Password</label>
            <div class="input-group">
              <input type="password" name="password" id="regPassword" class="form-control" placeholder="Min 8 chars, 1 uppercase, 1 number" autocomplete="new-password" disabled>
              <button type="button" class="btn" onclick="togglePass('regPassword','eyeRegPass')" aria-label="Show password"><i class="fas fa-eye" id="eyeRegPass"></i></button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="regConfirmPassword">Confirm Password</label>
            <div class="input-group">
              <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" placeholder="Repeat password" autocomplete="new-password" disabled>
              <button type="button" class="btn" onclick="togglePass('regConfirmPassword','eyeRegConfirm')" aria-label="Show password"><i class="fas fa-eye" id="eyeRegConfirm"></i></button>
            </div>
          </div>

          <button type="submit" class="btn-submit" id="cybeorchRegSubmit" disabled><i class="fas fa-user-plus me-2"></i>Sign Up</button>
        </div>
      </form>

      <div class="auth-card-footer">
        <p class="auth-card-footer-note">By registering you agree to our <a href="<?= url('terms.php') ?>" target="_blank" rel="noopener">Terms</a> and <a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Privacy Policy</a>.</p>
        <p class="auth-card-footer-note">Already have an account? <button type="button" class="auth-link-btn" id="closeRegModalLink">Sign in</button></p>
      </div>
    </div>
  </div>
</div>

<script>
function togglePass(id, iconId) {
  var inp = document.getElementById(id);
  var icon = document.getElementById(iconId);
  if (!inp || !icon) return;
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
}

(function () {
  var modal = document.getElementById('authRegModal');
  var openBtn = document.getElementById('openRegModal');
  var closeBtn = document.getElementById('authRegModalClose');
  var closeLink = document.getElementById('closeRegModalLink');
  var scrollY = 0;

  function lockPageScroll() {
    scrollY = window.scrollY || window.pageYOffset || 0;
    document.documentElement.classList.add('auth-modal-open');
    document.body.classList.add('auth-modal-open');
    document.body.style.top = '-' + scrollY + 'px';
  }

  function unlockPageScroll() {
    document.documentElement.classList.remove('auth-modal-open');
    document.body.classList.remove('auth-modal-open');
    document.body.style.top = '';
    window.scrollTo(0, scrollY);
  }

  function openModal() {
    if (!modal) return;
    modal.hidden = false;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    lockPageScroll();
    var first = document.getElementById('regFullName');
    if (first) setTimeout(function () { first.focus(); }, 100);
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    unlockPageScroll();
  }

  if (openBtn) {
    openBtn.addEventListener('click', function (e) {
      e.preventDefault();
      openModal();
    });
  }
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (closeLink) closeLink.addEventListener('click', closeModal);
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeModal();
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
  });

  <?php if ($openSignupModal): ?>openModal();<?php endif; ?>
})();
</script>
<script src="<?= url('assets/js/registration-otp.js') ?>"></script>
<?php renderSiteScripts(); ?>
</body>
</html>
