<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/certificate-system.php';

ensureCertificatesSchema();

$certificateId = trim((string) ($_GET['id'] ?? ''));
$cert = $certificateId !== '' ? findCertificateByPublicId($certificateId) : null;

$pageTitle = 'Verify Certificate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= asset('assets/css/certificate.css') ?>">
<?php renderPortalPageHead(); ?>
</head>
<body class="certificate-verify-page">
<?php require_once __DIR__ . '/includes/public-navbar.php'; ?>

<main class="certificate-verify-main">
  <div class="certificate-verify-card">
    <div class="certificate-verify-header">
      <img src="<?= asset('assets/images/Blue_home_white.png') ?>" alt="Cybeorch Labs" class="certificate-verify-logo" width="160" height="48">
      <h1>Certificate Verification</h1>
      <p>Verify the authenticity of a Cybeorch Labs digital certificate.</p>
    </div>

    <form method="get" class="certificate-verify-search">
      <label for="cert-id">Certificate ID</label>
      <div class="certificate-verify-search-row">
        <input type="text" id="cert-id" name="id" value="<?= htmlspecialchars($certificateId) ?>" placeholder="CYB-WEB-JUN2026-0001" required>
        <button type="submit">Verify</button>
      </div>
    </form>

    <?php if ($certificateId !== ''): ?>
      <?php if ($cert && (($cert['status'] ?? 'valid') === 'valid')): ?>
      <div class="certificate-verify-result valid">
        <div class="certificate-status-badge">Valid</div>
        <dl class="certificate-verify-details">
          <div><dt>Certificate ID</dt><dd><?= htmlspecialchars((string) $cert['certificate_id']) ?></dd></div>
          <div><dt>User Name</dt><dd><?= htmlspecialchars((string) $cert['user_name']) ?></dd></div>
          <div><dt>Event Name</dt><dd><?= htmlspecialchars((string) $cert['event_name']) ?></dd></div>
          <div><dt>Event Type</dt><dd><?= htmlspecialchars(ucfirst((string) $cert['event_type'])) ?></dd></div>
          <div><dt>Event Date</dt><dd><?= htmlspecialchars(certificateFormatDate((string) $cert['event_date'])) ?></dd></div>
          <div><dt>Issue Date</dt><dd><?= htmlspecialchars(certificateFormatDate((string) $cert['issue_date'])) ?></dd></div>
          <div><dt>Certificate Status</dt><dd class="status-valid">Valid</dd></div>
        </dl>
      </div>
      <?php elseif ($cert): ?>
      <div class="certificate-verify-result invalid">
        <div class="certificate-status-badge invalid-badge">Invalid</div>
        <p>This certificate ID exists but its status is <strong><?= htmlspecialchars((string) ($cert['status'] ?? 'invalid')) ?></strong>.</p>
      </div>
      <?php else: ?>
      <div class="certificate-verify-result invalid">
        <div class="certificate-status-badge invalid-badge">Not Found</div>
        <p>No certificate was found for ID <strong><?= htmlspecialchars($certificateId) ?></strong>. Please check the ID and try again.</p>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<?php renderPublicFooter(); ?>
</body>
</html>
