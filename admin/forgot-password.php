<?php
define('CYBEORCH_ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/admin-password-reset.php';
require_once __DIR__ . '/../includes/setup_admin.php';

startSession();
ensureAdminRegisteredEmail();

if (isAdminLoggedIn()) {
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$flash = getFlash();
$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Forgot Password – Admin – CYBEORCH LABS</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-accent:#00d4ff;--cyber-orange:#ff6b35;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.auth-container{width:100%;max-width:420px;position:relative;z-index:1;}
.auth-logo{font-size:1.8rem;text-align:center;display:block;margin-bottom:0.25rem;text-decoration:none;}
.admin-tag{text-align:center;margin-bottom:1.5rem;}
.admin-tag span{background:rgba(255,107,53,0.15);border:1px solid rgba(255,107,53,0.4);color:var(--cyber-orange);font-size:0.72rem;padding:3px 12px;border-radius:4px;letter-spacing:2px;font-weight:600;}
.auth-card{background:var(--cyber-card);border:1px solid rgba(255,107,53,0.25);border-radius:16px;padding:2.5rem;backdrop-filter:blur(10px);}
.auth-title{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;text-align:center;margin-bottom:1rem;}
.auth-subtitle{color:var(--cyber-muted);font-size:0.85rem;text-align:center;margin-bottom:2rem;line-height:1.6;}
.form-label{color:var(--cyber-muted);font-size:0.82rem;margin-bottom:0.4rem;display:block;}
.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:8px!important;padding:0.75rem 1rem!important;width:100%;}
.form-control:focus{border-color:var(--cyber-orange)!important;box-shadow:0 0 0 3px rgba(255,107,53,0.15)!important;outline:none!important;}
.btn-submit{background:var(--cyber-orange);color:#fff;border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;cursor:pointer;transition:all 0.2s;margin-top:0.5rem;}
.btn-submit:hover{background:#e05a25;transform:translateY(-1px);}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;}
.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:#00ff88;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.auth-footer{text-align:center;margin-top:1.25rem;font-size:0.82rem;}
.auth-footer a{color:var(--cyber-muted);text-decoration:none;}
.auth-footer a:hover{color:var(--cyber-orange);}
.mb-3{margin-bottom:1rem;}
</style>
</head>
<body>
<div class="auth-container">
  <a href="<?= url('') ?>" class="auth-logo"><?= brandMark() ?></a>
  <div class="admin-tag"><span>ADMIN ACCESS</span></div>
  <div class="auth-card">
    <h1 class="auth-title"><i class="fas fa-key me-2" style="color:var(--cyber-orange)"></i>Forgot Password</h1>
    <p class="auth-subtitle">We will send a secure password reset link to <strong><?= htmlspecialchars(cybeorchAdminRegisteredEmail()) ?></strong> using PHPMailer SMTP.</p>

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars((string) $flash['message']) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= adminUrl('send-reset-email.php') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars(cybeorchAdminRegisteredEmail()) ?>">
      <input type="text" name="company_url" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;">
      <button type="submit" class="btn-submit"><i class="fas fa-paper-plane me-2"></i>Send Reset Link to <?= htmlspecialchars(cybeorchAdminRegisteredEmail()) ?></button>
    </form>

    <div class="auth-footer">
      <a href="<?= adminLoginUrl() ?>"><i class="fas fa-arrow-left me-1"></i>Back to Admin Login</a>
    </div>
  </div>
</div>
<?php renderSiteScripts(); ?>
</body>
</html>
