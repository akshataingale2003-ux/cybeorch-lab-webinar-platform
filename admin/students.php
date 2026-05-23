<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$students = adminDb(
    fn () => db()->fetchAll(
        'SELECT u.*, COALESCE(w.balance, 0) AS wallet_balance
         FROM users u
         LEFT JOIN wallet w ON w.user_id = u.id
         WHERE ' . adminSqlActive('u') . '
         ORDER BY u.created_at DESC LIMIT 200'
    ),
    []
);

renderAdminPageStart('User Registrations & Trainees', 'students', 'fa-users');
?>

<div class="section-card">
  <div class="section-card-header">Registered user registrations & trainees (<?= count($students) ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Joined</th><th>Name</th><th>Email</th><th>Phone</th><th>Referral</th><th>Wallet</th><th>Verified</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$students): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No user registrations & trainees yet.</td></tr>
          <?php else: foreach ($students as $s):
            $blocked = adminRecordIsBlocked($s);
          ?>
          <tr<?= renderAdminRecordRowAttrs('user', (int) $s['id'], $blocked) ?>>
            <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
            <td><?= htmlspecialchars($s['full_name']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= htmlspecialchars($s['phone'] ?: '—') ?></td>
            <td style="font-size:0.8rem"><?= htmlspecialchars($s['referral_code'] ?: '—') ?></td>
            <td>₹<?= number_format((float) $s['wallet_balance'], 2) ?></td>
            <td><?= (int) $s['is_verified'] ? '<span class="badge-status badge-paid">Yes</span>' : '<span class="badge-status badge-pending">No</span>' ?><?php if ($blocked): ?><span class="admin-block-badge">Blocked</span><?php endif; ?></td>
            <td><?php renderAdminRecordActions('user', (int) $s['id'], $blocked); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
