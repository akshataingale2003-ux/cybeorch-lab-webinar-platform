<?php
declare(strict_types=1);

function studentContext(): array
{
    startSession();
    requireLogin();
    $userId = (int) $_SESSION['user_id'];
    $user   = db()->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
    $wallet = db()->fetchOne('SELECT * FROM wallet WHERE user_id = ?', [$userId]);
    if (function_exists('getWalletSummaryForUser')) {
        require_once __DIR__ . '/nxl-wallet.php';
        $summary = getWalletSummaryForUser($userId);
        $balance = $summary['balance'];
    } else {
        $balance = $wallet ? (float) $wallet['balance'] : 0.0;
    }
    $unreadNotifs = (int) db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])['c'];
    $initials = strtoupper(substr($user['full_name'], 0, 1));
    if (str_contains($user['full_name'], ' ')) {
        $initials .= strtoupper(substr($user['full_name'], (int) strpos($user['full_name'], ' ') + 1, 1));
    }
    return compact('userId', 'user', 'wallet', 'balance', 'unreadNotifs', 'initials');
}

function renderStudentHead(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($title) ?> – <?= htmlspecialchars(SITE_NAME) ?></title>
<?php renderPortalPageHead(); ?>
</head>
<body class="portal-page">
    <?php
}

function renderStudentSidebar(string $active, array $ctx): void
{
    extract($ctx);
    $links = [
        'dashboard'     => ['dashboard.php', 'fa-th-large', 'Dashboard'],
        'webinars'      => ['my-webinars.php', 'fa-video', 'Webinars'],
        'bootcamps'     => ['my-bootcamps.php', 'fa-graduation-cap', 'Bootcamps'],
        'registrations' => ['my-registrations.php', 'fa-ticket-alt', 'My Registrations'],
        'wallet'        => ['wallet.php', 'fa-coins', 'NxL Wallet'],
        'referral'      => ['referral.php', 'fa-users', 'Refer & Earn'],
        'leadership'    => ['leadership.php', 'fa-trophy', 'Leadership'],
        'notifications' => ['notifications.php', 'fa-bell', 'Notifications'],
        'profile'       => ['profile.php', 'fa-user-cog', 'Profile'],
        'payments'      => ['payment-history.php', 'fa-receipt', 'Payment History'],
    ];
    ?>
<aside class="sidebar" id="portalSidebar">
  <div class="sidebar-logo">
    <a href="<?= url('dashboard.php') ?>" class="sidebar-logo-text"><?= brandMark() ?></a>
    <div class="portal-trainee-badge">User Registration &amp; Trainee Portal</div>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
    <div>
      <div class="user-info-name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div class="user-info-email"><?= htmlspecialchars($user['email']) ?></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Main</div>
    <?php foreach ($links as $key => [$path, $icon, $label]): ?>
    <a href="<?= url($path) ?>" class="sidebar-link<?= $active === $key ? ' active' : '' ?>">
      <i class="fas <?= $icon ?>"></i><?= htmlspecialchars($label) ?>
      <?php if ($key === 'notifications' && $unreadNotifs > 0): ?><span class="badge-count"><?= $unreadNotifs ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <a href="<?= url('logout.php') ?>" class="sidebar-link" style="color:#ff8888"><i class="fas fa-sign-out-alt"></i>Logout</a>
  </nav>
  <div class="wallet-widget">
    <div style="font-size:.75rem;color:var(--cyber-muted)"><i class="fas fa-coins me-1"></i>NxL Balance</div>
    <div class="wallet-balance"><?= number_format($balance, 0) ?> <span style="font-size:.85rem;font-weight:400;color:var(--cyber-muted)">NxL</span></div>
    <a href="<?= url('wallet.php') ?>" style="font-size:.78rem;color:var(--cyber-accent);text-decoration:none">View History →</a>
  </div>
</aside>
    <?php
}

function renderStudentLayoutEnd(): void
{
    if (function_exists('renderNxlRewardPopups')) {
        require_once __DIR__ . '/nxl-wallet.php';
        renderNxlRewardPopups();
    }
    renderStudentPortalNavScript();
    renderSiteScripts(false);
    echo '</body></html>';
}
