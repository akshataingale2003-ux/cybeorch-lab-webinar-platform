<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/password-reset.php';

rejectWhenPublicAuthDisabled();

startSession();

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$flash = getFlash();
$csrfToken = generateCSRF();
$tokenRecord = $token !== '' ? findValidPasswordResetToken($token) : null;
$showForm = $tokenRecord !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif ($token === '') {
        $error = 'Missing reset token.';
    } else {
        $result = completePasswordReset(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirm'] ?? '')
        );

        if ($result['success']) {
            setFlash('success', $result['message']);
            header('Location: ' . url('login.php'));
            exit;
        }

        $error = $result['message'];
        $tokenRecord = findValidPasswordResetToken($token);
        $showForm = $tokenRecord !== null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Reset Password – CYBEORCH LABS</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.3);color-scheme:dark;}
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
.input-group{display:flex;width:100%;}
.input-group .form-control{border-radius:8px 0 0 8px!important;flex:1;min-width:0;}
.input-group .btn{background:rgba(255,255,255,0.05);border:1px solid var(--cyber-border);border-left:none;color:var(--cyber-muted);border-radius:0 8px 8px 0;padding:0 0.85rem;cursor:pointer;}
.btn-submit{background:var(--cyber-accent);color:#050b18;border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;transition:all 0.2s;margin-top:0.5rem;cursor:pointer;}
.btn-submit:hover{background:var(--cyber-green);transform:translateY(-1px);}
.auth-footer{text-align:center;margin-top:1.5rem;color:var(--cyber-muted);font-size:0.88rem;}
.auth-footer a{color:var(--cyber-accent);text-decoration:none;font-weight:500;}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;}
.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:#00ff88;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.alert-info{background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);color:var(--cyber-accent);}
.mb-3{margin-bottom:1rem;}
.hint{font-size:0.78rem;color:var(--cyber-muted);margin-top:0.35rem;}
</style>
</head>
<body class="auth-page">
<div class="auth-container">
  <a href="<?= url('index.php') ?>" class="auth-logo"><?= brandMark() ?></a>

  <div class="auth-page-card">
    <?php if ($showForm): ?>
    <h1 class="auth-title">Create <span class="accent">New Password</span></h1>
    <p class="auth-subtitle">
      <?php if (!empty($tokenRecord['full_name'])): ?>
      Hello <?= htmlspecialchars((string) $tokenRecord['full_name']) ?>, enter a new password for your account.
      <?php else: ?>
      Enter a new password for your account.
      <?php endif; ?>
    </p>

    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="<?= url('reset-password.php') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="mb-3">
        <label class="form-label" for="newPassword">New Password</label>
        <div class="input-group">
          <input type="password" name="password" id="newPassword" class="form-control" placeholder="At least 8 characters" required minlength="8" autofocus>
          <button type="button" class="btn" onclick="togglePass('newPassword','eyeNew')"><i class="fas fa-eye" id="eyeNew"></i></button>
        </div>
        <div class="hint">Use at least 8 characters.</div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="confirmPassword">Confirm Password</label>
        <div class="input-group">
          <input type="password" name="password_confirm" id="confirmPassword" class="form-control" placeholder="Re-enter password" required minlength="8">
          <button type="button" class="btn" onclick="togglePass('confirmPassword','eyeConfirm')"><i class="fas fa-eye" id="eyeConfirm"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-key me-2"></i>Update Password</button>
    </form>

    <?php else: ?>
    <h1 class="auth-title">Reset Link <span class="accent">Invalid</span></h1>
    <div class="alert alert-error">
      <?= htmlspecialchars($error !== '' ? $error : 'This password reset link is invalid, expired, or has already been used.') ?>
    </div>
    <p class="auth-subtitle" style="margin-bottom:1rem;">Request a new reset link to continue.</p>
    <a href="<?= url('forgot-password.php') ?>" class="btn-submit" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box;">Request New Link</a>
    <?php endif; ?>
  </div>

  <div class="auth-footer">
    <a href="<?= url('login.php') ?>">Back to Login</a>
  </div>
</div>
<script>
function togglePass(inputId, iconId) {
  var input = document.getElementById(inputId);
  var icon = document.getElementById(iconId);
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}
</script>
<?php renderSiteScripts(); ?>
</body>
</html>
