<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/legal-public.php';

startSession();

$pageTitle = 'Terms of Service';
renderLegalPublicPageStart($pageTitle, 'Terms of Service for using the CYBEORCH LAB platform.');
?>

<header class="page-hero">
  <div class="container">
    <h1>Terms of <span class="accent">Service</span></h1>
    <p>Please read these terms before using CYBEORCH LAB.</p>
  </div>
</header>

<section class="section">
  <div class="container">
    <div class="legal-card">
      <p class="legal-updated">Last updated: <?= date('F j, Y') ?></p>

      <h2>1. Acceptance</h2>
      <p>By creating an account, enrolling in programs, or using this website, you agree to these Terms of Service and our <a href="<?= url('privacy.php') ?>" style="color:var(--cyber-accent)">Privacy Policy</a>.</p>

      <h2>2. Services</h2>
      <p>CYBEORCH LAB provides online cybersecurity education including webinars, bootcamps, learning materials, and related user registration & trainee portal features. Content, schedules, and pricing may change with notice where practical.</p>

      <h2>3. Account responsibilities</h2>
      <ul>
        <li>You must provide accurate registration information.</li>
        <li>You are responsible for safeguarding your login credentials.</li>
        <li>You must not share account access or resell course materials without permission.</li>
        <li>You must not use the platform for unlawful, abusive, or disruptive activity.</li>
      </ul>

      <h2>4. Payments</h2>
      <p>Fees for paid programs are due at enrollment unless otherwise stated. Refunds are governed by our <a href="<?= url('refund.php') ?>" style="color:var(--cyber-accent)">Refund Policy</a>.</p>

      <h2>5. Intellectual property</h2>
      <p>All course content, branding, logos, and platform materials are owned by CYBEORCH or its licensors. You receive a limited, non-transferable license for personal learning use only.</p>

      <h2>6. Conduct in live sessions</h2>
      <p>Participants must follow instructor guidelines, respect others, and avoid recording or redistributing session content without written consent.</p>

      <h2>7. Disclaimer</h2>
      <p>Educational content is provided for learning purposes. Completion of a program does not guarantee employment or certification from third-party bodies unless explicitly stated.</p>

      <h2>8. Limitation of liability</h2>
      <p>To the fullest extent permitted by law, CYBEORCH LAB is not liable for indirect, incidental, or consequential damages arising from use of the platform. Our total liability for a claim is limited to the amount you paid for the specific program giving rise to the claim.</p>

      <h2>9. Termination</h2>
      <p>We may suspend or terminate accounts that violate these terms or pose security risks. You may stop using the service at any time.</p>

      <h2>10. Governing law</h2>
      <p>These terms are governed by the laws of India. Disputes shall be subject to the courts of competent jurisdiction in India unless otherwise required by law.</p>

      <h2>11. Contact</h2>
      <p>Questions about these terms? <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">Contact Us</a>.</p>
    </div>
  </div>
</section>

<?php renderLegalPublicPageEnd(); ?>
