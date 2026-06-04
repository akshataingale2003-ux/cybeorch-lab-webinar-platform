<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
requireAdminLogin();

$adminWalletNotifs = fetchRecentAdminNotifications(10);
$adminNotifUnread = getAdminUnreadNotificationCount();

$totalUsers      = (int) dbTry(fn () => db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE ' . adminSqlActive())['c'] ?? 0, 0);
$totalWebinars   = (int) dbTry(fn () => db()->fetchOne('SELECT COUNT(*) as c FROM webinars WHERE ' . adminSqlActive())['c'] ?? 0, 0);
$totalPayments   = (float) dbTry(fn () => db()->fetchOne("SELECT COALESCE(SUM(amount),0) as s FROM payments WHERE status='paid' AND " . adminSqlActive())['s'] ?? 0, 0);
$totalRegis      = (int) dbTry(fn () => db()->fetchOne('SELECT COUNT(*) as c FROM webinar_registrations WHERE ' . adminSqlActive())['c'] ?? 0, 0);
$totalBootcamp   = (int) dbTry(fn () => db()->fetchOne('SELECT COUNT(*) as c FROM bootcamp_enrollments WHERE ' . adminSqlActive())['c'] ?? 0, 0);
$pendingPayments = (int) dbTry(fn () => db()->fetchOne("SELECT COUNT(*) as c FROM payments WHERE status='created' AND " . adminSqlActive())['c'] ?? 0, 0);
$todayUsers      = (int) dbTry(fn () => db()->fetchOne('SELECT COUNT(*) as c FROM users WHERE DATE(created_at)=CURDATE() AND ' . adminSqlActive())['c'] ?? 0, 0);
$todayRevenue    = (float) dbTry(fn () => db()->fetchOne("SELECT COALESCE(SUM(amount),0) as s FROM payments WHERE status='paid' AND DATE(paid_at)=CURDATE() AND " . adminSqlActive())['s'] ?? 0, 0);
$totalWallet     = (float) dbTry(fn () => db()->fetchOne('SELECT COALESCE(SUM(balance),0) as s FROM wallet WHERE ' . adminSqlActive())['s'] ?? 0, 0);
$totalMessages   = (int) dbTry(fn () => getUnreadContactMessageCount(), 0);
$blockedUsers    = (int) dbTry(fn () => adminBlockedUsersCount(), 0);
$dashSort        = adminParseListSortParam();
$dashOrder       = adminSortSqlDirection($dashSort);

$recentRegis = dbTry(
    fn () => db()->fetchAll(
        "SELECT wr.*, u.full_name, u.email, w.title as webinar_title, w.scheduled_at
         FROM webinar_registrations wr
         JOIN users u ON u.id=wr.user_id
         JOIN webinars w ON w.id=wr.webinar_id
         WHERE " . adminSqlActive('wr') . "
         ORDER BY wr.registered_at {$dashOrder} LIMIT 8"
    ),
    []
);

$recentPayments = dbTry(
    fn () => db()->fetchAll(
        'SELECT p.*, u.full_name, u.email FROM payments p JOIN users u ON u.id=p.user_id
         WHERE ' . adminSqlActive('p') . " ORDER BY p.created_at {$dashOrder} LIMIT 6"
    ),
    []
);

$monthlyRevenue = dbTry(
    fn () => db()->fetchAll(
        "SELECT DATE_FORMAT(paid_at,'%b') as month, SUM(amount) as revenue, COUNT(*) as count
         FROM payments WHERE status='paid' AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         AND " . adminSqlActive() . "
         GROUP BY DATE_FORMAT(paid_at,'%Y-%m'), DATE_FORMAT(paid_at,'%b')
         ORDER BY MIN(paid_at) ASC"
    ),
    []
);
$maxRev = max(array_column($monthlyRevenue, 'revenue') ?: [1]);

renderAdminPageStart('Admin Dashboard', 'dashboard', 'fa-th-large');
?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-blue:#0f3460;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-red:#ff4444;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);--sidebar-w:250px;}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.02) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.02) 1px,transparent 1px);background-size:60px 60px;pointer-events:none;}
.sidebar{width:var(--sidebar-w);height:100vh;background:var(--cyber-navy);border-right:1px solid var(--cyber-border);position:fixed;top:0;left:0;z-index:100;overflow-y:auto;display:flex;flex-direction:column;}
.sidebar-logo{padding:1.25rem 1.5rem;border-bottom:1px solid var(--cyber-border);}
.sidebar-logo-text{font-size:1.4rem;}
.admin-badge{font-size:0.65rem;background:rgba(255,107,53,0.2);border:1px solid rgba(255,107,53,0.4);color:var(--cyber-orange);padding:2px 8px;border-radius:4px;letter-spacing:1px;margin-top:4px;display:inline-block;}
.sidebar-nav{flex:1;padding:0.75rem 0;}
.nav-section{padding:0.75rem 1.25rem 0.25rem;font-size:0.68rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:2px;font-weight:600;}
.sidebar-link{display:flex;align-items:center;gap:0.75rem;padding:0.6rem 1.25rem;color:var(--cyber-muted);text-decoration:none;font-size:0.85rem;transition:all 0.2s;border-left:3px solid transparent;}
.sidebar-link i{width:18px;text-align:center;}
.sidebar-link:hover,.sidebar-link.active{color:var(--cyber-accent);background:rgba(0,212,255,0.06);border-left-color:var(--cyber-accent);}
.sidebar-link.danger:hover{color:var(--cyber-red);border-left-color:var(--cyber-red);}
.main{margin-left:var(--sidebar-w);min-height:100vh;position:relative;z-index:1;}
.topbar{background:rgba(10,22,40,0.9);border-bottom:1px solid var(--cyber-border);padding:0.85rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;backdrop-filter:blur(10px);}
.page-title{font-family:'Rajdhani',sans-serif;font-size:1.35rem;font-weight:700;display:flex;align-items:center;gap:0.5rem;}
.content{padding:1.5rem;}
.stat-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.25rem 1.5rem;transition:all 0.2s;position:relative;overflow:hidden;}
.stat-card:hover{border-color:var(--cyber-accent);transform:translateY(-2px);}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;opacity:0;transition:opacity 0.2s;}
.stat-card:hover::before{opacity:1;}
.stat-card.blue::before{background:linear-gradient(90deg,var(--cyber-accent),transparent);}
.stat-card.green::before{background:linear-gradient(90deg,var(--cyber-green),transparent);}
.stat-card.orange::before{background:linear-gradient(90deg,var(--cyber-orange),transparent);}
.stat-card.red::before{background:linear-gradient(90deg,var(--cyber-red),transparent);}
.stat-icon{width:46px;height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:0.75rem;}
.stat-value{font-family:'Rajdhani',sans-serif;font-size:2.2rem;font-weight:700;line-height:1;}
.stat-label{font-size:0.8rem;color:var(--cyber-muted);margin-top:0.3rem;}
.stat-sub{font-size:0.75rem;margin-top:0.4rem;}
.section-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;overflow:hidden;margin-bottom:1.5rem;}
.section-card-header{padding:1rem 1.5rem;border-bottom:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:space-between;background:rgba(0,0,0,0.15);}
.section-card-title{font-family:'Rajdhani',sans-serif;font-size:1.05rem;font-weight:600;display:flex;align-items:center;gap:0.5rem;}
.section-card-body{padding:0;}
.data-table{width:100%;border-collapse:collapse;}
.data-table th{padding:0.75rem 1.25rem;font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;color:var(--cyber-muted);font-weight:600;border-bottom:1px solid var(--cyber-border);text-align:left;white-space:nowrap;}
.data-table td{padding:0.85rem 1.25rem;font-size:0.85rem;border-bottom:1px solid rgba(0,212,255,0.06);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:rgba(0,212,255,0.03);}
.badge-status{font-size:0.7rem;padding:0.25rem 0.65rem;border-radius:4px;font-weight:600;}
.badge-paid{background:rgba(0,255,136,0.12);color:var(--cyber-green);border:1px solid rgba(0,255,136,0.25);}
.badge-pending{background:rgba(255,107,53,0.12);color:var(--cyber-orange);border:1px solid rgba(255,107,53,0.25);}
.badge-upcoming{background:rgba(0,212,255,0.12);color:var(--cyber-accent);border:1px solid rgba(0,212,255,0.25);}
.badge-live{background:rgba(255,0,0,0.15);color:#ff4444;border:1px solid rgba(255,0,0,0.3);}
.badge-completed{background:rgba(255,255,255,0.08);color:var(--cyber-muted);}
.badge-free{background:rgba(0,212,255,0.1);color:var(--cyber-accent);}
.btn-sm-cyber{font-size:0.75rem;padding:0.3rem 0.75rem;border-radius:5px;border:none;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:0.25rem;}
.btn-edit{background:rgba(0,212,255,0.12);color:var(--cyber-accent);border:1px solid rgba(0,212,255,0.25);}
.btn-edit:hover{background:var(--cyber-accent);color:var(--cyber-dark);}
.btn-view{background:rgba(0,255,136,0.1);color:var(--cyber-green);border:1px solid rgba(0,255,136,0.25);}
.btn-view:hover{background:var(--cyber-green);color:var(--cyber-dark);}
.btn-primary-cyber{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.6rem 1.25rem;border-radius:7px;font-weight:600;font-size:0.85rem;cursor:pointer;transition:all 0.2s;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;}
.btn-primary-cyber:hover{background:var(--cyber-green);transform:translateY(-1px);}
.chart-bar-container{padding:1.25rem 1.5rem;}
.chart-bar-row{display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;}
.chart-bar-label{font-size:0.78rem;color:var(--cyber-muted);width:80px;text-align:right;flex-shrink:0;}
.chart-bar-track{flex:1;background:rgba(255,255,255,0.06);border-radius:3px;height:8px;overflow:hidden;}
.chart-bar-fill{height:100%;border-radius:3px;transition:width 1s ease;}
.chart-bar-val{font-size:0.78rem;color:var(--cyber-text);width:40px;flex-shrink:0;}
.quick-action{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:10px;padding:1rem;text-align:center;text-decoration:none;color:var(--cyber-text);transition:all 0.2s;display:flex;flex-direction:column;align-items:center;gap:0.5rem;}
.quick-action:hover{border-color:var(--cyber-accent);color:var(--cyber-accent);transform:translateY(-2px);}
.quick-action i{font-size:1.5rem;}
.quick-action span{font-size:0.8rem;}
.revenue-big{font-family:'Rajdhani',sans-serif;font-size:2.8rem;font-weight:700;color:var(--cyber-green);}
.section-card-header{display:flex;align-items:center;justify-content:space-between;}
.badge-upcoming{background:rgba(0,212,255,0.12);color:var(--cyber-accent);}
.badge-live{background:rgba(255,0,0,0.15);color:#ff4444;}
.badge-completed{background:rgba(255,255,255,0.08);color:var(--cyber-muted);}
.badge-free{background:rgba(0,212,255,0.1);color:var(--cyber-accent);}
.dashboard-recent-row{align-items:stretch;}
.dashboard-recent-panel{display:flex;flex-direction:column;gap:1.5rem;width:100%;}
.dashboard-recent-panel .section-card{margin-bottom:0;width:100%;}
.dashboard-recent-panel .table-scroll{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
.dashboard-recent-panel .data-table{width:100%;min-width:100%;table-layout:auto;}
.dashboard-recent-panel .data-table th,.dashboard-recent-panel .data-table td{padding:0.85rem 1.35rem;}
.dashboard-recent-panel .col-webinar-cell{min-width:12rem;max-width:22rem;}
.dashboard-chart-col .section-card{height:100%;}
@media (min-width:1200px){
  .dashboard-recent-panel .data-table{min-width:680px;}
}
@media (max-width:1199.98px){
  .dashboard-chart-col{margin-bottom:0;}
}
</style>

<p style="font-size:0.82rem;color:var(--cyber-muted);margin-bottom:1.25rem"><?= date('D, d M Y • h:i A') ?></p>

    <?php if ($adminWalletNotifs): ?>
    <div class="section-card mb-4">
      <div class="section-card-header">
        <div class="section-card-title">
          <i class="fas fa-bell" style="color:var(--cyber-orange)"></i>
          Wallet activity
          <?php if ($adminNotifUnread > 0): ?>
          <span class="badge-status badge-pending" style="margin-left:.5rem"><?= (int) $adminNotifUnread ?> new</span>
          <?php endif; ?>
        </div>
        <a href="<?= adminUrl('admin/notifications.php') ?>" style="font-size:.78rem;color:var(--cyber-accent);text-decoration:none">View all →</a>
      </div>
      <div class="section-card-body" style="padding:0">
        <table class="data-table">
          <tbody>
            <?php foreach ($adminWalletNotifs as $an): ?>
            <tr>
              <td style="width:140px;color:var(--cyber-muted);font-size:.78rem"><?= date('d M Y, h:i A', strtotime((string) $an['created_at'])) ?></td>
              <td><?= htmlspecialchars((string) $an['message']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <a href="<?= adminUrl('webinars.php?action=add') ?>" class="quick-action">
          <i class="fas fa-plus-circle" style="color:var(--cyber-accent)"></i>
          <span>Add Webinar</span>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="<?= adminUrl('admin/bootcamps.php?action=add') ?>" class="quick-action">
          <i class="fas fa-graduation-cap" style="color:var(--cyber-green)"></i>
          <span>Add Bootcamp</span>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="<?= adminUrl('wallet.php?action=credit') ?>" class="quick-action">
          <i class="fas fa-coins" style="color:var(--cyber-orange)"></i>
          <span>Credit NxL</span>
        </a>
      </div>
      <div class="col-6 col-md-3">
        <a href="<?= adminUrl('admin/notifications.php?action=broadcast') ?>" class="quick-action">
          <i class="fas fa-broadcast-tower" style="color:#a855f7"></i>
          <span>Broadcast</span>
        </a>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-lg-3">
        <div class="stat-card blue">
          <div class="stat-icon" style="background:rgba(0,212,255,0.1);color:var(--cyber-accent)"><i class="fas fa-users"></i></div>
          <div class="stat-value"><?= number_format($totalUsers) ?></div>
          <div class="stat-label">Total User Registrations & Trainees</div>
          <div class="stat-sub" style="color:var(--cyber-green)"><i class="fas fa-user-plus me-1"></i>+<?= $todayUsers ?> today</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card green">
          <div class="stat-icon" style="background:rgba(0,255,136,0.1);color:var(--cyber-green)"><i class="fas fa-rupee-sign"></i></div>
          <div class="stat-value" style="font-size:1.6rem">₹<?= number_format($totalPayments) ?></div>
          <div class="stat-label">Total Revenue</div>
          <div class="stat-sub" style="color:var(--cyber-green)">+₹<?= number_format($todayRevenue) ?> today</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card orange">
          <div class="stat-icon" style="background:rgba(255,107,53,0.1);color:var(--cyber-orange)"><i class="fas fa-ticket-alt"></i></div>
          <div class="stat-value"><?= number_format($totalRegis) ?></div>
          <div class="stat-label">Webinar Registrations</div>
          <div class="stat-sub" style="color:var(--cyber-muted)"><?= $totalBootcamp ?> bootcamp enrollments</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card red">
          <div class="stat-icon" style="background:rgba(0,212,255,0.1);color:var(--cyber-accent)"><i class="fas fa-coins"></i></div>
          <div class="stat-value"><?= number_format($totalWallet) ?></div>
          <div class="stat-label">Total NxL Tokens Issued</div>
          <div class="stat-sub" style="color:var(--cyber-orange)"><?= $pendingPayments ?> pending payments</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card orange">
          <div class="stat-icon" style="background:rgba(255,107,53,0.15);color:var(--cyber-orange)"><i class="fas fa-user-slash"></i></div>
          <div class="stat-value"><?= number_format($blockedUsers) ?></div>
          <div class="stat-label">Blocked User Registrations & Trainees</div>
          <div class="stat-sub" style="color:var(--cyber-muted)">Cannot log in while blocked</div>
        </div>
      </div>
    </div>

    <div class="row g-4 dashboard-recent-row">
      <div class="col-12 col-xl-3 dashboard-chart-col">
        <div class="section-card">
          <div class="section-card-header">
            <div class="section-card-title"><i class="fas fa-chart-bar" style="color:var(--cyber-green)"></i>Monthly Revenue</div>
          </div>
          <div class="chart-bar-container">
            <?php if (empty($monthlyRevenue)): ?>
            <div style="text-align:center;padding:2rem;color:var(--cyber-muted);font-size:0.85rem">No revenue data yet.</div>
            <?php else: ?>
            <?php foreach ($monthlyRevenue as $m): ?>
            <div class="chart-bar-row">
              <div class="chart-bar-label"><?= $m['month'] ?></div>
              <div class="chart-bar-track">
                <div class="chart-bar-fill" style="width:<?= ($m['revenue']/$maxRev)*100 ?>%;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green))"></div>
              </div>
              <div class="chart-bar-val">₹<?= number_format($m['revenue']/1000,1) ?>K</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-9">
        <div class="dashboard-recent-panel">
          <?php renderAdminSortBar('admin/dashboard.php', $dashSort); ?>
          <div class="section-card">
            <div class="section-card-header">
              <div class="section-card-title"><i class="fas fa-ticket-alt" style="color:var(--cyber-orange)"></i>Recent Registrations</div>
              <a href="<?= adminUrl('registrations.php') ?>" style="font-size:0.78rem;color:var(--cyber-accent);text-decoration:none">View all →</a>
            </div>
            <div class="table-scroll">
              <table class="data-table">
                <thead><tr>
                  <th>User Registration</th><th>Webinar</th><th>Date</th><th>Status</th><th>Attended</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentRegis as $r): ?>
                <tr>
                  <td>
                    <div style="font-size:0.85rem;font-weight:500"><?= htmlspecialchars($r['full_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--cyber-muted)"><?= htmlspecialchars($r['email']) ?></div>
                  </td>
                  <td class="col-webinar-cell" style="font-size:0.82rem"><?= htmlspecialchars($r['webinar_title']) ?></td>
                  <td style="font-size:0.78rem;white-space:nowrap;color:var(--cyber-muted)"><?= date('d M', strtotime($r['registered_at'])) ?></td>
                  <td><span class="badge-status badge-<?= $r['payment_status'] === 'free' ? 'free' : $r['payment_status'] ?>"><?= strtoupper($r['payment_status']) ?></span></td>
                  <td>
                    <?php if ($r['attended']): ?>
                      <span style="color:var(--cyber-green);font-size:0.8rem"><i class="fas fa-check-circle"></i></span>
                    <?php else: ?>
                      <span style="color:var(--cyber-muted);font-size:0.8rem"><i class="fas fa-times-circle"></i></span>
                    <?php endif; ?>
                  </td>
                  <td><a href="<?= adminUrl('admin/registrations.php?tab=webinar') ?>" class="btn-sm-link">Open</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentRegis)): ?>
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--cyber-muted)">No registrations yet.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="section-card">
            <div class="section-card-header">
              <div class="section-card-title"><i class="fas fa-credit-card" style="color:var(--cyber-green)"></i>Recent Payments</div>
              <a href="<?= adminUrl('payments.php') ?>" style="font-size:0.78rem;color:var(--cyber-accent);text-decoration:none">View all →</a>
            </div>
            <div class="table-scroll">
              <table class="data-table">
                <thead><tr>
                  <th>User Registration</th><th>Amount</th><th>For</th><th>Invoice</th><th>Status</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($recentPayments as $p): ?>
                <tr>
                  <td>
                    <div style="font-size:0.85rem;font-weight:500"><?= htmlspecialchars($p['full_name']) ?></div>
                    <div style="font-size:0.72rem;color:var(--cyber-muted)"><?= date('d M, h:i A', strtotime($p['created_at'])) ?></div>
                  </td>
                  <td style="font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:700;color:var(--cyber-green);white-space:nowrap">₹<?= number_format($p['amount']) ?></td>
                  <td style="font-size:0.8rem;text-transform:capitalize"><?= str_replace('_',' ',$p['payment_for']) ?></td>
                  <td style="font-size:0.75rem;color:var(--cyber-muted)"><?= htmlspecialchars($p['invoice_no'] ?? '–') ?></td>
                  <td><span class="badge-status badge-<?= $p['status'] === 'paid' ? 'paid' : 'pending' ?>"><?= strtoupper($p['status']) ?></span></td>
                  <td><a href="<?= adminUrl('admin/payments.php?view=' . (int) $p['id']) ?>" class="btn-sm-link">View</a></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentPayments)): ?>
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--cyber-muted)">No payments yet.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

<?php renderAdminPageEnd(); ?>
