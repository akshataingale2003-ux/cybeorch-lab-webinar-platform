<?php
define('CYBEORCH_ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/admin-password-reset.php';

startSession();

if (isAdminLoggedIn()) {
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$csrfToken = generateCSRF();
$tokenRecord = $token !== '' ? findValidAdminPasswordResetToken($token) : null;
$showForm = $tokenRecord !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif ($token === '') {
        $error = 'Missing reset token.';
    } else {
        $result = completeAdminPasswordReset(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirm'] ?? '')
        );

        if ($result['success']) {
            setFlash('success', $result['message']);
            header('Location: ' . adminLoginUrl());
            exit;
        }

        $error = $result['message'];
        $tokenRecord = findValidAdminPasswordResetToken($token);
        $showForm = $tokenRecord !== null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Reset Password – Admin – CYBEORCH LABS</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-orange:#ff6b35;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
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
.input-group{display:flex;width:100%;}
.input-group .form-control{border-radius:8px 0 0 8px!important;flex:1;min-width:0;}
.input-group .btn{background:rgba(255,255,255,0.05);border:1px solid var(--cyber-border);border-left:none;color:var(--cyber-muted);border-radius:0 8px 8px 0;padding:0 0.85rem;cursor:pointer;}
.btn-submit{background:var(--cyber-orange);color:#fff;border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;cursor:pointer;transition:all 0.2s;margin-top:0.5rem;text-decoration:none;display:block;text-align:center;box-sizing:border-box;}
.btn-submit:hover{background:#e05a25;transform:translateY(-1px);}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.auth-footer{text-align:center;margin-top:1.25rem;font-size:0.82rem;}
.auth-footer a{color:var(--cyber-muted);text-decoration:none;}
.auth-footer a:hover{color:var(--cyber-orange);}
.mb-3{margin-bottom:1rem;}
.hint{font-size:0.75rem;color:var(--cyber-muted);margin-top:0.35rem;line-height:1.45;}
</style>
</head>
<body>
<div class="auth-container">
  <a href="<?= url('') ?>" class="auth-logo"><?= brandMark() ?></a>
  <div class="admin-tag"><span>ADMIN ACCESS</span></div>
  <div class="auth-card">
    <?php if ($showForm): ?>
    <h1 class="auth-title"><i class="fas fa-lock me-2" style="color:var(--cyber-orange)"></i>Reset Password</h1>
    <p class="auth-subtitle">
      <?php if (!empty($tokenRecord['full_name'])): ?>
      Hello <?= htmlspecialchars((string) $tokenRecord['full_name']) ?>, set a new admin password.
      <?php else: ?>
      Set a new password for your admin account.
      <?php endif; ?>
    </p>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= adminUrl('reset-password.php') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="mb-3">
        <label class="form-label" for="newPassword">New Password</label>
        <div class="input-group">
          <input type="password" name="password" id="newPassword" class="form-control" placeholder="New password" required minlength="8" autofocus>
          <button type="button" class="btn" onclick="togglePass('newPassword','eyeNew')"><i class="fas fa-eye" id="eyeNew"></i></button>
        </div>
        <div class="hint">At least 8 characters with uppercase, lowercase, and a number.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="confirmPassword">Confirm Password</label>
        <div class="input-group">
          <input type="password" name="password_confirm" id="confirmPassword" class="form-control" placeholder="Confirm password" required minlength="8">
          <button type="button" class="btn" onclick="togglePass('confirmPassword','eyeConfirm')"><i class="fas fa-eye" id="eyeConfirm"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-check me-2"></i>Update Password</button>
    </form>
    <?php else: ?>
    <h1 class="auth-title">Link Expired</h1>
    <div class="alert alert-error">
      <?= htmlspecialchars($error !== '' ? $error : 'This password reset link is invalid, expired, or has already been used.') ?>
    </div>
    <p class="auth-subtitle" style="margin-bottom:1rem;">Request a new reset link to continue.</p>
    <a href="<?= adminUrl('forgot-password.php') ?>" class="btn-submit">Request New Link</a>
    <?php endif; ?>

    <div class="auth-footer">
      <a href="<?= adminLoginUrl() ?>"><i class="fas fa-arrow-left me-1"></i>Back to Admin Login</a>
    </div>
  </div>
</div>
<script>
function togglePass(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if (!input || !icon) return;
  input.type = input.type === 'password' ? 'text' : 'password';
  icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
<?php renderSiteScripts(); ?>
</body>
</html>
