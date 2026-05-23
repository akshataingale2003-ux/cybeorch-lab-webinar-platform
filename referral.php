<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$user = $ctx['user'];
$refLink = url('index.php?register_required=1&ref=' . urlencode($user['referral_code']));
$count = db()->fetchOne('SELECT COUNT(*) as c FROM referrals WHERE referrer_id = ?', [$ctx['userId']])['c'];

renderStudentHead('Refer & Earn');
renderStudentSidebar('referral', $ctx);
?>
<main class="main">
  <div class="topbar"><div class="page-title">Refer & Earn</div></div>
  <div class="content">
    <div class="card-panel">
      <p style="color:var(--cyber-muted)">Share your code and earn <?= NXL_REFERRAL_BONUS ?> NxL when friends join.</p>
      <p style="font-family:'Rajdhani',sans-serif;font-size:1.5rem;color:var(--cyber-green);margin:1rem 0"><?= htmlspecialchars($user['referral_code']) ?></p>
      <p style="font-size:.85rem;word-break:break-all;color:var(--cyber-accent)"><?= htmlspecialchars($refLink) ?></p>
      <p style="margin-top:1rem;color:var(--cyber-muted)">Successful referrals: <?= (int) $count ?></p>
    </div>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
