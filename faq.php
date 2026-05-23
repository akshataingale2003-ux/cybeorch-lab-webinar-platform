<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/legal-public.php';

startSession();

$pageTitle = 'FAQ';
renderLegalPublicPageStart($pageTitle, 'Frequently asked questions about CYBEORCH LAB webinars, bootcamps, payments, and certificates.');
?>

<header class="page-hero">
  <div class="container">
    <h1>Frequently Asked <span class="accent">Questions</span></h1>
    <p>Answers to common questions about learning, enrolling, and using the platform.</p>
  </div>
</header>

<section class="section">
  <div class="container">
    <div class="accordion" id="faqAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true">How do I register for a webinar or bootcamp?</button>
        </h2>
        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Create a free account, browse upcoming webinars or bootcamps, and complete checkout. You will receive confirmation on your dashboard and by email when payment is successful.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">What payment methods are accepted?</button>
        </h2>
        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">We support secure online payments through our payment gateway (UPI, cards, and net banking where enabled). All transactions are processed over SSL-encrypted connections.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">Can I get a refund if I cannot attend?</button>
        </h2>
        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Refund eligibility depends on the program type and how close the start date is. Please read our <a href="<?= url('refund.php') ?>" style="color:var(--cyber-accent)">Refund Policy</a> or contact support with your registration details.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">Will I receive a certificate?</button>
        </h2>
        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Paid bootcamps and selected programs include a certificate of completion when attendance and assessment requirements are met. Details are listed on each program page.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">How do I access live sessions?</button>
        </h2>
        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">After enrollment, log in to your dashboard for session links, schedules, and materials. Join a few minutes early to test audio and video.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">I forgot my password. What should I do?</button>
        </h2>
        <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
          <div class="accordion-body">Use the <a href="<?= url('forgot-password.php') ?>" style="color:var(--cyber-accent)">Forgot Password</a> page with your registered email. If you still need help, contact us via the <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">contact form</a>.</div>
        </div>
      </div>
    </div>
    <p class="text-center mt-4" style="color:var(--cyber-muted)">Still need help? <a href="<?= url('contact.php') ?>" style="color:var(--cyber-accent)">Contact us</a>.</p>
  </div>
</section>

<?php renderLegalPublicPageEnd(); ?>
