<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$payments = Payment::getHistory($ctx['userId']);

renderStudentHead('Payment History');
renderStudentSidebar('payments', $ctx);
?>
<main class="main">
  <div class="topbar"><div class="page-title">Payment History</div></div>
  <div class="content">
    <?php if (empty($payments)): ?>
    <div class="card-panel" style="color:var(--cyber-muted)">No payments yet.</div>
    <?php else: foreach ($payments as $p): ?>
    <div class="card-panel">
      <strong><?= htmlspecialchars($p['item_title'] ?? 'Payment') ?></strong>
      <p style="margin:.25rem 0;color:var(--cyber-muted)">₹<?= number_format($p['amount'], 2) ?> · <?= htmlspecialchars($p['status']) ?></p>
      <small style="color:var(--cyber-muted)"><?= timeAgo($p['created_at']) ?></small>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
