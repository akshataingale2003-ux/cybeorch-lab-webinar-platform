<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/student-layout.php';
require_once __DIR__ . '/includes/certificate-system.php';

$ctx = studentContext();
extract($ctx);
$userId = $ctx['userId'];

ensureCertificatesSchema();
syncMissingCertificatesForUser($userId);
$certificates = listCertificatesForUser($userId);

renderStudentHead('My Certificates');
renderStudentSidebar('certificates', $ctx);
?>
<main class="main">
  <?php renderPortalTopbar('<i class="fas fa-certificate me-2" style="color:var(--cyber-accent)"></i>My Certificates'); ?>
  <div class="content">
    <p style="color:var(--cyber-muted);font-size:.88rem;margin-bottom:1.25rem">
      Certificates are issued automatically after webinar attendance or bootcamp completion is confirmed by Cybeorch Labs.
    </p>

    <?php if (empty($certificates)): ?>
    <div class="section-card">
      <div class="portal-empty" style="padding:2rem 1rem;text-align:center;color:var(--cyber-muted)">
        <i class="fas fa-certificate" style="font-size:2rem;margin-bottom:.75rem;opacity:.5"></i>
        <p style="margin:0">No certificates yet. Complete a webinar or bootcamp to earn your certificate.</p>
      </div>
    </div>
    <?php else: ?>
    <?php foreach ($certificates as $c):
        $certId = (string) $c['certificate_id'];
        $emailMeta = certificateEmailStatusMeta($c);
    ?>
    <div class="section-card cert-list-card">
      <div class="cert-list-head">
        <div>
          <div class="cert-list-title"><?= htmlspecialchars((string) $c['event_name']) ?></div>
          <div class="cert-list-meta">
            <span><strong>ID:</strong> <?= htmlspecialchars($certId) ?></span>
            <span><strong>Type:</strong> <?= htmlspecialchars(ucfirst((string) $c['event_type'])) ?></span>
            <span><strong>Event:</strong> <?= htmlspecialchars(certificateFormatDate((string) $c['event_date'])) ?></span>
            <span><strong>Issued:</strong> <?= htmlspecialchars(certificateFormatDate((string) $c['issue_date'])) ?></span>
            <span><strong>Status:</strong> Certificate Generated</span>
            <span><strong>Email:</strong>
              <?php if ($emailMeta['status'] === 'sent'): ?>
              <span style="color:var(--cyber-green)">Sent Successfully</span>
              <?php elseif ($emailMeta['status'] === 'failed'): ?>
              <span style="color:#ff8888">Delivery Failed</span>
              <?php else: ?>
              <span style="color:var(--cyber-muted)">Pending</span>
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>
      <div class="cert-action-btns">
        <a href="<?= certificateDownloadUrl($certId, 'pdf', true) ?>" class="btn-cyber btn-cyber-sm" target="_blank" rel="noopener"><i class="fas fa-eye me-1"></i>View Certificate</a>
        <a href="<?= certificateDownloadUrl($certId, 'pdf') ?>" class="btn-cyber btn-cyber-sm btn-cyber-outline"><i class="fas fa-file-pdf me-1"></i>Download PDF</a>
        <a href="<?= certificateDownloadUrl($certId, 'png') ?>" class="btn-cyber btn-cyber-sm btn-cyber-outline"><i class="fas fa-image me-1"></i>Download PNG</a>
        <a href="<?= certificateDownloadUrl($certId, 'jpg') ?>" class="btn-cyber btn-cyber-sm btn-cyber-outline"><i class="fas fa-image me-1"></i>Download JPG</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
