<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

startSession();
requireLogin();

$userId  = $_SESSION['user_id'];
$type    = sanitize($_GET['type'] ?? 'webinar');
$payRef  = sanitize($_GET['ref'] ?? '');

$payment = db()->fetchOne(
    'SELECT p.*, u.full_name, u.email FROM payments p JOIN users u ON u.id=p.user_id WHERE p.razorpay_payment_id = ? AND p.user_id = ?',
    [$payRef, $userId]
);

$nxlEarned     = $type === 'bootcamp' ? NXL_BOOTCAMP_REWARD : NXL_WEBINAR_REWARD;
$walletBalance = getWalletBalance($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Payment Successful – CYBEORCH LAB</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.success-card{background:var(--cyber-card);border:1px solid rgba(0,255,136,0.3);border-radius:16px;padding:3rem 2rem;text-align:center;max-width:520px;width:100%;position:relative;z-index:1;}
.success-icon{width:90px;height:90px;border-radius:50%;background:rgba(0,255,136,0.12);border:2px solid rgba(0,255,136,0.4);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;animation:pulse-green 2s infinite;}
@keyframes pulse-green{0%,100%{box-shadow:0 0 0 0 rgba(0,255,136,0.3);}50%{box-shadow:0 0 0 20px rgba(0,255,136,0);}}
.success-icon i{font-size:2.5rem;color:var(--cyber-green);}
.success-title{font-family:'Rajdhani',sans-serif;font-size:2rem;font-weight:700;color:var(--cyber-green);margin-bottom:0.5rem;}
.success-subtitle{color:var(--cyber-muted);font-size:0.95rem;line-height:1.6;margin-bottom:2rem;}
.details-box{background:rgba(0,0,0,0.3);border:1px solid var(--cyber-border);border-radius:10px;padding:1.25rem;margin-bottom:1.5rem;text-align:left;}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:0.4rem 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:0.85rem;}
.detail-row:last-child{border-bottom:none;}
.detail-label{color:var(--cyber-muted);}
.detail-value{font-weight:500;color:var(--cyber-text);}
.nxl-banner{background:linear-gradient(135deg,rgba(0,255,136,0.1),rgba(0,212,255,0.08));border:1px solid rgba(0,255,136,0.25);border-radius:10px;padding:1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:0.75rem;}
.btn-goto{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.85rem 2rem;border-radius:8px;font-weight:700;font-size:0.95rem;text-decoration:none;display:inline-block;transition:all 0.2s;margin:0.25rem;}
.btn-goto:hover{background:var(--cyber-green);color:var(--cyber-dark);transform:translateY(-2px);}
.btn-outline{border:1px solid var(--cyber-border);color:var(--cyber-text);background:transparent;padding:0.85rem 2rem;border-radius:8px;font-size:0.95rem;text-decoration:none;display:inline-block;transition:all 0.2s;margin:0.25rem;}
.btn-outline:hover{border-color:var(--cyber-accent);color:var(--cyber-accent);}
</style>
</head>
<body>

<div style="position:relative;z-index:1;width:100%;max-width:520px;">
  <!-- Confetti dots -->
  <div style="position:fixed;inset:0;pointer-events:none;overflow:hidden;" id="confetti"></div>

  <div class="success-card">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <h1 class="success-title">Payment Successful!</h1>
    <p class="success-subtitle">
      Your <?= $type === 'bootcamp' ? 'bootcamp enrollment' : 'webinar registration' ?> is confirmed.
      A confirmation has been sent to your email.
    </p>

    <?php if ($payment): ?>
    <div class="details-box">
      <div class="detail-row">
        <span class="detail-label">Transaction ID</span>
        <span class="detail-value" style="font-size:0.78rem;font-family:monospace"><?= htmlspecialchars($payment['razorpay_payment_id']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Invoice No.</span>
        <span class="detail-value"><?= htmlspecialchars($payment['invoice_no'] ?? '—') ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Amount Paid</span>
        <span class="detail-value" style="color:var(--cyber-green);font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:700">₹<?= number_format($payment['amount']) ?></span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Status</span>
        <span class="detail-value" style="color:var(--cyber-green)"><i class="fas fa-check-circle me-1"></i>Paid</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">Date</span>
        <span class="detail-value"><?= date('d M Y, h:i A', strtotime($payment['paid_at'] ?? 'now')) ?></span>
      </div>
    </div>
    <?php endif; ?>

    <div class="nxl-banner">
      <div style="font-size:2rem">🪙</div>
      <div style="text-align:left">
        <div style="font-size:0.85rem;font-weight:600;color:var(--cyber-green)">
          +<?= $nxlEarned ?> NxL Tokens <?= $type === 'bootcamp' ? 'Credited!' : 'on Attendance!' ?>
        </div>
        <div style="font-size:0.78rem;color:var(--cyber-muted)">
          <?= $type === 'bootcamp' 
            ? "Tokens added to your wallet. New balance: {$walletBalance} NxL"
            : "Attend the webinar to earn your NxL tokens reward." ?>
        </div>
      </div>
    </div>

    <div>
      <a href="<?= url('my-registrations.php') ?>" class="btn-goto"><i class="fas fa-ticket-alt me-1"></i>My Registrations</a>
      <a href="<?= url(($type === 'bootcamp' ? 'bootcamps' : 'webinars') . '.php') ?>" class="btn-outline">Browse More</a>
    </div>

    <div style="margin-top:1.5rem;font-size:0.78rem;color:var(--cyber-muted)">
      Need help? Email us at <a href="mailto:support@CYBEORCH.com" style="color:var(--cyber-accent)">support@CYBEORCH.com</a>
    </div>
  </div>
</div>

<script>
// Simple confetti animation
const container = document.getElementById('confetti');
const colors = ['#00d4ff','#00ff88','#ff6b35','#a855f7','#ffffff'];
for (let i = 0; i < 60; i++) {
  const dot = document.createElement('div');
  const size = Math.random() * 8 + 4;
  dot.style.cssText = `
    position:absolute;
    width:${size}px;height:${size}px;
    background:${colors[Math.floor(Math.random()*colors.length)]};
    border-radius:${Math.random()>0.5?'50%':'2px'};
    left:${Math.random()*100}%;
    top:-20px;
    opacity:${Math.random()*0.8+0.2};
    animation: fall ${Math.random()*3+2}s linear ${Math.random()*2}s forwards;
  `;
  container.appendChild(dot);
}
const style = document.createElement('style');
style.textContent = `@keyframes fall { to { transform: translateY(110vh) rotate(720deg); opacity: 0; } }`;
document.head.appendChild(style);
</script>
<?php renderSiteScripts(); ?>
</body>
</html>
