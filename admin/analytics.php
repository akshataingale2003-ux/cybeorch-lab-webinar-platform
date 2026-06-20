<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$stats = [
    'users'       => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM users')['c'] ?? 0, 0),
    'webinars'    => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM webinars')['c'] ?? 0, 0),
    'bootcamps'   => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM bootcamps')['c'] ?? 0, 0),
    'webinar_reg' => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM webinar_registrations')['c'] ?? 0, 0),
    'boot_reg'    => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM bootcamp_enrollments')['c'] ?? 0, 0),
    'payments'    => (float) adminDb(fn () => db()->fetchOne("SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status='paid'")['s'] ?? 0, 0),
    'freelancers' => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM freelancer_registrations')['c'] ?? 0, 0),
    'messages'    => (int) adminDb(fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM contact_messages')['c'] ?? 0, 0),
    'unread_msg'  => (int) adminDb(fn () => getUnreadContactMessageCount(), 0),
];

renderAdminPageStart('Analytics', 'analytics', 'fa-chart-line');
?>

<div class="row g-3">
  <?php
  $cards = [
      ['Users', $stats['users'], 'fa-users', 'var(--cyber-accent)'],
      ['Webinar regs', $stats['webinar_reg'], 'fa-video', 'var(--cyber-green)'],
      ['Bootcamp enrollments', $stats['boot_reg'], 'fa-graduation-cap', 'var(--cyber-orange)'],
      ['Revenue (paid)', '₹' . number_format((float) $stats['payments'], 2), 'fa-rupee-sign', 'var(--cyber-green)'],
      ['Freelancer apps', $stats['freelancers'], 'fa-user-tie', 'var(--cyber-accent)'],
      ['Form messages', $stats['messages'] . ' (' . $stats['unread_msg'] . ' unread)', 'fa-envelope', 'var(--cyber-orange)'],
  ];
  foreach ($cards as [$label, $val, $icon, $color]): ?>
  <div class="col-md-4">
    <div class="section-card"><div class="section-card-body text-center">
      <i class="fas <?= $icon ?>" style="font-size:1.5rem;color:<?= $color ?>;margin-bottom:0.5rem"></i>
      <div style="font-family:'Rajdhani',sans-serif;font-size:1.8rem;font-weight:700"><?= is_numeric($val) ? (int) $val : htmlspecialchars((string) $val) ?></div>
      <div style="font-size:0.82rem;color:var(--cyber-muted)"><?= htmlspecialchars($label) ?></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<?php renderAdminPageEnd(); ?>
