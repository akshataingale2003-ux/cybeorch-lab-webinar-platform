<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$rows = adminDb(
    fn () => db()->fetchAll(
        "SELECT a.*, u.full_name, u.email, w.title AS webinar_title
         FROM attendance a
         JOIN users u ON u.id = a.user_id
         JOIN webinars w ON w.id = a.webinar_id
         WHERE " . adminSqlActive('a') . "
         ORDER BY a.created_at DESC LIMIT 100"
    ),
    []
);

renderAdminPageStart('Attendance', 'attendance', 'fa-user-check');
?>

<div class="section-card">
  <div class="section-card-header">Webinar attendance (<?= count($rows) ?>)</div>
  <div class="section-card-body">
    <table class="data-table">
      <thead><tr><th>Date</th><th>User Registration &amp; Trainee</th><th>Webinar</th><th>Joined</th><th>Duration</th><th>Marked by</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
        <tr><td colspan="7" style="color:var(--cyber-muted)">No attendance records yet.</td></tr>
        <?php else: foreach ($rows as $a):
          $blocked = adminRecordIsBlocked($a);
        ?>
        <tr<?= renderAdminRecordRowAttrs('attendance', (int) $a['id'], $blocked) ?>>
          <td><?= date('d M Y', strtotime($a['created_at'])) ?></td>
          <td><?= htmlspecialchars($a['full_name']) ?></td>
          <td><?= htmlspecialchars($a['webinar_title']) ?></td>
          <td><?= $a['joined_at'] ? date('H:i', strtotime($a['joined_at'])) : '—' ?></td>
          <td><?= (int) $a['duration_mins'] ?> min</td>
          <td><?= htmlspecialchars($a['marked_by']) ?></td>
          <td><?php renderAdminRecordActions('attendance', (int) $a['id'], $blocked); ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
