<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$action = $_GET['action'] ?? '';
$creditMsg = '';
$creditMsgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'credit' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? 'Admin credit');

    if ($userId > 0 && $amount > 0) {
        creditWallet($userId, $amount, 'admin_credit', null, $description ?: 'Admin credit');
        sendNotification($userId, 'wallet', 'NxL Tokens Credited', "You received {$amount} NxL tokens. " . $description);
        $creditMsg = 'Wallet credited successfully.';
    } else {
        $creditMsg = 'Select a user registration & trainee and enter a valid amount.';
        $creditMsgType = 'error';
    }
    $action = 'credit';
}

$allStudents = db()->fetchAll('SELECT id, full_name, email FROM users WHERE ' . adminSqlActive() . ' ORDER BY full_name ASC');

$wallets = db()->fetchAll(
    'SELECT w.*, u.full_name, u.email FROM wallet w JOIN users u ON u.id = w.user_id
     WHERE ' . adminSqlActive('w') . ' ORDER BY w.balance DESC LIMIT 100'
);

$transactions = db()->fetchAll(
    'SELECT wt.*, u.full_name, u.email FROM wallet_transactions wt
     JOIN users u ON u.id = wt.user_id
     WHERE ' . adminSqlActive('wt') . ' ORDER BY wt.created_at DESC LIMIT 80'
);

$totalBalance = db()->fetchOne('SELECT COALESCE(SUM(balance),0) AS s FROM wallet WHERE ' . adminSqlActive())['s'] ?? 0;

renderAdminPageStart('NxL Wallet', 'wallet', 'fa-coins');
?>

<?php if ($creditMsg): ?>
<div class="alert alert-<?= $creditMsgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($creditMsg) ?></div>
<?php endif; ?>

<?php if ($action === 'credit'): ?>
<div class="form-card mb-4">
  <div class="form-card-header"><i class="fas fa-coins"></i> Credit NxL Tokens</div>
  <div class="form-card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="action" value="credit">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">User Registration & Trainee *</label>
          <select name="user_id" class="form-select" required>
            <option value="">Select user registration & trainee</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Amount (NxL) *</label>
          <input type="number" name="amount" class="form-control" min="1" step="1" required placeholder="e.g. 50">
        </div>
        <div class="col-md-4">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Reason for credit">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit"><i class="fas fa-plus me-1"></i>Credit Tokens</button>
          <a href="<?= url('admin/wallet.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
<p class="mb-3"><a href="<?= url('admin/wallet.php?action=credit') ?>" class="btn-primary-cyber"><i class="fas fa-plus"></i>Credit NxL</a></p>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="section-card"><div class="section-card-body">
    <div style="font-size:0.8rem;color:var(--cyber-muted)">Total wallet balance</div>
    <div style="font-family:'Rajdhani',sans-serif;font-size:2rem;color:var(--cyber-accent)">₹<?= number_format((float) $totalBalance, 2) ?></div>
  </div></div></div>
</div>

<div class="section-card mb-4">
  <div class="section-card-header">User Registration & Trainee wallets</div>
  <div class="section-card-body">
    <table class="data-table">
      <thead><tr><th>User Registration &amp; Trainee</th><th>Balance</th><th>Earned</th><th>Spent</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($wallets as $w):
          $blocked = adminRecordIsBlocked($w);
        ?>
        <tr<?= renderAdminRecordRowAttrs('wallet', (int) $w['id'], $blocked) ?>>
          <td><?= htmlspecialchars($w['full_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($w['email']) ?></span></td>
          <td>₹<?= number_format((float) $w['balance'], 2) ?></td>
          <td>₹<?= number_format((float) $w['total_earned'], 2) ?></td>
          <td>₹<?= number_format((float) $w['total_spent'], 2) ?></td>
          <td><?= date('d M Y', strtotime($w['updated_at'])) ?></td>
          <td><?php renderAdminRecordActions('wallet', (int) $w['id'], $blocked); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">Recent transactions</div>
  <div class="section-card-body">
    <table class="data-table">
      <thead><tr><th>Date</th><th>User Registration &amp; Trainee</th><th>Type</th><th>Amount</th><th>Reason</th><th>Balance after</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($transactions as $t):
          $txBlocked = adminRecordIsBlocked($t);
        ?>
        <tr<?= renderAdminRecordRowAttrs('wallet_transaction', (int) $t['id'], $txBlocked) ?>>
          <td><?= date('d M Y H:i', strtotime($t['created_at'])) ?></td>
          <td><?= htmlspecialchars($t['full_name']) ?></td>
          <td><span class="badge-status badge-<?= $t['type'] === 'credit' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($t['type']) ?></span></td>
          <td>₹<?= number_format((float) $t['amount'], 2) ?></td>
          <td><?= htmlspecialchars($t['reason']) ?></td>
          <td>₹<?= number_format((float) ($t['balance_after'] ?? 0), 2) ?></td>
          <td><?php renderAdminRecordActions('wallet_transaction', (int) $t['id'], $txBlocked); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
