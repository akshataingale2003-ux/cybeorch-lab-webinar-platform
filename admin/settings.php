<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$adminUser = adminDb(
    fn () => db()->fetchOne('SELECT username, email, full_name, role, last_login, created_at FROM admin WHERE id = ?', [(int) ($_SESSION['admin_id'] ?? 0)]),
    null
);

$dbStatus = getDatabaseConnectionStatus();
$tableStats = $dbStatus['connected'] ? getSiteTableStats() : [];
$formLinks = getPublicFormAdminLinks();

renderAdminPageStart('Settings & Database', 'settings', 'fa-cog');
?>

<div class="section-card">
  <div class="section-card-header">Database connection</div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Site name</label><span><?= htmlspecialchars(SITE_NAME) ?></span></div>
      <div class="detail-item"><label>Site URL</label><span><?= htmlspecialchars(SITE_URL) ?></span></div>
      <div class="detail-item"><label>Database</label><span><?= htmlspecialchars(DB_NAME) ?> @ <?= htmlspecialchars(DB_HOST) ?></span></div>
      <div class="detail-item"><label>Status</label>
        <span><?= $dbStatus['connected']
            ? '<span class="badge-status badge-paid">Connected</span>'
            : '<span class="badge-status badge-failed">Offline</span>' ?></span>
      </div>
    </div>
    <p style="margin-top:1rem;font-size:.88rem;color:var(--cyber-muted)"><?= htmlspecialchars($dbStatus['message']) ?></p>
    <?php if (!$dbStatus['connected']): ?>
    <p style="margin-top:.75rem;font-size:.85rem;color:#ff8888">
      Start <strong>MySQL</strong> in XAMPP, then import <code>database.sql</code> into phpMyAdmin (database: <code><?= htmlspecialchars(DB_NAME) ?></code>).
    </p>
    <?php endif; ?>
  </div>
</div>

<?php if ($dbStatus['connected'] && $tableStats): ?>
<div class="section-card">
  <div class="section-card-header">Website data in database (admin pages)</div>
  <div class="section-card-body">
    <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      Every row below is stored in MySQL and managed from the linked admin screen.
    </p>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Data</th><th>Table</th><th>Records</th><th>Admin page</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tableStats as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['label']) ?></td>
            <td><code><?= htmlspecialchars($row['table']) ?></code></td>
            <td><?= (int) $row['count'] ?></td>
            <td><a href="<?= adminUrl($row['admin_page']) ?>" class="btn-sm-link">Open</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">Public forms → Admin</div>
  <div class="section-card-body">
    <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      Visitor forms on the website save to the database. View submissions under <strong>Messages</strong> or the linked admin page.
    </p>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Website form</th><th>Page</th><th>Stored as</th><th>View in admin</th></tr>
        </thead>
        <tbody>
          <?php foreach ($formLinks as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['form']) ?></td>
            <td><a href="<?= url($f['page']) ?>" class="btn-sm-link" target="_blank" rel="noopener"><?= htmlspecialchars($f['page']) ?></a></td>
            <td style="font-size:.82rem;color:var(--cyber-muted)"><?= htmlspecialchars($f['subject']) ?></td>
            <td>
              <?php if (str_contains($f['admin_filter'], 'admin/')): ?>
              <a href="<?= adminUrl($f['admin_filter']) ?>" class="btn-sm-link">Open</a>
              <?php else: ?>
              <a href="<?= adminUrl('admin/messages.php?subject=' . rawurlencode($f['admin_filter'])) ?>" class="btn-sm-link"><?= htmlspecialchars($f['admin_filter']) ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($adminUser): ?>
<div class="section-card">
  <div class="section-card-header">Your admin account</div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Username</label><span><?= htmlspecialchars($adminUser['username']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($adminUser['email']) ?></span></div>
      <div class="detail-item"><label>Name</label><span><?= htmlspecialchars($adminUser['full_name'] ?? '') ?></span></div>
      <div class="detail-item"><label>Role</label><span><?= htmlspecialchars($adminUser['role']) ?></span></div>
      <div class="detail-item"><label>Last login</label><span><?= $adminUser['last_login'] ? date('d M Y H:i', strtotime($adminUser['last_login'])) : '—' ?></span></div>
    </div>
    <p style="margin-top:1rem;font-size:0.85rem;color:var(--cyber-muted)">Admin login: <a href="<?= adminUrl('admin/login.php') ?>" class="btn-sm-link">admin/login.php</a></p>
  </div>
</div>
<?php endif; ?>

<?php renderAdminPageEnd(); ?>
