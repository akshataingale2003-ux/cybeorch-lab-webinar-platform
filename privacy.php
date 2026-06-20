<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/legal-public.php';

startSession();

$pageTitle = 'Privacy Policy';
renderLegalPublicPageStart($pageTitle, 'How CYBEORCH LABS collects, uses, and protects your personal data.');
?>

<header class="page-hero">
  <div class="container">
    <h1>Privacy <span class="accent">Policy</span></h1>
    <p>How we handle your information when you use CYBEORCH LABS.</p>
  </div>
</header>

<section class="section">
  <div class="container">
    <div class="legal-card">
      <p class="legal-updated">Last updated: <?= date('F j, Y') ?></p>

      <h2>1. Information we collect</h2>
      <p>When you register, enroll, or contact us, we may collect:</p>
      <ul>
        <li>Name, email address, phone number, and profile details</li>
        <li>Payment and transaction references (processed via secure payment partners)</li>
        <li>Course enrollment, attendance, and progress data</li>
        <li>Messages sent through contact forms or support channels</li>
        <li>Technical data such as IP address, browser type, and usage logs for security and analytics</li>
      </ul>

      <h2>2. How we use your information</h2>
      <ul>
        <li>To provide access to webinars, bootcamps, and user registration & trainee dashboard features</li>
        <li>To process payments, confirmations, and certificates</li>
        <li>To send service-related updates (schedules, reminders, account notices)</li>
        <li>To improve platform security and user experience</li>
        <li>To comply with applicable laws and respond to lawful requests</li>
      </ul>

      <h2>3. Sharing of data</h2>
      <p>We do not sell your personal data. We may share limited information with trusted service providers (payment gateways, email delivery, hosting) strictly to operate the platform, under confidentiality obligations.</p>

      <h2>4. Data security</h2>
      <p>We use industry-standard measures including SSL encryption for data in transit, access controls, and secure session handling. No method of transmission over the internet is 100% secure; we work continuously to protect your data.</p>

      <h2>5. Data retention</h2>
      <p>We retain account and enrollment records as long as needed to provide services, meet legal obligations, and resolve disputes. You may request account-related changes by contacting support.</p>

      <h2>6. Your rights</h2>
      <p>Depending on applicable law, you may request access, correction, or deletion of certain personal data. Contact us at <strong>support@CYBEORCH.com</strong> with your registered email.</p>

      <h2>7. Cookies</h2>
      <p>We use session cookies and similar technologies to keep you logged in and remember preferences. You can control cookies through your browser settings.</p>

      <h2>8. Changes</h2>
      <p>We may update this policy from time to time. Continued use of the platform after changes constitutes acceptance of the updated policy.</p>

      <h2>9. Contact</h2>
      <p>For privacy questions, <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">Contact Us</a>.</p>
    </div>
  </div>
</section>

<?php renderLegalPublicPageEnd(); ?>
