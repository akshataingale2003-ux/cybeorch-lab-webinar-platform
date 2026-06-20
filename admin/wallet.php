<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
requireAdminLogin();

markAdminWalletNotificationsRead();

$action = $_GET['action'] ?? '';
$creditMsg = '';
$creditMsgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $postAction = (string) ($_POST['action'] ?? '');
    $userId = (int) ($_POST['user_id'] ?? 0);
    $amount = round((float) ($_POST['amount'] ?? 0), 2);
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($postAction === 'credit' && $userId > 0 && $amount > 0) {
        creditWallet($userId, $amount, 'admin_credit', null, $description ?: 'Admin credit');
        sendNotification($userId, 'wallet', nxlWalletDisplayName() . ' Credited', 'You received ' . formatNxlCredits($amount) . ' (' . formatNxlInrEquivalent($amount) . '). ' . $description);
        $creditMsg = 'Wallet credited successfully.';
        $action = 'credit';
    } elseif ($postAction === 'debit' && $userId > 0 && $amount > 0) {
        $result = debitWallet($userId, $amount, 'admin_debit', null, $description ?: 'Admin debit');
        if ($result['success']) {
            sendNotification($userId, 'wallet', nxlWalletDisplayName() . ' Debited', formatNxlCredits($amount) . ' deducted by admin. ' . $description);
            $creditMsg = 'Wallet debited successfully.';
        } else {
            $creditMsg = $result['message'];
            $creditMsgType = 'error';
        }
        $action = 'debit';
    } elseif ($postAction === 'transfer') {
        $toUserId = (int) ($_POST['to_user_id'] ?? 0);
        $transfer = transferNxlCredits($userId, $toUserId, $amount, $description ?: 'Admin-initiated transfer');
        $creditMsg = $transfer['message'];
        $creditMsgType = $transfer['success'] ? 'success' : 'error';
        $action = 'transfer';
    } else {
        $creditMsg = 'Select a user and enter a valid amount.';
        $creditMsgType = 'error';
    }
}

$allStudents = db()->fetchAll('SELECT id, full_name, email FROM users WHERE ' . adminSqlActive() . ' ORDER BY full_name ASC');

$wallets = db()->fetchAll(
    'SELECT w.*, u.full_name, u.email FROM wallet w JOIN users u ON u.id = w.user_id
     WHERE ' . adminSqlActive('w') . ' ORDER BY w.balance DESC LIMIT 100'
);

$transactions = db()->fetchAll(
    'SELECT wt.*, u.full_name, u.email FROM wallet_transactions wt
     JOIN users u ON u.id = wt.user_id
     WHERE ' . adminSqlActive('wt') . ' ORDER BY wt.created_at DESC LIMIT 500'
);

$circulation = getNxlCirculationStats();
$totalCreditsAdded = (float) (db()->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions WHERE type = 'credit' AND " . adminSqlActive()
)['s'] ?? 0);
$totalCreditsUsed = (float) (db()->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS s FROM wallet_transactions WHERE type = 'debit' AND " . adminSqlActive()
)['s'] ?? 0);

renderAdminPageStart(nxlWalletDisplayName(), 'wallet', 'fa-coins');
?>

<?php if ($creditMsg): ?>
<div class="alert alert-<?= $creditMsgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($creditMsg) ?></div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="<?= adminUrl('wallet.php?action=credit') ?>" class="btn-primary-cyber"><i class="fas fa-plus"></i> Credit NXL</a>
  <a href="<?= adminUrl('wallet.php?action=debit') ?>" class="btn-cancel"><i class="fas fa-minus"></i> Debit NXL</a>
  <a href="<?= adminUrl('wallet.php?action=transfer') ?>" class="btn-cancel"><i class="fas fa-exchange-alt"></i> Transfer</a>
</div>

<?php if ($action === 'credit'): ?>
<div class="form-card mb-4">
  <div class="form-card-header"><i class="fas fa-plus-circle"></i> Credit NXL Credits</div>
  <div class="form-card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="action" value="credit">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">User *</label>
          <select name="user_id" class="form-select" required>
            <option value="">Select user</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Credits *</label>
          <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="e.g. 50">
        </div>
        <div class="col-md-4">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Reason for credit">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit"><i class="fas fa-plus me-1"></i>Credit</button>
          <a href="<?= adminUrl('wallet.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php elseif ($action === 'debit'): ?>
<div class="form-card mb-4">
  <div class="form-card-header"><i class="fas fa-minus-circle"></i> Debit NXL Credits</div>
  <div class="form-card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="action" value="debit">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label">User *</label>
          <select name="user_id" class="form-select" required>
            <option value="">Select user</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Credits *</label>
          <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required placeholder="e.g. 25">
        </div>
        <div class="col-md-4">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Reason for debit">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit"><i class="fas fa-minus me-1"></i>Debit</button>
          <a href="<?= adminUrl('wallet.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php elseif ($action === 'transfer'): ?>
<div class="form-card mb-4">
  <div class="form-card-header"><i class="fas fa-exchange-alt"></i> Transfer NXL Credits</div>
  <div class="form-card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="hidden" name="action" value="transfer">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">From user *</label>
          <select name="user_id" class="form-select" required>
            <option value="">Select sender</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">To user *</label>
          <select name="to_user_id" class="form-select" required>
            <option value="">Select recipient</option>
            <?php foreach ($allStudents as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' — ' . $s['email']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Credits *</label>
          <input type="number" name="amount" class="form-control" min="0.01" step="0.01" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Description</label>
          <input type="text" name="description" class="form-control" placeholder="Transfer note">
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit"><i class="fas fa-exchange-alt me-1"></i>Transfer</button>
          <a href="<?= adminUrl('wallet.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="section-card"><div class="section-card-body">
      <div style="font-size:0.8rem;color:var(--cyber-muted)">Credits in circulation</div>
      <div style="font-family:'Rajdhani',sans-serif;font-size:1.75rem;color:var(--cyber-accent)"><?= formatNxlCredits($circulation['total_credits']) ?></div>
      <div style="font-size:0.78rem;color:var(--cyber-muted)"><?= formatNxlInrEquivalent($circulation['total_credits']) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="section-card"><div class="section-card-body">
      <div style="font-size:0.8rem;color:var(--cyber-muted)">Credits added (all time)</div>
      <div style="font-family:'Rajdhani',sans-serif;font-size:1.75rem;color:var(--cyber-green)"><?= formatNxlCredits($totalCreditsAdded) ?></div>
      <div style="font-size:0.78rem;color:var(--cyber-muted)"><?= formatNxlInrEquivalent($totalCreditsAdded) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="section-card"><div class="section-card-body">
      <div style="font-size:0.8rem;color:var(--cyber-muted)">Credits used (all time)</div>
      <div style="font-family:'Rajdhani',sans-serif;font-size:1.75rem;color:var(--cyber-orange)"><?= formatNxlCredits($totalCreditsUsed) ?></div>
      <div style="font-size:0.78rem;color:var(--cyber-muted)"><?= formatNxlInrEquivalent($totalCreditsUsed) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="section-card"><div class="section-card-body">
      <div style="font-size:0.8rem;color:var(--cyber-muted)">Wallets / transactions</div>
      <div style="font-family:'Rajdhani',sans-serif;font-size:1.75rem;color:var(--cyber-text)"><?= number_format($circulation['user_count']) ?></div>
      <div style="font-size:0.78rem;color:var(--cyber-muted)"><?= number_format($circulation['transaction_count']) ?> transactions</div>
    </div></div>
  </div>
</div>

<div class="section-card mb-4">
  <div class="section-card-header">User wallets</div>
  <div class="section-card-body table-responsive-wrap">
    <table class="data-table">
      <thead><tr><th>User</th><th>NXL Credits</th><th>INR Equivalent</th><th>Earned</th><th>Spent</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($wallets as $w):
          $blocked = adminRecordIsBlocked($w);
          $bal = (float) $w['balance'];
        ?>
        <tr<?= renderAdminRecordRowAttrs('wallet', (int) $w['id'], $blocked) ?>>
          <td><?= htmlspecialchars($w['full_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($w['email']) ?></span></td>
          <td><?= formatNxlCredits($bal) ?></td>
          <td><?= formatNxlInrEquivalent($bal) ?></td>
          <td><?= formatNxlCredits((float) $w['total_earned']) ?></td>
          <td><?= formatNxlCredits((float) $w['total_spent']) ?></td>
          <td><?= date('d M Y', strtotime($w['updated_at'])) ?></td>
          <td><?php renderAdminRecordActions('wallet', (int) $w['id'], $blocked); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$wallets): ?>
        <tr><td colspan="7" style="color:var(--cyber-muted);text-align:center;padding:1.5rem">No wallet records yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">
    <span><i class="fas fa-history me-2" style="color:var(--cyber-accent)"></i>Wallet Activity</span>
    <span style="font-size:0.78rem;color:var(--cyber-muted);font-weight:400">Showing <?= count($transactions) ?> of <?= number_format($circulation['transaction_count']) ?> transactions</span>
  </div>
  <div class="section-card-body table-responsive-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>User</th>
          <th>Type</th>
          <th>Credits</th>
          <th>INR Equivalent</th>
          <th>Balance After</th>
          <th>Description</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transactions as $t):
          $txBlocked = adminRecordIsBlocked($t);
          $isCredit = ($t['type'] ?? '') === 'credit';
          $amount = (float) ($t['amount'] ?? 0);
          $inr = (float) ($t['inr_equivalent'] ?? 0);
          if ($inr <= 0 && $amount > 0) {
              $inr = nxlTokensToInrDiscount($amount);
          }
          $balanceAfter = (float) ($t['balance_after'] ?? 0);
          $typeLabel = formatNxlTransactionTypeLabel((string) ($t['reason'] ?? ''), (string) ($t['type'] ?? ''));
        ?>
        <tr<?= renderAdminRecordRowAttrs('wallet_transaction', (int) $t['id'], $txBlocked) ?>>
          <td style="white-space:nowrap"><?= date('d M Y, h:i A', strtotime((string) $t['created_at'])) ?></td>
          <td>
            <div style="font-size:0.85rem;font-weight:500"><?= htmlspecialchars((string) $t['full_name']) ?></div>
            <div style="font-size:0.72rem;color:var(--cyber-muted)"><?= htmlspecialchars((string) $t['email']) ?></div>
          </td>
          <td><span class="badge-status badge-<?= $isCredit ? 'paid' : 'pending' ?>"><?= htmlspecialchars($typeLabel) ?></span></td>
          <td style="color:<?= $isCredit ? 'var(--cyber-green)' : 'var(--cyber-orange)' ?>;font-weight:600;white-space:nowrap"><?= ($isCredit ? '+' : '−') . number_format($amount, 0) ?></td>
          <td style="white-space:nowrap"><?= ($isCredit ? '+' : '−') . formatNxlInrEquivalent($amount) ?></td>
          <td style="white-space:nowrap"><?= formatNxlCredits($balanceAfter) ?></td>
          <td style="font-size:0.82rem;max-width:14rem"><?= htmlspecialchars((string) ($t['description'] ?: str_replace('_', ' ', (string) $t['reason']))) ?></td>
          <td><?php renderAdminRecordActions('wallet_transaction', (int) $t['id'], $txBlocked); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?>
        <tr><td colspan="8" style="color:var(--cyber-muted);text-align:center;padding:2rem">No wallet activity yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
