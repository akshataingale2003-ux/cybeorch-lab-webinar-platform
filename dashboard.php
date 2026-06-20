<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';
require_once __DIR__ . '/includes/nxl-wallet.php';
require_once __DIR__ . '/includes/certificate-system.php';

$ctx = studentContext();
extract($ctx);
$userId = $ctx['userId'];
$walletSummary = getWalletSummaryForUser($userId);
$balance = $walletSummary['balance'];

ensureCertificatesSchema();
syncMissingCertificatesForUser($userId);
$userCertificates = listCertificatesForUser($userId);
$certificateCount = count($userCertificates);

$webinarCount  = db()->fetchOne('SELECT COUNT(*) as c FROM webinar_registrations WHERE user_id = ?', [$userId])['c'];
$bootcampCount = db()->fetchOne('SELECT COUNT(*) as c FROM bootcamp_enrollments WHERE user_id = ?', [$userId])['c'];
$attendCount   = db()->fetchOne('SELECT COUNT(*) as c FROM webinar_registrations WHERE user_id = ? AND attended = 1', [$userId])['c'];
$referralCount = db()->fetchOne('SELECT COUNT(*) as c FROM referrals WHERE referrer_id = ? AND status = ?', [$userId, 'rewarded'])['c'];
$upcomingWebinars = db()->fetchAll(
    "SELECT wr.*, w.title, w.scheduled_at, w.duration_mins, w.meeting_platform, wr.payment_status
     FROM webinar_registrations wr
     JOIN webinars w ON w.id = wr.webinar_id
     WHERE wr.user_id = ? AND w.status IN ('upcoming','live')
     ORDER BY w.scheduled_at ASC LIMIT 5",
    [$userId]
);

$transactions = db()->fetchAll(
    'SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 8',
    [$userId]
);

$notifications = db()->fetchAll(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
    [$userId]
);

$welcome = isset($_GET['welcome']);
$firstName = htmlspecialchars(explode(' ', $user['full_name'])[0]);

renderStudentHead('Dashboard');
renderStudentSidebar('dashboard', $ctx);
?>
<main class="main">
  <?php renderPortalTopbar('Dashboard', '<div class="topbar-actions">
      <a href="' . url('webinars.php') . '" class="topbar-link">
        <i class="fas fa-plus me-1"></i>Register Webinar
      </a>
      <button type="button" class="notif-btn" aria-label="Notifications">
        <i class="fas fa-bell"></i>' . ($unreadNotifs > 0 ? '<span class="notif-dot" aria-hidden="true"></span>' : '') . '
      </button>
    </div>'); ?>

  <div class="content">
    <?php if ($welcome): ?>
    <div class="welcome-banner">
      <h3 style="font-family:'Rajdhani',sans-serif;font-size:1.45rem;font-weight:700">Welcome to CYBEORCH LABS, <?= $firstName ?>!</h3>
      <p style="color:var(--cyber-muted);font-size:.88rem;margin:0">You've received <strong style="color:var(--cyber-green)"><?= NXL_SIGNUP_BONUS ?> NxL tokens</strong> as a welcome bonus. Start exploring our webinars and bootcamps!</p>
    </div>
    <?php endif; ?>

    <div class="row g-3 dashboard-stats">
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(0,212,255,0.1);color:var(--cyber-accent)"><i class="fas fa-video"></i></div>
          <div class="stat-value"><?= (int) $webinarCount ?></div>
          <div class="stat-label">Webinars Joined</div>
          <div class="stat-change up"><i class="fas fa-arrow-up me-1"></i>Keep learning!</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(0,255,136,0.1);color:var(--cyber-green)"><i class="fas fa-graduation-cap"></i></div>
          <div class="stat-value"><?= (int) $bootcampCount ?></div>
          <div class="stat-label">Bootcamps Enrolled</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(255,107,53,0.1);color:var(--cyber-orange)"><i class="fas fa-check-double"></i></div>
          <div class="stat-value"><?= (int) $attendCount ?></div>
          <div class="stat-label">Sessions Attended</div>
        </div>
      </div>
      <div class="col-6 col-lg-3">
        <div class="stat-card">
          <div class="stat-icon" style="background:rgba(0,255,136,0.1);color:var(--cyber-green)"><i class="fas fa-coins"></i></div>
          <div class="stat-value" style="color:var(--cyber-green);font-size:clamp(1.25rem,3.5vw,1.85rem)"><?= formatNxlCredits($walletSummary['balance']) ?></div>
          <div class="stat-label"><?= htmlspecialchars(nxlWalletDisplayName()) ?></div>
          <div class="stat-change up" style="font-size:.75rem;color:var(--cyber-muted)">
            INR Equivalent: <strong style="color:var(--cyber-text)"><?= formatNxlInrEquivalent($walletSummary['balance']) ?></strong>
          </div>
          <div class="stat-change up" style="font-size:.72rem;color:var(--cyber-muted)">
            Earned <?= number_format($walletSummary['total_earned']) ?> · Spent <?= number_format($walletSummary['total_spent']) ?>
          </div>
          <?php if ($walletSummary['referrals_made'] > 0): ?>
          <div class="stat-change up"><i class="fas fa-users me-1"></i><?= (int) $walletSummary['referrals_made'] ?> referrals · <?= number_format($walletSummary['referral_earnings']) ?> credits</div>
          <?php endif; ?>
          <a href="<?= url('wallet.php') ?>" style="font-size:.75rem;color:var(--cyber-accent);text-decoration:none;display:inline-block;margin-top:.35rem">View wallet →</a>
        </div>
      </div>
    </div>

    <div class="row g-3 g-lg-4 dashboard-grid">
      <div class="col-lg-7">
        <div class="section-card">
          <div class="section-card-title">
            <span><i class="fas fa-calendar-alt me-2" style="color:var(--cyber-accent)"></i>My Upcoming Webinars</span>
            <a href="<?= url('webinars.php') ?>">Browse all →</a>
          </div>
          <?php if (empty($upcomingWebinars)): ?>
          <div class="portal-empty">
            <i class="fas fa-video"></i>
            No upcoming webinars registered.<br>
            <a href="<?= url('webinars.php') ?>" style="color:var(--cyber-accent);font-size:.85rem">Browse and register for free webinars →</a>
          </div>
          <?php else: ?>
          <?php foreach ($upcomingWebinars as $w): ?>
          <div class="item-card">
            <div class="item-date">
              <div class="item-date-day"><?= date('d', strtotime($w['scheduled_at'])) ?></div>
              <div class="item-date-mon"><?= date('M', strtotime($w['scheduled_at'])) ?></div>
            </div>
            <div class="flex-1">
              <div class="item-title"><?= htmlspecialchars($w['title']) ?></div>
              <div class="item-meta">
                <i class="far fa-clock me-1"></i><?= date('h:i A', strtotime($w['scheduled_at'])) ?>
                <span class="ms-2"><i class="fas fa-hourglass-half me-1"></i><?= (int) $w['duration_mins'] ?> min</span>
                <span class="ms-2 text-capitalize"><i class="fas fa-video me-1"></i><?= htmlspecialchars($w['meeting_platform']) ?></span>
              </div>
            </div>
            <span class="item-status <?= $w['payment_status'] === 'free' ? 'status-free' : ($w['payment_status'] === 'paid' ? 'status-paid' : 'status-pending') ?>">
              <?= strtoupper((string) $w['payment_status']) ?>
            </span>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="section-card">
          <div class="section-card-title">
            <span><i class="fas fa-certificate me-2" style="color:var(--cyber-accent)"></i>My Certificates</span>
            <a href="<?= url('my-certificates.php') ?>">View all →</a>
          </div>
          <?php if (empty($userCertificates)): ?>
          <div class="portal-empty" style="padding:1.25rem">
            <i class="fas fa-certificate"></i>
            No certificates yet. Certificates appear here after webinar attendance or bootcamp completion is confirmed.
          </div>
          <?php else: ?>
          <?php foreach (array_slice($userCertificates, 0, 3) as $cert):
            $emailMeta = certificateEmailStatusMeta($cert);
          ?>
          <div class="item-card cert-dashboard-card">
            <div class="flex-1">
              <div class="item-title"><?= htmlspecialchars((string) $cert['event_name']) ?></div>
              <div class="item-meta">
                <span><i class="fas fa-hashtag me-1"></i><?= htmlspecialchars((string) $cert['certificate_id']) ?></span>
                <span class="ms-2"><i class="fas fa-tag me-1"></i><?= htmlspecialchars(ucfirst((string) $cert['event_type'])) ?></span>
                <span class="ms-2"><i class="far fa-calendar me-1"></i>Issued <?= htmlspecialchars(certificateFormatDate((string) $cert['issue_date'])) ?></span>
                <span class="ms-2"><i class="fas fa-check-circle me-1"></i>Certificate Generated</span>
                <span class="ms-2">
                  <i class="fas fa-envelope me-1"></i>
                  <?php if ($emailMeta['status'] === 'sent'): ?>
                  <span style="color:var(--cyber-green)">Email Sent Successfully</span>
                  <?php elseif ($emailMeta['status'] === 'failed'): ?>
                  <span style="color:#ff8888">Email Pending Retry</span>
                  <?php else: ?>
                  <span style="color:var(--cyber-muted)">Email Pending</span>
                  <?php endif; ?>
                </span>
              </div>
            </div>
            <div class="cert-action-btns">
              <a href="<?= certificateDownloadUrl((string) $cert['certificate_id'], 'pdf', true) ?>" class="btn-cyber btn-cyber-sm" target="_blank" rel="noopener"><i class="fas fa-eye me-1"></i>View</a>
              <a href="<?= certificateDownloadUrl((string) $cert['certificate_id'], 'pdf') ?>" class="btn-cyber btn-cyber-sm btn-cyber-outline"><i class="fas fa-file-pdf me-1"></i>PDF</a>
              <a href="<?= certificateDownloadUrl((string) $cert['certificate_id'], 'png') ?>" class="btn-cyber btn-cyber-sm btn-cyber-outline"><i class="fas fa-image me-1"></i>PNG</a>
              <a href="<?= certificateDownloadUrl((string) $cert['certificate_id'], 'jpg') ?>" class="btn-cyber btn-cyber-sm btn-cyber-outline"><i class="fas fa-image me-1"></i>JPG</a>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ($certificateCount > 3): ?>
          <p style="font-size:.82rem;color:var(--cyber-muted);margin:.75rem 0 0">Showing 3 of <?= (int) $certificateCount ?> certificates.</p>
          <?php endif; ?>
          <?php endif; ?>
        </div>

        <div class="section-card" style="background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(0,255,136,0.04));">
          <div class="section-card-title">
            <span><i class="fas fa-users me-2" style="color:var(--cyber-green)"></i>Refer &amp; Earn</span>
          </div>
          <p style="color:var(--cyber-muted);font-size:.88rem;margin:0 0 1rem">Share your referral link and earn <strong style="color:var(--cyber-green)"><?= NXL_REFERRAL_BONUS ?> NxL tokens</strong> for every friend who joins!</p>
          <div class="referral-input-row">
            <input type="text" class="form-control" value="<?= htmlspecialchars(referralShareUrl((string) $user['referral_code'])) ?>" id="refLink" readonly>
            <button type="button" onclick="copyRef()" class="btn-cyber">
              <i class="fas fa-copy me-1"></i>Copy
            </button>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="section-card">
          <div class="section-card-title">
            <span><i class="fas fa-coins me-2" style="color:var(--cyber-green)"></i>NxL Token History</span>
            <a href="<?= url('wallet.php') ?>">See all →</a>
          </div>
          <?php if (empty($transactions)): ?>
          <div class="portal-empty" style="padding:1.25rem">No transactions yet. Earn tokens by attending webinars!</div>
          <?php else: ?>
          <?php foreach ($transactions as $tx): ?>
          <div class="tx-row">
            <div class="tx-icon <?= $tx['type'] === 'credit' ? 'tx-credit' : 'tx-debit' ?>">
              <i class="fas fa-<?= $tx['type'] === 'credit' ? 'arrow-down' : 'arrow-up' ?>"></i>
            </div>
            <div class="tx-desc">
              <div class="tx-title"><?= htmlspecialchars(
                  (string) ($tx['remarks'] ?? $tx['description'] ?? nxlRewardLabel((string) $tx['reason']))
              ) ?></div>
              <div class="tx-date"><?= timeAgo($tx['created_at']) ?></div>
            </div>
            <div class="tx-amount <?= htmlspecialchars((string) $tx['type']) ?>">
              <?= $tx['type'] === 'credit' ? '+' : '-' ?><?= number_format((float) $tx['amount'], 0) ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="section-card">
          <div class="section-card-title">
            <span><i class="fas fa-bell me-2" style="color:var(--cyber-orange)"></i>Notifications</span>
            <a href="<?= url('notifications.php') ?>">View all →</a>
          </div>
          <?php if (empty($notifications)): ?>
          <div class="portal-empty" style="padding:1rem">No notifications yet.</div>
          <?php else: ?>
          <?php foreach ($notifications as $notif): ?>
          <div class="notif-item">
            <div class="notif-dot-wrap <?= $notif['is_read'] ? 'read' : 'unread' ?>"></div>
            <div>
              <div class="notif-title" style="font-weight:<?= $notif['is_read'] ? '400' : '600' ?>"><?= htmlspecialchars($notif['title']) ?></div>
              <div class="notif-time"><?= timeAgo($notif['created_at']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
function copyRef() {
  var inp = document.getElementById('refLink');
  if (!inp) return;
  inp.select();
  if (navigator.clipboard) {
    navigator.clipboard.writeText(inp.value).catch(function () { document.execCommand('copy'); });
  } else {
    document.execCommand('copy');
  }
  var btn = inp.parentElement && inp.parentElement.querySelector('button');
  if (!btn) return;
  var orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
  btn.style.background = 'var(--cyber-green)';
  setTimeout(function () { btn.innerHTML = orig; btn.style.background = ''; }, 2000);
}
</script>
<?php renderStudentLayoutEnd(); ?>
