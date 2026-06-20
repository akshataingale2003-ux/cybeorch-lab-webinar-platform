<?php
define('CYBEORCH_ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/setup_admin.php';

startSession();

if (isset($_GET['redirect']) && trim((string) $_GET['redirect']) !== '') {
    storeAdminLoginRedirect((string) $_GET['redirect']);
    $cleanLoginUrl = adminLoginUrl(isset($_GET['logout']) ? ['logout' => '1'] : []);
    header('Location: ' . $cleanLoginUrl);
    exit;
}

if (isAdminLoggedIn()) {
    header('Location: ' . adminUrl('dashboard.php'));
    exit;
}

$setup = ensureDefaultAdminAccount();
$setupNotice = $setup['message'] ?? '';

$error = '';
$flash = getFlash();
$logoutMsg = isset($_GET['logout']) ? 'You have been logged out successfully.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $result = Auth::adminLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: ' . adminUrl(pullAdminLoginRedirect()));
            exit;
        }
        $error = $result['message'];
    }
}
$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Admin Login – CYBEORCH LABS</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.auth-container{width:100%;max-width:420px;position:relative;z-index:1;}
.auth-logo{font-size:1.8rem;text-align:center;display:block;margin-bottom:0.25rem;text-decoration:none;}
.admin-tag{text-align:center;margin-bottom:1.5rem;}
.admin-tag span{background:rgba(255,107,53,0.15);border:1px solid rgba(255,107,53,0.4);color:var(--cyber-orange);font-size:0.72rem;padding:3px 12px;border-radius:4px;letter-spacing:2px;font-weight:600;}
.auth-card{background:var(--cyber-card);border:1px solid rgba(255,107,53,0.25);border-radius:16px;padding:2.5rem;backdrop-filter:blur(10px);}
.auth-title{font-family:'Rajdhani',sans-serif;font-size:1.5rem;font-weight:700;text-align:center;margin-bottom:1rem;}
.auth-subtitle{color:var(--cyber-muted);font-size:0.85rem;text-align:center;margin-bottom:2rem;}
.form-label{color:var(--cyber-muted);font-size:0.82rem;margin-bottom:0.4rem;}
.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:8px!important;padding:0.75rem 1rem!important;}
.form-control:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.1)!important;outline:none!important;}
.form-control::placeholder{color:var(--cyber-muted)!important;}
.input-group .form-control{border-radius:8px 0 0 8px!important;}
.input-group .btn{background:rgba(255,255,255,0.05);border:1px solid var(--cyber-border);border-left:none;color:var(--cyber-muted);border-radius:0 8px 8px 0;}
.btn-submit{background:var(--cyber-orange);color:#fff;border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;cursor:pointer;transition:all 0.2s;margin-top:0.5rem;}
.btn-submit:hover{background:#e05a25;transform:translateY(-1px);}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:#00ff88;}
.security-note{margin-top:1.5rem;text-align:center;font-size:0.75rem;color:var(--cyber-muted);background:rgba(0,0,0,0.2);border:1px solid var(--cyber-border);border-radius:8px;padding:0.75rem;}
</style>
</head>
<body>
<div class="auth-container">
  <a href="<?= url('') ?>" class="auth-logo"><?= brandMark() ?></a>
  <div class="admin-tag"><span>ADMIN ACCESS</span></div>
  <div class="auth-card">
    <h1 class="auth-title"><i class="fas fa-shield-alt me-2" style="color:var(--cyber-orange)"></i>Admin Login</h1>
    <p class="auth-subtitle">Restricted area — authorized personnel only</p>

    <?php if ($setupNotice && empty($error)): ?>
    <div class="alert alert-<?= ($setup['ok'] ?? false) ? 'success' : 'error' ?>"><i class="fas fa-<?= ($setup['ok'] ?? false) ? 'check' : 'exclamation' ?>-circle"></i><?= htmlspecialchars($setupNotice) ?></div>
    <?php endif; ?>
    <?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? '') === 'error' ? 'error' : 'success' ?>"><i class="fas fa-<?= ($flash['type'] ?? '') === 'error' ? 'exclamation-triangle' : 'check-circle' ?>"></i><?= htmlspecialchars((string) $flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($logoutMsg !== ''): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($logoutMsg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="mb-3">
        <label class="form-label">Username or Email</label>
        <input type="text" name="username" class="form-control" placeholder="admin username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <div class="input-group">
          <input type="password" name="password" id="password" class="form-control" required placeholder="Admin password">
          <button type="button" class="btn" onclick="togglePass()"><i class="fas fa-eye" id="eyeIcon"></i></button>
        </div>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt me-2"></i>Access Admin Panel</button>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.82rem">
      <a href="<?= adminUrl('forgot-password.php') ?>" style="color:var(--cyber-orange);text-decoration:none;font-weight:500">Forgot Password?</a>
    </p>
  </div>
  <div style="text-align:center;margin-top:1rem">
    <a href="<?= url('') ?>" style="font-size:0.82rem;color:var(--cyber-muted);text-decoration:none"><i class="fas fa-arrow-left me-1"></i>Back to website</a>
  </div>
</div>
<script>
function togglePass() {
  const inp = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  icon.className = inp.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
</script>
<?php renderSiteScripts(); ?>
</body>
</html>
