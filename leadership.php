<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$userId = $ctx['userId'];

$sort = $_GET['sort'] ?? 'balance';
$allowedSort = ['balance', 'earned', 'referrals'];
if (!in_array($sort, $allowedSort, true)) {
    $sort = 'balance';
}

$orderSql = match ($sort) {
    'earned'    => 'COALESCE(w.total_earned, 0) DESC, COALESCE(w.balance, 0) DESC',
    'referrals' => 'ref_count DESC, COALESCE(w.balance, 0) DESC',
    default     => 'COALESCE(w.balance, 0) DESC, COALESCE(w.total_earned, 0) DESC',
};

$leaders = dbTry(
    fn () => db()->fetchAll(
        "SELECT u.id, u.full_name, u.email,
                COALESCE(w.balance, 0) AS balance,
                COALESCE(w.total_earned, 0) AS total_earned,
                (SELECT COUNT(*) FROM referrals r WHERE r.referrer_id = u.id AND r.status = 'rewarded') AS ref_count,
                (SELECT COUNT(*) FROM webinar_registrations wr WHERE wr.user_id = u.id AND wr.attended = 1) AS sessions
         FROM users u
         LEFT JOIN wallet w ON w.user_id = u.id
         ORDER BY {$orderSql}
         LIMIT 50"
    ),
    []
);

$myRank = 0;
foreach ($leaders as $i => $row) {
    if ((int) $row['id'] === $userId) {
        $myRank = $i + 1;
        break;
    }
}

if ($myRank === 0 && $userId > 0) {
    $myRow = dbTry(
        fn () => db()->fetchOne(
            "SELECT u.id FROM users u WHERE u.id = ?",
            [$userId]
        ),
        null
    );
    if ($myRow) {
        $myRank = count($leaders) + 1;
    }
}

function leaderDisplayName(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    if (count($parts) <= 1) {
        return htmlspecialchars($fullName);
    }
    $last = array_pop($parts);
    return htmlspecialchars(implode(' ', $parts) . ' ' . strtoupper(substr($last, 0, 1)) . '.');
}

function leaderInitials(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [];
    $a = strtoupper(substr($parts[0] ?? 'U', 0, 1));
    $b = count($parts) > 1 ? strtoupper(substr($parts[1], 0, 1)) : '';
    return $a . $b;
}

renderStudentHead('Leadership');
renderStudentSidebar('leadership', $ctx);
?>
<main class="main">
  <div class="topbar">
    <div class="page-title"><i class="fas fa-trophy me-2" style="color:var(--cyber-accent)"></i>Leadership</div>
    <div class="topbar-actions">
      <a href="<?= url('wallet.php') ?>" class="topbar-link"><i class="fas fa-coins me-1"></i>My Wallet</a>
    </div>
  </div>

  <div class="content">
    <div class="welcome-banner" style="margin-bottom:1.25rem">
      <h3 style="font-family:'Rajdhani',sans-serif;font-size:1.35rem;font-weight:700;margin:0 0 .35rem">NxL Leadership Board</h3>
      <p style="color:var(--cyber-muted);font-size:.88rem;margin:0">Top learners ranked by tokens, earnings, and referrals. Earn more NxL to climb the board.</p>
    </div>

    <?php if ($myRank > 0): ?>
    <div class="leader-my-rank">
      <i class="fas fa-user-circle me-2"></i>
      Your rank: <strong>#<?= (int) $myRank ?></strong>
      <span class="ms-2" style="color:var(--cyber-muted)">· <?= number_format($ctx['balance'], 0) ?> NxL balance</span>
    </div>
    <?php endif; ?>

    <div class="filter-tabs mb-3">
      <a href="?sort=balance" class="filter-tab <?= $sort === 'balance' ? 'active' : '' ?>">NxL Balance</a>
      <a href="?sort=earned" class="filter-tab <?= $sort === 'earned' ? 'active' : '' ?>">Total Earned</a>
      <a href="?sort=referrals" class="filter-tab <?= $sort === 'referrals' ? 'active' : '' ?>">Referrals</a>
    </div>

    <?php if (count($leaders) >= 3): ?>
    <div class="row g-3 mb-4 leader-podium-row">
      <?php
      $podiumOrder = [1, 0, 2];
      $medals = ['🥇', '🥈', '🥉'];
      foreach ($podiumOrder as $idx => $pos):
          if (!isset($leaders[$pos])) {
              continue;
          }
          $L = $leaders[$pos];
          $isMe = (int) $L['id'] === $userId;
      ?>
      <div class="col-4">
        <div class="leader-podium-card rank-<?= $pos + 1 ?><?= $isMe ? ' is-me' : '' ?>">
          <div class="leader-podium-medal"><?= $medals[$idx] ?></div>
          <div class="leader-podium-avatar"><?= leaderInitials($L['full_name']) ?></div>
          <div class="leader-podium-name"><?= leaderDisplayName($L['full_name']) ?></div>
          <div class="leader-podium-score">
            <?= $sort === 'earned'
                ? number_format((float) $L['total_earned'], 0) . ' earned'
                : ($sort === 'referrals'
                    ? (int) $L['ref_count'] . ' referrals'
                    : number_format((float) $L['balance'], 0) . ' NxL') ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="section-card leader-table-card">
      <div class="section-card-title">
        <span><i class="fas fa-list-ol me-2" style="color:var(--cyber-green)"></i>Full Rankings</span>
        <span style="font-size:.8rem;color:var(--cyber-muted)"><?= count($leaders) ?> user registrations & trainees</span>
      </div>

      <?php if (empty($leaders)): ?>
      <div class="portal-empty">
        <i class="fas fa-trophy"></i>
        No rankings yet. Be the first to earn NxL tokens!
      </div>
      <?php else: ?>
      <div class="leader-table">
        <div class="leader-table-head">
          <span>Rank</span>
          <span>User Registration & Trainee</span>
          <span>NxL</span>
          <span>Earned</span>
          <span>Referrals</span>
        </div>
        <?php foreach ($leaders as $i => $L):
            $rank = $i + 1;
            $isMe = (int) $L['id'] === $userId;
        ?>
        <div class="leader-table-row<?= $isMe ? ' is-me' : '' ?>">
          <span class="leader-rank-num"><?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank - 1] : '#' . $rank ?></span>
          <span class="leader-student">
            <span class="leader-row-avatar"><?= leaderInitials($L['full_name']) ?></span>
            <?= leaderDisplayName($L['full_name']) ?>
            <?php if ($isMe): ?><span class="leader-you-badge">You</span><?php endif; ?>
          </span>
          <span class="leader-metric" style="color:var(--cyber-green)"><?= number_format((float) $L['balance'], 0) ?></span>
          <span class="leader-metric"><?= number_format((float) $L['total_earned'], 0) ?></span>
          <span class="leader-metric"><?= (int) $L['ref_count'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
