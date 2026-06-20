<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$action = $_GET['action'] ?? '';
$broadcastMsg = '';
$broadcastMsgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'broadcast' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target = $_POST['target'] ?? 'all';

    if (strlen($title) < 2 || strlen($message) < 5) {
        $broadcastMsg = 'Title and message are required (title 2+ chars, message 5+ chars).';
        $broadcastMsgType = 'error';
        $action = 'broadcast';
    } else {
        try {
            if ($target === 'all') {
                $users = db()->fetchAll(
                    'SELECT id FROM users WHERE ' . adminSqlActive() . ' AND COALESCE(is_blocked, 0) = 0'
                );
                if (!$users) {
                    $broadcastMsg = 'No active user registrations & trainees found to notify.';
                    $broadcastMsgType = 'error';
                    $action = 'broadcast';
                } else {
                    foreach ($users as $u) {
                        sendNotification((int) $u['id'], 'system', $title, $message);
                    }
                    setFlash('success', 'Notification sent to ' . count($users) . ' user registration & trainee(s).');
                    header('Location: ' . adminUrl('admin/notifications.php'));
                    exit;
                }
            } else {
                $userId = (int) $target;
                if ($userId <= 0) {
                    $broadcastMsg = 'Please select a valid user registration & trainee.';
                    $broadcastMsgType = 'error';
                    $action = 'broadcast';
                } else {
                    $student = db()->fetchOne(
                        'SELECT id FROM users WHERE id = ? AND ' . adminSqlActive() . ' AND COALESCE(is_blocked, 0) = 0',
                        [$userId]
                    );
                    if (!$student) {
                        $broadcastMsg = 'Selected user registration & trainee not found or is blocked.';
                        $broadcastMsgType = 'error';
                        $action = 'broadcast';
                    } else {
                        sendNotification($userId, 'system', $title, $message);
                        setFlash('success', 'Notification sent to selected user registration & trainee.');
                        header('Location: ' . adminUrl('admin/notifications.php'));
                        exit;
                    }
                }
            }
        } catch (Throwable $e) {
            $broadcastMsg = 'Broadcast failed. Check database connection and try again.';
            $broadcastMsgType = 'error';
            $action = 'broadcast';
        }
    }
}

$allStudents = db()->fetchAll('SELECT id, full_name, email FROM users WHERE ' . adminSqlActive() . ' ORDER BY full_name ASC');

$notifications = adminDb(
    fn () => db()->fetchAll(
        'SELECT n.*, u.full_name, u.email FROM notifications n
         LEFT JOIN users u ON u.id = n.user_id
         WHERE ' . adminSqlActive('n') . '
         ORDER BY n.created_at DESC LIMIT 100'
    ),
    []
);

renderAdminPageStart('Notifications', 'notifications', 'fa-bell');

$flash = getFlash();
if ($flash) {
    $broadcastMsg = $flash['message'];
    $broadcastMsgType = $flash['type'] === 'success' ? 'success' : 'error';
}
?>

<?php if ($broadcastMsg): ?>
<div class="alert alert-<?= $broadcastMsgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($broadcastMsg) ?></div>
<?php endif; ?>

<?php if ($action === 'broadcast'): ?>
<div class="form-card mb-4">
  <div class="form-card-header"><i class="fas fa-broadcast-tower"></i> Broadcast Notification</div>
  <div class="form-card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="action" value="broadcast">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Send to</label>
          <select name="target" class="form-select">
            <option value="all">All user registrations & trainees</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" required placeholder="Notification title">
        </div>
        <div class="col-12">
          <label class="form-label">Message *</label>
          <textarea name="message" class="form-control" rows="4" required placeholder="Your message to user registrations & trainees..."></textarea>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit"><i class="fas fa-paper-plane me-1"></i>Send</button>
          <a href="<?= adminUrl('admin/notifications.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
<p class="mb-3"><a href="<?= adminUrl('admin/notifications.php?action=broadcast') ?>" class="btn-primary-cyber"><i class="fas fa-broadcast-tower"></i>Broadcast</a></p>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">System notifications (<?= count($notifications) ?>)</div>
  <div class="section-card-body">
    <table class="data-table">
      <thead><tr><th>Date</th><th>User</th><th>Type</th><th>Title</th><th>Read</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$notifications): ?>
        <tr><td colspan="6" style="color:var(--cyber-muted)">No notifications.</td></tr>
        <?php else: foreach ($notifications as $n):
          $blocked = adminRecordIsBlocked($n);
        ?>
        <tr<?= renderAdminRecordRowAttrs('notification', (int) $n['id'], $blocked) ?>>
          <td><?= date('d M Y H:i', strtotime($n['created_at'])) ?></td>
          <td><?= $n['full_name'] ? htmlspecialchars($n['full_name']) : '<em style="color:var(--cyber-muted)">Broadcast</em>' ?></td>
          <td><?= htmlspecialchars($n['type']) ?></td>
          <td><?= htmlspecialchars($n['title']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars(substr($n['message'], 0, 80)) ?></span></td>
          <td><?= (int) $n['is_read'] ? 'Yes' : 'No' ?></td>
          <td><?php renderAdminRecordActions('notification', (int) $n['id'], $blocked); ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
