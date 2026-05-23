<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$referrals = adminDb(
    fn () => db()->fetchAll(
        "SELECT r.*,
                u1.full_name AS referrer_name, u1.email AS referrer_email,
                u2.full_name AS referred_name, u2.email AS referred_email
         FROM referrals r
         JOIN users u1 ON u1.id = r.referrer_id
         JOIN users u2 ON u2.id = r.referred_id
         WHERE " . adminSqlActive('r') . "
         ORDER BY r.created_at DESC LIMIT 100"
    ),
    []
);

renderAdminPageStart('Referrals', 'referrals', 'fa-share-alt');
?>

<div class="section-card">
  <div class="section-card-header">Referral records (<?= count($referrals) ?>)</div>
  <div class="section-card-body">
    <table class="data-table">
      <thead><tr><th>Date</th><th>Referrer</th><th>Referred user</th><th>Bonus</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (!$referrals): ?>
        <tr><td colspan="6" style="color:var(--cyber-muted)">No referrals yet.</td></tr>
        <?php else: foreach ($referrals as $r):
          $blocked = adminRecordIsBlocked($r);
        ?>
        <tr<?= renderAdminRecordRowAttrs('referral', (int) $r['id'], $blocked) ?>>
          <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
          <td><?= htmlspecialchars($r['referrer_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($r['referrer_email']) ?></span></td>
          <td><?= htmlspecialchars($r['referred_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($r['referred_email']) ?></span></td>
          <td>₹<?= number_format((float) $r['bonus_amount'], 2) ?></td>
          <td><span class="badge-status badge-<?= $r['status'] === 'rewarded' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
          <td><?php renderAdminRecordActions('referral', (int) $r['id'], $blocked); ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
