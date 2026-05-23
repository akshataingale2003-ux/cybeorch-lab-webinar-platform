<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
extract($ctx);
$userId = $ctx['userId'];
$wallet = $ctx['wallet'];

$balance     = $wallet ? (float) $wallet['balance'] : 0.00;
$totalEarned = $wallet ? (float) $wallet['total_earned'] : 0.00;
$totalSpent  = $wallet ? (float) $wallet['total_spent'] : 0.00;

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'credit', 'debit'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$txParams = [$userId];
$whereClause = '';
if ($filter !== 'all') {
    $whereClause = ' AND type = ?';
    $txParams[] = $filter;
}
$transactions = db()->fetchAll(
    "SELECT * FROM wallet_transactions WHERE user_id = ?{$whereClause} ORDER BY created_at DESC LIMIT 50",
    $txParams
);

$referralCount  = db()->fetchOne('SELECT COUNT(*) as c FROM referrals WHERE referrer_id = ?', [$userId])['c'];
$referralEarned = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as s FROM wallet_transactions WHERE user_id = ? AND reason='referral_bonus'", [$userId])['s'];

$reasonLabels = [
    'webinar_reward'  => 'Webinar Reward',
    'referral_bonus'  => 'Referral Bonus',
    'bootcamp_reward' => 'Bootcamp Reward',
    'admin_credit'    => 'Admin Credit',
    'redemption'      => 'Redeemed',
    'cashback'        => 'Cashback',
    'signup_bonus'    => 'Signup Bonus',
];

renderStudentHead('NxL Wallet');
renderStudentSidebar('wallet', $ctx);
?>
<main class="main">
  <div class="topbar">
    <div class="page-title"><i class="fas fa-coins" style="color:var(--cyber-green)"></i>NxL Wallet</div>
    <div style="font-size:0.82rem;color:var(--cyber-muted)">Future-ready learning credits ecosystem</div>
  </div>

  <div class="content">

    <!-- Wallet Hero -->
    <div class="wallet-hero">
      <div class="wallet-balance-label"><i class="fas fa-coins me-1"></i>Available NxL Token Balance</div>
      <div class="wallet-balance-amount"><?= number_format($balance, 0) ?><span class="wallet-balance-unit">NxL</span></div>
      <div style="font-size:0.82rem;color:var(--cyber-muted);margin-top:0.5rem">≈ ₹<?= number_format($balance * 0.5, 0) ?> equivalent discount value</div>
      <div class="wallet-stats">
        <div class="w-stat">
          <div class="w-stat-val" style="color:var(--cyber-green)"><?= number_format($totalEarned, 0) ?></div>
          <div class="w-stat-label">Total Earned</div>
        </div>
        <div class="w-stat">
          <div class="w-stat-val" style="color:#ff6666"><?= number_format($totalSpent, 0) ?></div>
          <div class="w-stat-label">Total Spent</div>
        </div>
        <div class="w-stat">
          <div class="w-stat-val"><?= $referralCount ?></div>
          <div class="w-stat-label">Referrals Made</div>
        </div>
        <div class="w-stat">
          <div class="w-stat-val" style="color:var(--cyber-accent)"><?= number_format($referralEarned, 0) ?></div>
          <div class="w-stat-label">Referral Earnings</div>
        </div>
      </div>
    </div>

    <!-- How to Earn -->
    <div class="row g-3 mb-4">
      <div class="col-12"><h5 style="font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:0.25rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:1px;font-size:0.82rem">How to Earn NxL Tokens</h5></div>
      <div class="col-6 col-md-3">
        <div class="earn-card">
          <div class="earn-icon" style="background:rgba(0,212,255,0.1);color:var(--cyber-accent)"><i class="fas fa-user-plus"></i></div>
          <div class="earn-amount">+<?= NXL_SIGNUP_BONUS ?></div>
          <div class="earn-label">Sign Up Bonus</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="earn-card">
          <div class="earn-icon" style="background:rgba(0,255,136,0.1);color:var(--cyber-green)"><i class="fas fa-video"></i></div>
          <div class="earn-amount">+<?= NXL_WEBINAR_REWARD ?></div>
          <div class="earn-label">Per Webinar Attended</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="earn-card">
          <div class="earn-icon" style="background:rgba(255,107,53,0.1);color:var(--cyber-orange)"><i class="fas fa-users"></i></div>
          <div class="earn-amount">+<?= NXL_REFERRAL_BONUS ?></div>
          <div class="earn-label">Per Referral</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="earn-card">
          <div class="earn-icon" style="background:rgba(168,85,247,0.1);color:#a855f7"><i class="fas fa-graduation-cap"></i></div>
          <div class="earn-amount">+<?= NXL_BOOTCAMP_REWARD ?></div>
          <div class="earn-label">Per Bootcamp Enrolled</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Transaction History -->
      <div class="col-lg-8">
        <div class="section-card tx-table">
          <div class="section-card-header">
            <div class="section-card-title"><i class="fas fa-history" style="color:var(--cyber-accent)"></i>Token History</div>
            <div class="filter-tabs">
              <a href="?filter=all"    class="filter-tab <?= $filter==='all'    ? 'active' : '' ?>">All</a>
              <a href="?filter=credit" class="filter-tab <?= $filter==='credit' ? 'active' : '' ?>">Credits</a>
              <a href="?filter=debit"  class="filter-tab <?= $filter==='debit'  ? 'active' : '' ?>">Debits</a>
            </div>
          </div>

          <div class="table-responsive-wrap">
          <div class="tx-table-header" style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;gap:1rem;padding:0.65rem 1.5rem;border-bottom:1px solid var(--cyber-border);background:rgba(0,0,0,0.1);min-width:520px;">
            <div style="font-size:0.7rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:1px"></div>
            <div style="font-size:0.7rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:1px">Description</div>
            <div style="font-size:0.7rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:1px">Balance After</div>
            <div style="font-size:0.7rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:1px;text-align:right">Amount</div>
          </div>

          <?php if (empty($transactions)): ?>
          <div style="text-align:center;padding:3rem;color:var(--cyber-muted)">
            <i class="fas fa-coins" style="font-size:2.5rem;opacity:0.3;display:block;margin-bottom:0.75rem"></i>
            No transactions yet. Start earning NxL tokens!
          </div>
          <?php else: ?>
          <?php foreach ($transactions as $tx): ?>
          <div class="tx-row">
            <div class="tx-icon <?= $tx['type'] === 'credit' ? 'tx-credit' : 'tx-debit' ?>">
              <i class="fas fa-<?= $tx['type'] === 'credit' ? 'arrow-down' : 'arrow-up' ?>"></i>
            </div>
            <div>
              <div style="font-size:0.85rem;font-weight:500;margin-bottom:2px"><?= htmlspecialchars($tx['description'] ?? 'Token transaction') ?></div>
              <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
                <span class="badge-reason"><?= $reasonLabels[$tx['reason']] ?? $tx['reason'] ?></span>
                <span style="font-size:0.72rem;color:var(--cyber-muted)"><?= timeAgo($tx['created_at']) ?></span>
              </div>
            </div>
            <div class="tx-bal"><?= number_format($tx['balance_after'] ?? 0, 0) ?> NxL</div>
            <div class="<?= $tx['type'] === 'credit' ? 'tx-amount-credit' : 'tx-amount-debit' ?>" style="text-align:right">
              <?= $tx['type'] === 'credit' ? '+' : '-' ?><?= number_format($tx['amount'], 0) ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Side Panel -->
      <div class="col-lg-4">
        <!-- Referral -->
        <div class="section-card mb-3" style="overflow:visible">
          <div class="section-card-header"><div class="section-card-title"><i class="fas fa-users" style="color:var(--cyber-green)"></i>Refer & Earn</div></div>
          <div style="padding:1.25rem">
            <p style="font-size:0.85rem;color:var(--cyber-muted);margin-bottom:0.75rem">Share your link — earn <strong style="color:var(--cyber-green)"><?= NXL_REFERRAL_BONUS ?> NxL</strong> per successful referral.</p>
            <div style="display:flex;gap:0.5rem">
              <input type="text" id="refLink" readonly value="<?= SITE_URL ?>/index.php?register_required=1&ref=<?= htmlspecialchars($user['referral_code']) ?>"
                style="flex:1;background:rgba(255,255,255,0.05);border:1px solid var(--cyber-border);border-radius:7px;padding:0.6rem 0.75rem;color:var(--cyber-text);font-size:0.75rem;outline:none;min-width:0">
              <button onclick="copyRef()" id="copyBtn" style="background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.6rem 0.85rem;border-radius:7px;font-weight:600;font-size:0.8rem;cursor:pointer;white-space:nowrap">Copy</button>
            </div>
            <div style="font-size:0.78rem;color:var(--cyber-muted);margin-top:0.5rem">Your code: <strong style="color:var(--cyber-text)"><?= htmlspecialchars($user['referral_code']) ?></strong></div>
          </div>
        </div>

        <!-- Redeem -->
        <div class="redeem-card mb-3">
          <div style="font-family:'Rajdhani',sans-serif;font-size:1.1rem;font-weight:600;margin-bottom:0.5rem;color:var(--cyber-orange)"><i class="fas fa-gift me-2"></i>Redeem NxL Tokens</div>
          <p style="font-size:0.82rem;color:var(--cyber-muted);margin-bottom:1rem">Use NxL tokens to get discounts on webinars and bootcamps during checkout.</p>
          <div style="background:rgba(0,0,0,0.2);border-radius:8px;padding:0.75rem;font-size:0.82rem;color:var(--cyber-muted);">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem"><span>1 NxL Token =</span><span style="color:var(--cyber-text)">₹0.50 discount</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.4rem"><span>Max per checkout =</span><span style="color:var(--cyber-text)">50% of fee</span></div>
            <div style="display:flex;justify-content:space-between"><span>Your current value =</span><span style="color:var(--cyber-green);font-weight:600">₹<?= number_format($balance * 0.5) ?></span></div>
          </div>
          <a href="<?= url('webinars.php') ?>" style="display:block;text-align:center;margin-top:1rem;background:rgba(255,107,53,0.15);border:1px solid rgba(255,107,53,0.3);color:var(--cyber-orange);padding:0.6rem;border-radius:7px;text-decoration:none;font-size:0.85rem;transition:all 0.2s">
            <i class="fas fa-shopping-cart me-1"></i>Use on Next Purchase
          </a>
        </div>

        <!-- NxL Roadmap (future) -->
        <div class="section-card" style="overflow:visible">
          <div class="section-card-header"><div class="section-card-title"><i class="fas fa-road" style="color:#a855f7"></i>NxL Ecosystem Roadmap</div></div>
          <div style="padding:1.25rem">
            <?php
            $roadmap = [
              ['label'=>'Wallet & Rewards',    'done'=>true,  'color'=>'var(--cyber-green)'],
              ['label'=>'Pay with NxL Tokens', 'done'=>true,  'color'=>'var(--cyber-green)'],
              ['label'=>'Referral System',      'done'=>true,  'color'=>'var(--cyber-green)'],
              ['label'=>'NxL Cashback',         'done'=>false, 'color'=>'var(--cyber-accent)'],
              ['label'=>'Premium Access Unlock','done'=>false, 'color'=>'var(--cyber-accent)'],
              ['label'=>'NxL Marketplace',      'done'=>false, 'color'=>'var(--cyber-muted)'],
              ['label'=>'NxL-to-INR Conversion','done'=>false, 'color'=>'var(--cyber-muted)'],
            ];
            foreach ($roadmap as $item): ?>
            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.4rem 0;font-size:0.82rem">
              <i class="fas fa-<?= $item['done'] ? 'check-circle' : 'circle' ?>" style="color:<?= $item['color'] ?>;font-size:0.9rem"></i>
              <span style="color:<?= $item['done'] ? 'var(--cyber-text)' : 'var(--cyber-muted)' ?>"><?= $item['label'] ?></span>
              <?php if (!$item['done'] && $item['color'] === 'var(--cyber-accent)'): ?>
              <span style="font-size:0.68rem;background:rgba(0,212,255,0.1);color:var(--cyber-accent);padding:1px 6px;border-radius:4px;margin-left:auto">Soon</span>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
function copyRef() {
  const inp = document.getElementById('refLink');
  if (!inp) return;
  inp.select();
  navigator.clipboard?.writeText(inp.value).catch(function(){ document.execCommand('copy'); });
  const btn = document.getElementById('copyBtn');
  if (!btn) return;
  const orig = btn.textContent;
  btn.textContent = '✓ Copied!';
  btn.style.background = 'var(--cyber-green)';
  setTimeout(function () { btn.textContent = orig; btn.style.background = 'var(--cyber-accent)'; }, 2000);
}
</script>
<?php renderStudentLayoutEnd(); ?>
