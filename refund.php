<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/legal-public.php';

startSession();

$pageTitle = 'Refund Policy';
renderLegalPublicPageStart($pageTitle, 'CYBEORCH LABS refund policy for webinars, bootcamps, and paid programs.');
?>

<header class="page-hero">
  <div class="container">
    <h1>Refund <span class="accent">Policy</span></h1>
    <p>Clear guidelines on cancellations, refunds, and credit options.</p>
  </div>
</header>

<section class="section">
  <div class="container">
    <div class="legal-card">
      <p class="legal-updated">Last updated: <?= date('F j, Y') ?></p>

      <h2>1. Overview</h2>
      <p>CYBEORCH LABS aims to deliver high-quality cybersecurity education. This policy explains when refunds may be issued for paid webinars, bootcamps, and related services purchased on our platform.</p>

      <h2>2. Eligibility</h2>
      <ul>
        <li><strong>Webinars (paid):</strong> Full refund if requested at least 48 hours before the scheduled start time.</li>
        <li><strong>Bootcamps:</strong> Full refund if requested at least 7 days before the bootcamp start date, provided no more than one live session has been accessed.</li>
        <li><strong>Duplicate payments:</strong> Refunded in full after verification.</li>
        <li><strong>Technical failure:</strong> If we cancel a session or cannot deliver the service, you may choose a full refund or transfer to another batch.</li>
      </ul>

      <h2>3. Non-refundable cases</h2>
      <ul>
        <li>Requests made after the program has started or after substantial materials have been accessed.</li>
        <li>No-shows without prior notice within the eligible window.</li>
        <li>Free webinars and promotional offers unless otherwise stated.</li>
        <li>Third-party fees charged by payment providers (if any) may not be reversible.</li>
      </ul>

      <h2>4. How to request a refund</h2>
      <p>Email <strong>support@CYBEORCH.com</strong> from your registered account email with your full name, program name, payment date, and transaction reference. We typically respond within 3&ndash;5 business days.</p>

      <h2>5. Processing time</h2>
      <p>Approved refunds are initiated within 7&ndash;10 business days. Depending on your bank or payment provider, funds may take additional time to appear in your account.</p>

      <h2>6. Wallet credit</h2>
      <p>At our discretion, we may offer platform wallet credit instead of a cash refund. Wallet credit can be applied to future enrollments on CYBEORCH LABS.</p>

      <h2>7. Contact</h2>
      <p>Questions about this policy? <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">Contact Us</a>.</p>
    </div>
  </div>
</section>

<?php renderLegalPublicPageEnd(); ?>
