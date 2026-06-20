<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$userId = $ctx['userId'];
$notifications = db()->fetchAll('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50', [$userId]);
db()->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);

renderStudentHead('Notifications');
renderStudentSidebar('notifications', $ctx);
?>
<main class="main">
  <?php renderPortalTopbar('<i class="fas fa-bell me-2" style="color:var(--cyber-orange)"></i>Notifications'); ?>
  <div class="content">
    <?php if (empty($notifications)): ?>
    <div class="card-panel" style="color:var(--cyber-muted)">No notifications yet.</div>
    <?php else: foreach ($notifications as $n): ?>
    <div class="card-panel">
      <strong><?= htmlspecialchars($n['title']) ?></strong>
      <p style="margin:.35rem 0;color:var(--cyber-muted);font-size:.88rem"><?= htmlspecialchars($n['message']) ?></p>
      <small style="color:var(--cyber-muted)"><?= timeAgo($n['created_at']) ?></small>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
