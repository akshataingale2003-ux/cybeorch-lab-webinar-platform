<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

startSession();
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Application Submitted – CYBEORCH LAB</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.3);}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.success-card{max-width:520px;width:100%;background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;padding:2.5rem;text-align:center;position:relative;z-index:1;}
.success-icon{width:72px;height:72px;border-radius:50%;background:rgba(0,255,136,0.12);border:2px solid var(--cyber-green);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:var(--cyber-green);}
.success-card h1{font-family:'Rajdhani',sans-serif;font-size:1.75rem;font-weight:700;margin-bottom:0.75rem;}
.success-card p{color:var(--cyber-muted);line-height:1.7;margin-bottom:1.5rem;font-size:0.95rem;}
.btn-primary-cyber{display:inline-block;background:var(--cyber-accent);color:#050b18;padding:0.75rem 1.5rem;border-radius:8px;font-weight:700;margin:0.35rem;text-decoration:none;transition:all .2s;}
.btn-primary-cyber:hover{background:var(--cyber-green);color:#050b18;}
.btn-outline-cyber{display:inline-block;border:1px solid var(--cyber-border);color:var(--cyber-accent);padding:0.75rem 1.5rem;border-radius:8px;font-weight:600;margin:0.35rem;text-decoration:none;transition:all .2s;}
.btn-outline-cyber:hover{border-color:var(--cyber-accent);background:rgba(0,212,255,0.08);}
</style>
</head>
<body>
<div class="success-card">
  <div class="success-icon"><i class="fas fa-check"></i></div>
  <h1>Application Submitted!</h1>
  <p>Thank you for registering as a freelancer with CYBEORCH LAB. Our team will review your profile and contact you when suitable hands-on projects are available.</p>
  <div>
    <a href="<?= url('dashboard.php') ?>" class="btn-primary-cyber"><i class="fas fa-th-large me-2"></i>Go to Dashboard</a>
    <a href="<?= url('') ?>" class="btn-outline-cyber"><i class="fas fa-home me-2"></i>Back to Home</a>
  </div>
</div>
<?php renderSiteScripts(); ?>
</body>
</html>
