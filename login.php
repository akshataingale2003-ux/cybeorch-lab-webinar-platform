<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth-registration-popup.php';

rejectWhenPublicAuthDisabled();

startSession();
if (isLoggedIn()) {
    $afterLogin = safeRedirectPath((string) ($_GET['redirect'] ?? loginSuccessRedirectPath()));
    header('Location: ' . url($afterLogin));
    exit;
}

$error = '';
$flash = getFlash();
if (isset($_GET['logout'])) {
    $flash = ['type' => 'success', 'message' => 'You have been logged out successfully.'];
}

$redirectAfterLogin = safeRedirectPath((string) ($_GET['redirect'] ?? loginSuccessRedirectPath()));
$signupSuccessUrl = signupSuccessRedirectPath();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $result = Auth::login($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            $dest = safeRedirectPath((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? loginSuccessRedirectPath()));
            header('Location: ' . url($dest));
            exit;
        }
        $error = $result['message'];
    }
}

$refCode = sanitize($_GET['ref'] ?? '');
$csrfToken = generateCSRF();
$openRegModal = !empty($_GET['signup']) || !empty($_GET['register_required'])
    || ($error !== '' && (($_POST['login_from'] ?? '') === 'modal'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Login – CYBEORCH LABS</title>
<?php renderAuthPageHead(); ?>
<?php renderAuthRegistrationPopupStyles(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.3);color-scheme:dark;}
html,body.auth-page{overflow-x:hidden;overscroll-behavior-x:none;scrollbar-width:none;-ms-overflow-style:none;}
html::-webkit-scrollbar,body.auth-page::-webkit-scrollbar{display:none;width:0;height:0;}
body.auth-page{margin:0;box-sizing:border-box;background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;max-width:100%;width:100%;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.auth-container{width:100%;max-width:450px;min-width:0;position:relative;z-index:1;}
.auth-logo{font-size:2rem;text-align:center;text-decoration:none;display:block;margin-bottom:0.25rem;}
.auth-page-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;padding:2.5rem;backdrop-filter:blur(10px);margin-top:1.5rem;}
.auth-page-card .auth-title{text-align:center;}
.auth-page-card .auth-subtitle{text-align:center;margin-bottom:2rem;}
.auth-title{font-family:'Rajdhani',sans-serif;font-size:1.6rem;font-weight:700;text-align:center;margin-bottom:0.25rem;}
.auth-title .accent{color:var(--cyber-accent);}
.auth-subtitle{color:var(--cyber-muted);font-size:0.88rem;text-align:center;margin-bottom:2rem;line-height:1.4;}
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
@media (max-width:480px){body.auth-page{align-items:flex-start;padding:1.25rem 1rem;}}
</style>
</head>
<body class="auth-page">
<div class="auth-container">
  <a href="<?= url('index.php') ?>" class="auth-logo"><?= brandMark() ?></a>

  <div class="auth-page-card">
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Sign in to your CYBEORCH LABS account</p>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-<?= $flash['type'] === 'success' ? 'check' : 'info' ?>-circle"></i><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($error && (($_POST['login_from'] ?? '') !== 'modal')): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars((string) ($_GET['redirect'] ?? '')) ?>">
      <div class="mb-3">
        <label class="form-label" for="loginEmailMain">Email Address</label>
        <input type="email" name="email" id="loginEmailMain" class="form-control" placeholder="you@email.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
          <label class="form-label" for="loginPasswordMain">Password</label>
          <a href="<?= url('forgot-password.php') ?>" class="forgot-link">Forgot password?</a>
        </div>
        <div class="input-group">
          <input type="password" name="password" id="loginPasswordMain" class="form-control" placeholder="Your password" required>
          <button type="button" class="btn" onclick="togglePass('loginPasswordMain','eyeIconMain')"><i class="fas fa-eye" id="eyeIconMain"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt me-2"></i>Sign In</button>
    </form>
  </div>
  <div class="auth-footer">
    Don't have an account? <button type="button" class="auth-link-btn" id="openRegModal">Sign Up</button>
  </div>
</div>

<?php
renderAuthRegistrationPopup(buildAuthRegistrationPopupConfig([
    'open'             => $openRegModal,
    'csrf_token'       => $csrfToken,
    'success_url'      => url($signupSuccessUrl),
    'ref_code'         => $refCode,
    'login_redirect'   => (string) ($_GET['redirect'] ?? ''),
    'login_error'      => $error,
    'login_from_modal' => (($_POST['login_from'] ?? '') === 'modal'),
]));
renderAuthRegistrationPopupScripts($openRegModal);
?>

<?php renderSiteScripts(); ?>
</body>
</html>
