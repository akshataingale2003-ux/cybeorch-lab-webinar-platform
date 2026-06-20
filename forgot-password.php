<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/password-reset.php';

rejectWhenPublicAuthDisabled();

startSession();
if (isLoggedIn()) {
    header('Location: ' . url(loginSuccessRedirectPath()));
    exit;
}

$flash = getFlash();
$csrfToken = generateCSRF();
$devResetLink = $_SESSION['password_reset_dev_link'] ?? '';
unset($_SESSION['password_reset_dev_link']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Forgot Password – CYBEORCH LABS</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.3);color-scheme:dark;}
body.auth-page{margin:0;box-sizing:border-box;background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.auth-container{width:100%;max-width:450px;position:relative;z-index:1;}
.auth-logo{font-size:2rem;text-align:center;text-decoration:none;display:block;margin-bottom:0.25rem;}
.auth-page-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;padding:2.5rem;backdrop-filter:blur(10px);margin-top:1.5rem;}
.auth-title{font-family:'Rajdhani',sans-serif;font-size:1.6rem;font-weight:700;text-align:center;margin-bottom:0.25rem;}
.auth-title .accent{color:var(--cyber-accent);}
.auth-subtitle{color:var(--cyber-muted);font-size:0.88rem;text-align:center;margin-bottom:2rem;line-height:1.5;}
.form-label{color:var(--cyber-muted);font-size:0.85rem;margin-bottom:0.4rem;display:block;}
.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:8px!important;padding:0.75rem 1rem!important;width:100%;box-sizing:border-box;}
.form-control:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.1)!important;outline:none!important;}
.btn-submit{background:var(--cyber-accent);color:#050b18;border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;transition:all 0.2s;margin-top:0.5rem;cursor:pointer;}
.btn-submit:hover{background:var(--cyber-green);transform:translateY(-1px);}
.auth-footer{text-align:center;margin-top:1.5rem;color:var(--cyber-muted);font-size:0.88rem;}
.auth-footer a{color:var(--cyber-accent);text-decoration:none;font-weight:500;}
.auth-footer a:hover{color:var(--cyber-green);}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;}
.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:#00ff88;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.alert-info{background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);color:var(--cyber-accent);}
.dev-reset-link{margin-top:0.75rem;padding:0.75rem;background:rgba(0,212,255,0.08);border:1px dashed var(--cyber-border);border-radius:8px;font-size:0.78rem;word-break:break-all;}
.dev-reset-link a{color:var(--cyber-accent);}
.mb-3{margin-bottom:1rem;}
</style>
</head>
<body class="auth-page">
<div class="auth-container">
  <a href="<?= url('index.php') ?>" class="auth-logo"><?= brandMark() ?></a>

  <div class="auth-page-card">
    <h1 class="auth-title">Forgot <span class="accent">Password</span></h1>
    <p class="auth-subtitle">Enter your registered email address and we will send you a secure link to reset your password.</p>

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php if ($devResetLink !== ''): ?>
    <div class="dev-reset-link">
      <strong>Local dev reset link:</strong><br>
      <a href="<?= htmlspecialchars($devResetLink) ?>"><?= htmlspecialchars($devResetLink) ?></a>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <form method="POST" action="<?= url('send-reset-email.php') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="text" name="company_url" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;">
      <div class="mb-3">
        <label class="form-label" for="resetEmail">Email Address</label>
        <input type="email" name="email" id="resetEmail" class="form-control" placeholder="you@email.com" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-paper-plane me-2"></i>Send Reset Link</button>
    </form>
  </div>

  <div class="auth-footer">
    Remember your password? <a href="<?= url('login.php') ?>">Back to Login</a>
  </div>
</div>
<?php renderSiteScripts(); ?>
</body>
</html>
