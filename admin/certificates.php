<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/certificate-system.php';

requireAdminLogin();
ensureCertificatesSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_missing_certificates'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/certificates.php', 'error', 'Invalid request.');
    }
    $synced = 0;
    $webinarRows = db()->fetchAll(
        "SELECT wr.id FROM webinar_registrations wr
         LEFT JOIN certificates c ON c.source_type = 'webinar_registration' AND c.source_id = wr.id
         WHERE wr.attended = 1 AND c.id IS NULL"
    );
    foreach ($webinarRows as $row) {
        $result = issueCertificateForWebinarRegistration((int) $row['id'], true);
        if ($result['success']) {
            $synced++;
        }
    }
    $bootcampRows = db()->fetchAll(
        "SELECT be.id FROM bootcamp_enrollments be
         LEFT JOIN certificates c ON c.source_type = 'bootcamp_enrollment' AND c.source_id = be.id
         WHERE be.completed = 1 AND c.id IS NULL"
    );
    foreach ($bootcampRows as $row) {
        $result = issueCertificateForBootcampEnrollment((int) $row['id'], true);
        if ($result['success']) {
            $synced++;
        }
    }
    adminFlashRedirect('admin/certificates.php?' . http_build_query($_GET), 'success', "Synced {$synced} missing certificate(s).");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/certificates.php', 'error', 'Invalid request.');
    }
    $certDbId = (int) ($_POST['certificate_db_id'] ?? 0);
    if (isset($_POST['regenerate_certificate'])) {
        $result = regenerateCertificate($certDbId);
        adminFlashRedirect('admin/certificates.php?' . http_build_query($_GET), $result['success'] ? 'success' : 'error', $result['message']);
    }
    if (isset($_POST['resend_certificate'])) {
        $result = resendCertificateEmail($certDbId);
        adminFlashRedirect('admin/certificates.php?' . http_build_query($_GET), $result['success'] ? 'success' : 'error', $result['message']);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = (string) ($_GET['type'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$result = searchCertificatesAdmin($search !== '' ? $search : null, $typeFilter !== '' ? $typeFilter : null, $perPage, $offset);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int) ceil($total / $perPage));

$flash = getFlash();
$queryExtra = array_filter([
    'q'    => $search !== '' ? $search : null,
    'type' => in_array($typeFilter, ['webinar', 'bootcamp'], true) ? $typeFilter : null,
]);

renderAdminPageStart('Certificates', 'certificates', 'fa-certificate');
?>
<?php if ($flash): ?><div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

<div class="section-card">
  <div class="section-card-header">Certificate Management</div>
  <div class="section-card-body">
    <div class="admin-toolbar">
      <form method="get" class="admin-search-form">
        <input type="text" name="q" class="form-control" placeholder="Search by Certificate ID or User Name" value="<?= htmlspecialchars($search) ?>">
        <select name="type" class="form-control" style="max-width:150px">
          <option value="">All types</option>
          <option value="webinar" <?= $typeFilter === 'webinar' ? 'selected' : '' ?>>Webinars</option>
          <option value="bootcamp" <?= $typeFilter === 'bootcamp' ? 'selected' : '' ?>>Bootcamps</option>
        </select>
        <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
      </form>
      <div style="font-size:.82rem;color:var(--cyber-muted)"><?= (int) $total ?> certificate(s)</div>
      <form method="post" onsubmit="return confirm('Generate certificates for all attended/completed registrations missing a certificate?');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
        <button type="submit" name="sync_missing_certificates" value="1" class="btn-primary-cyber"><i class="fas fa-sync"></i> Sync Missing</button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Certificate ID</th>
            <th>User</th>
            <th>Event</th>
            <th>Type</th>
            <th>Event Date</th>
            <th>Issue Date</th>
            <th>Status</th>
            <th>Email Status</th>
            <th>Email Sent At</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
          <tr><td colspan="10" style="color:var(--cyber-muted)">No certificates found.</td></tr>
          <?php else: foreach ($rows as $c):
            $emailMeta = certificateEmailStatusMeta($c);
          ?>
          <tr>
            <td><code style="font-size:.78rem"><?= htmlspecialchars((string) $c['certificate_id']) ?></code></td>
            <td>
              <?= htmlspecialchars((string) $c['user_name']) ?>
              <?php if (!empty($c['user_email'])): ?>
              <br><span style="font-size:.75rem;color:var(--cyber-muted)"><?= htmlspecialchars((string) $c['user_email']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) $c['event_name']) ?></td>
            <td><span class="badge-status badge-paid"><?= htmlspecialchars(ucfirst((string) $c['event_type'])) ?></span></td>
            <td><?= htmlspecialchars(certificateFormatDate((string) $c['event_date'])) ?></td>
            <td><?= htmlspecialchars(certificateFormatDate((string) $c['issue_date'])) ?></td>
            <td>
              <?php if (($c['status'] ?? 'valid') === 'valid'): ?>
              <span class="badge-status badge-paid">Valid</span>
              <?php else: ?>
              <span class="badge-status badge-pending"><?= htmlspecialchars(ucfirst((string) ($c['status'] ?? 'invalid'))) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-status <?= htmlspecialchars($emailMeta['class']) ?>"><?= htmlspecialchars($emailMeta['label']) ?></span>
              <?php if ($emailMeta['error']): ?>
              <br><span style="font-size:.72rem;color:#ff8888" title="<?= htmlspecialchars($emailMeta['error']) ?>"><?= htmlspecialchars(mb_strimwidth($emailMeta['error'], 0, 48, '…')) ?></span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--cyber-muted)">
              <?= $emailMeta['sent_at'] ? htmlspecialchars(date('d M Y, h:i A', strtotime($emailMeta['sent_at']))) : '—' ?>
            </td>
            <td>
              <div class="admin-action-btns" style="display:flex;flex-wrap:wrap;gap:.35rem">
                <a href="<?= url('api/certificate-action.php?action=view&amp;format=pdf&amp;id=' . rawurlencode((string) $c['certificate_id'])) ?>" class="btn-sm-cyber btn-view" title="View" target="_blank" rel="noopener"><i class="fas fa-eye"></i></a>
                <a href="<?= url('api/certificate-action.php?action=download&amp;format=pdf&amp;id=' . rawurlencode((string) $c['certificate_id'])) ?>" class="btn-sm-cyber btn-view" title="Download PDF"><i class="fas fa-file-pdf"></i></a>
                <a href="<?= url('api/certificate-action.php?action=download&amp;format=png&amp;id=' . rawurlencode((string) $c['certificate_id'])) ?>" class="btn-sm-cyber btn-view" title="Download PNG"><i class="fas fa-image"></i></a>
                <form method="post" style="display:inline" onsubmit="return confirm('Regenerate certificate files?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                  <input type="hidden" name="certificate_db_id" value="<?= (int) $c['id'] ?>">
                  <button type="submit" name="regenerate_certificate" value="1" class="btn-sm-cyber btn-edit" title="Regenerate"><i class="fas fa-sync"></i></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Resend certificate email to the user?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                  <input type="hidden" name="certificate_db_id" value="<?= (int) $c['id'] ?>">
                  <button type="submit" name="resend_certificate" value="1" class="btn-sm-cyber btn-view" title="Resend Certificate Email"><i class="fas fa-envelope"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="admin-pagination">
      <span>Page <?= $page ?> of <?= $totalPages ?></span>
      <div class="page-links">
        <?php if ($page > 1): ?>
        <a href="<?= adminUrl('admin/certificates.php?' . http_build_query(array_merge($queryExtra, ['page' => $page - 1]))) ?>">&laquo; Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?= adminUrl('admin/certificates.php?' . http_build_query(array_merge($queryExtra, ['page' => $page + 1]))) ?>">Next &raquo;</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
