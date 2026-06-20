<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
require_once __DIR__ . '/../includes/certificate-system.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_webinar_attended'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/registrations.php?tab=webinar', 'error', 'Invalid request.');
    }
    $regId = (int) ($_POST['registration_id'] ?? 0);
    $result = markWebinarRegistrationAttended($regId, (int) ($_SESSION['admin_id'] ?? 0));
    $flash = !empty($result['certificate_error']) || !$result['success'] ? 'error' : 'success';
    adminFlashRedirect('admin/registrations.php?tab=webinar', $flash, $result['message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_webinar_certificate'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/registrations.php?tab=webinar', 'error', 'Invalid request.');
    }
    $regId = (int) ($_POST['registration_id'] ?? 0);
    $result = issueCertificateForWebinarRegistration($regId, true);
    adminFlashRedirect(
        'admin/registrations.php?tab=webinar',
        $result['success'] ? 'success' : 'error',
        $result['success']
            ? ('Certificate issued: ' . ($result['certificate']['certificate_id'] ?? '') . (!empty($result['email_sent']) ? ' (emailed)' : ''))
            : ($result['message'] ?? 'Certificate generation failed.')
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_bootcamp_completed'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/registrations.php?tab=bootcamp', 'error', 'Invalid request.');
    }
    $enrollId = (int) ($_POST['enrollment_id'] ?? 0);
    $result = markBootcampEnrollmentCompleted($enrollId);
    $flash = !empty($result['certificate_error']) || !$result['success'] ? 'error' : 'success';
    adminFlashRedirect('admin/registrations.php?tab=bootcamp', $flash, $result['message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_bootcamp_certificate'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        adminFlashRedirect('admin/registrations.php?tab=bootcamp', 'error', 'Invalid request.');
    }
    $enrollId = (int) ($_POST['enrollment_id'] ?? 0);
    $result = issueCertificateForBootcampEnrollment($enrollId, true);
    adminFlashRedirect(
        'admin/registrations.php?tab=bootcamp',
        $result['success'] ? 'success' : 'error',
        $result['success']
            ? ('Certificate issued: ' . ($result['certificate']['certificate_id'] ?? '') . (!empty($result['email_sent']) ? ' (emailed)' : ''))
            : ($result['message'] ?? 'Certificate generation failed.')
    );
}

$tab = ($_GET['tab'] ?? 'webinar') === 'bootcamp' ? 'bootcamp' : 'webinar';
$sort = adminParseListSortParam();
$order = adminSortSqlDirection($sort);
$tabExtra = ['tab' => $tab];

$webinarRegs = adminDb(
    fn () => db()->fetchAll(
        "SELECT wr.*, u.full_name, u.email, w.title AS item_title, w.scheduled_at,
                c.certificate_id, c.email_sent AS cert_email_sent, c.pdf_path AS cert_pdf_path
         FROM webinar_registrations wr
         JOIN users u ON u.id = wr.user_id
         JOIN webinars w ON w.id = wr.webinar_id
         LEFT JOIN certificates c ON c.source_type = 'webinar_registration' AND c.source_id = wr.id
         WHERE " . adminSqlActive('wr') . "
         ORDER BY wr.registered_at {$order} LIMIT 150"
    ),
    []
);

$bootcampRegs = adminDb(
    fn () => db()->fetchAll(
        "SELECT be.*, u.full_name, u.email, b.title AS item_title, b.start_date, b.end_date,
                c.certificate_id, c.email_sent AS cert_email_sent, c.pdf_path AS cert_pdf_path
         FROM bootcamp_enrollments be
         JOIN users u ON u.id = be.user_id
         JOIN bootcamps b ON b.id = be.bootcamp_id
         LEFT JOIN certificates c ON c.source_type = 'bootcamp_enrollment' AND c.source_id = be.id
         WHERE " . adminSqlActive('be') . "
         ORDER BY be.enrolled_at {$order} LIMIT 150"
    ),
    []
);

$flash = getFlash();

renderAdminPageStart('Registrations', 'registrations', 'fa-ticket-alt');
?>
<?php if ($flash): ?><div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

<div class="section-card">
  <div class="section-card-header">Course registrations</div>
  <div class="section-card-body">
    <?php renderAdminSortBar('admin/registrations.php', $sort, $tabExtra); ?>
    <div class="filter-bar">
      <a href="<?= adminUrl('admin/registrations.php?' . http_build_query(array_merge($tabExtra, ['sort' => $sort, 'tab' => 'webinar']))) ?>" class="<?= $tab === 'webinar' ? 'active' : '' ?>">Webinars (<?= count($webinarRegs) ?>)</a>
      <a href="<?= adminUrl('admin/registrations.php?' . http_build_query(array_merge($tabExtra, ['sort' => $sort, 'tab' => 'bootcamp']))) ?>" class="<?= $tab === 'bootcamp' ? 'active' : '' ?>">Bootcamps (<?= count($bootcampRegs) ?>)</a>
    </div>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date</th><th>Reg. No</th><th>User Registration &amp; Trainee</th><th><?= $tab === 'webinar' ? 'Webinar' : 'Bootcamp' ?></th><th>Payment</th>
            <?php if ($tab === 'webinar'): ?><th>Attendance</th><?php else: ?><th>Completion</th><?php endif; ?>
            <th>Certificate</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rows = $tab === 'webinar' ? $webinarRegs : $bootcampRegs;
          if (!$rows): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No registrations yet.</td></tr>
          <?php else: foreach ($rows as $r):
            $dateCol = $tab === 'webinar' ? $r['registered_at'] : $r['enrolled_at'];
            $regNo = $tab === 'webinar' ? $r['registration_no'] : $r['enrollment_no'];
            $entity = $tab === 'webinar' ? 'webinar_registration' : 'bootcamp_enrollment';
            $blocked = adminRecordIsBlocked($r);
          ?>
          <tr<?= renderAdminRecordRowAttrs($entity, (int) $r['id'], $blocked) ?>>
            <td><?= date('d M Y', strtotime($dateCol)) ?></td>
            <td><?= htmlspecialchars($regNo) ?></td>
            <td><?= htmlspecialchars($r['full_name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($r['email']) ?></span></td>
            <td><?= htmlspecialchars($r['item_title']) ?></td>
            <td><span class="badge-status badge-<?= ($r['payment_status'] ?? '') === 'paid' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($r['payment_status']) ?></span></td>
            <?php if ($tab === 'webinar'): ?>
            <td>
              <?php if (!empty($r['attended'])): ?>
              <span class="badge-status badge-paid">Attended</span>
              <?php else: ?>
              <form method="post" class="js-mark-webinar-attended" style="display:inline" data-registration-id="<?= (int) $r['id'] ?>" onsubmit="return confirm('Mark attended and credit <?= (int) NXL_WEBINAR_REWARD ?> NxL?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                <input type="hidden" name="mark_webinar_attended" value="1">
                <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="btn-sm-cyber btn-view js-mark-attended-btn" style="font-size:.72rem">Mark attended</button>
              </form>
              <?php endif; ?>
            </td>
            <?php else: ?>
            <td>
              <?php if (!empty($r['completed'])): ?>
              <span class="badge-status badge-paid">Completed</span>
              <?php else: ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Mark bootcamp complete?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                <input type="hidden" name="mark_bootcamp_completed" value="1">
                <input type="hidden" name="enrollment_id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="btn-sm-cyber btn-view" style="font-size:.72rem">Mark complete</button>
              </form>
              <?php endif; ?>
            </td>
            <?php endif; ?>
            <td>
              <?php if (!empty($r['certificate_id'])): ?>
              <a href="<?= url('api/certificate-action.php?action=download&amp;format=pdf&amp;id=' . rawurlencode((string) $r['certificate_id'])) ?>" class="btn-sm-cyber btn-view" style="font-size:.72rem" title="Download certificate">
                <i class="fas fa-certificate"></i> <?= htmlspecialchars((string) $r['certificate_id']) ?>
              </a>
              <br><span style="font-size:.72rem;color:var(--cyber-muted)"><?= !empty($r['cert_email_sent']) ? 'Emailed' : 'Email pending' ?></span>
              <?php elseif (($tab === 'webinar' && !empty($r['attended'])) || ($tab === 'bootcamp' && !empty($r['completed']))): ?>
              <span style="font-size:.72rem;color:var(--cyber-orange)">Not generated</span>
              <form method="post" style="display:inline;margin-left:.35rem">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                <?php if ($tab === 'webinar'): ?>
                <button type="submit" name="issue_webinar_certificate" value="1" class="btn-sm-cyber btn-edit" style="font-size:.72rem" title="Generate certificate now">Generate</button>
                <?php else: ?>
                <input type="hidden" name="enrollment_id" value="<?= (int) $r['id'] ?>">
                <button type="submit" name="issue_bootcamp_certificate" value="1" class="btn-sm-cyber btn-edit" style="font-size:.72rem" title="Generate certificate now">Generate</button>
                <?php endif; ?>
              </form>
              <?php else: ?>
              <span style="font-size:.72rem;color:var(--cyber-muted)">—</span>
              <?php endif; ?>
            </td>
            <td><?php renderAdminRecordActions($entity, (int) $r['id'], $blocked); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  const apiUrl = <?= json_encode(url('api/mark-webinar-attendance.php'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  document.querySelectorAll('.js-mark-webinar-attended').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.fetch) return;
      e.preventDefault();
      if (!confirm('Mark attended and credit <?= (int) NXL_WEBINAR_REWARD ?> NxL?')) return;

      const btn = form.querySelector('.js-mark-attended-btn');
      const cell = form.closest('td');
      const original = btn ? btn.textContent : '';
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving…';
      }

      const body = new FormData(form);
      fetch(apiUrl, {
        method: 'POST',
        body: body,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) {
            alert(data.message || 'Could not mark attendance.');
            if (btn) {
              btn.disabled = false;
              btn.textContent = original;
            }
            return;
          }
          if (cell) {
            cell.innerHTML = '<span class="badge-status badge-paid">Attended</span>';
          }
          const certCell = form.closest('tr')?.querySelector('td:nth-child(7)');
          if (certCell && certCell.textContent.trim() === '—') {
            certCell.innerHTML = '<span style="font-size:.72rem;color:var(--cyber-orange)">Processing certificate…</span>';
          }
        })
        .catch(function () {
          alert('Network error. Please try again.');
          if (btn) {
            btn.disabled = false;
            btn.textContent = original;
          }
        });
    });
  });
})();
</script>

<?php renderAdminPageEnd(); ?>
