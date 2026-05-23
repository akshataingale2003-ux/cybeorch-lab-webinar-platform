<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

startSession();
requireLogin();

$userId = (int) $_SESSION['user_id'];
$type   = $_GET['type'] ?? 'webinar';
$id     = (int) ($_GET['id'] ?? 0);

if (!in_array($type, ['webinar', 'bootcamp'], true) || $id < 1) {
    header('Location: ' . url('my-registrations.php'));
    exit;
}

$item = null;
$reg  = null;

if ($type === 'webinar') {
    $item = db()->fetchOne('SELECT * FROM webinars WHERE id = ?', [$id]);
    $reg  = db()->fetchOne(
        'SELECT * FROM webinar_registrations WHERE user_id = ? AND webinar_id = ?',
        [$userId, $id]
    );
} else {
    $item = db()->fetchOne('SELECT * FROM bootcamps WHERE id = ?', [$id]);
    $reg  = db()->fetchOne(
        'SELECT * FROM bootcamp_enrollments WHERE user_id = ? AND bootcamp_id = ?',
        [$userId, $id]
    );
}

if (!$item || !$reg) {
    redirectWith('my-registrations.php', 'info', 'Registration not found. Please try registering again.');
}

$isFree = ($reg['payment_status'] ?? '') === 'free';
$pageTitle = $isFree ? 'Registration Confirmed' : 'Registration Confirmed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LAB</title>
<?php renderAuthPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem 1rem;}
.success-card{background:var(--cyber-card);border:1px solid rgba(0,255,136,0.3);border-radius:16px;padding:2.5rem 2rem;text-align:center;max-width:520px;width:100%;position:relative;z-index:1;}
.success-icon{width:80px;height:80px;border-radius:50%;background:rgba(0,255,136,0.12);border:2px solid rgba(0,255,136,0.4);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;}
.success-icon i{font-size:2.2rem;color:var(--cyber-green);}
.success-title{font-family:'Rajdhani',sans-serif;font-size:1.85rem;font-weight:700;color:var(--cyber-green);margin-bottom:.5rem;}
.success-subtitle{color:var(--cyber-muted);font-size:.92rem;line-height:1.6;margin-bottom:1.5rem;}
.details-box{background:rgba(0,0,0,0.3);border:1px solid var(--cyber-border);border-radius:10px;padding:1.25rem;margin-bottom:1.5rem;text-align:left;font-size:.88rem;}
.detail-row{display:flex;justify-content:space-between;gap:1rem;padding:.35rem 0;border-bottom:1px solid rgba(255,255,255,0.05);}
.detail-row:last-child{border-bottom:none;}
.detail-label{color:var(--cyber-muted);}
.btn-goto{background:var(--cyber-accent);color:#050b18;border:none;padding:.85rem 1.5rem;border-radius:8px;font-weight:700;font-size:.92rem;text-decoration:none;display:inline-block;margin:.25rem;}
.btn-goto:hover{background:var(--cyber-green);color:#050b18;}
.btn-outline{border:1px solid var(--cyber-border);color:var(--cyber-text);background:transparent;padding:.85rem 1.5rem;border-radius:8px;font-size:.92rem;text-decoration:none;display:inline-block;margin:.25rem;}
.btn-outline:hover{border-color:var(--cyber-accent);color:var(--cyber-accent);}
</style>
</head>
<body>

<div class="success-card">
  <div class="success-icon"><i class="fas fa-check-circle"></i></div>
  <h1 class="success-title">You&rsquo;re registered!</h1>
  <p class="success-subtitle">
    Your <?= $type === 'bootcamp' ? 'bootcamp enrollment' : 'webinar registration' ?> for
    <strong style="color:#fff"><?= htmlspecialchars($item['title']) ?></strong> is confirmed.
  </p>

  <div class="details-box">
    <div class="detail-row">
      <span class="detail-label">Registration #</span>
      <span><?= htmlspecialchars($reg['registration_no'] ?? $reg['enrollment_no'] ?? '') ?></span>
    </div>
    <div class="detail-row">
      <span class="detail-label">Status</span>
      <span style="color:var(--cyber-green)"><?= htmlspecialchars(ucfirst($reg['payment_status'] ?? 'confirmed')) ?></span>
    </div>
    <?php if ($type === 'webinar'): ?>
    <div class="detail-row">
      <span class="detail-label">Session</span>
      <span><?= date('d M Y, h:i A', strtotime($item['scheduled_at'])) ?></span>
    </div>
    <?php else: ?>
    <div class="detail-row">
      <span class="detail-label">Starts</span>
      <span><?= date('d M Y', strtotime($item['start_date'])) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div>
    <a href="<?= url('my-registrations.php') ?>" class="btn-goto"><i class="fas fa-ticket-alt me-1"></i> My Registrations</a>
    <a href="<?= url($type === 'bootcamp' ? 'bootcamps.php' : 'webinars.php') ?>" class="btn-outline">Browse more</a>
  </div>
  <p style="margin-top:1.25rem;font-size:.8rem;color:var(--cyber-muted)">
    Session details will appear in <a href="<?= url('my-registrations.php') ?>" style="color:var(--cyber-accent)">My Registrations</a> and your notifications.
  </p>
</div>

<?php renderSiteScripts(); ?>
</body>
</html>
