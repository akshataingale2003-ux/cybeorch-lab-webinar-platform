<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$user = $ctx['user'];

renderStudentHead('Profile');
renderStudentSidebar('profile', $ctx);
?>
<main class="main">
  <div class="topbar"><div class="page-title">Profile</div></div>
  <div class="content">
    <div class="card-panel">
      <p><strong>Name:</strong> <?= htmlspecialchars($user['full_name']) ?></p>
      <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
      <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? '—') ?></p>
      <p><strong>Referral code:</strong> <?= htmlspecialchars($user['referral_code']) ?></p>
      <p><strong>Member since:</strong> <?= date('d M Y', strtotime($user['created_at'])) ?></p>
    </div>
  </div>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
