<?php
require_once __DIR__ . '/includes/public-enquiry.php';

startSession();

$navActive = 'bootcamps';
$pageTitle = 'Enquire & Enroll';
$contactMethods = enquireEnrollContactMethods();
$courseGroups = enquireEnrollCourseOptionGroups();
$selectedCourse = enquireEnrollDefaultCourseSelection();
$selectedContact = trim((string) ($_POST['preferred_contact'] ?? 'email'));
if (!isset($contactMethods[$selectedContact])) {
    $selectedContact = 'email';
}

$prefillName = trim((string) ($_GET['name'] ?? ''));
$prefillEmail = trim((string) ($_GET['email'] ?? ''));
$prefillPhone = trim((string) ($_GET['phone'] ?? ''));

if (isLoggedIn()) {
    $u = dbTry(fn () => db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id=?', [(int) $_SESSION['user_id']]), null);
    if ($u) {
        if ($prefillName === '') {
            $prefillName = $u['full_name'] ?? '';
        }
        if ($prefillEmail === '') {
            $prefillEmail = $u['email'] ?? '';
        }
        if ($prefillPhone === '') {
            $prefillPhone = $u['phone'] ?? '';
        }
    }
}

enquireEnrollValidatePostRequest($contactMethods);

handlePublicEnquiryPost('enquire-enroll.php', 'Enquire & Enroll', [
    'Course / Program'       => enquireEnrollResolveCourseLabel((string) ($_POST['course_selection'] ?? $selectedCourse)),
    'Preferred Contact'      => $contactMethods[$_POST['preferred_contact'] ?? $selectedContact] ?? '',
]);

$enrollSteps = [
    [
        'title' => 'Submit Your Details',
        'desc'  => 'Tell us which program you are interested in and how you would like us to reach you.',
    ],
    [
        'title' => 'Team Review',
        'desc'  => 'Our enrollment team confirms availability and shares joining or payment instructions within 24 hours.',
    ],
    [
        'title' => 'Start Learning',
        'desc'  => 'Join live sessions, access labs, and begin your CYBEORCH learning journey.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LABS</title>
<meta name="description" content="Enquire and enroll in CYBEORCH LABS webinars, bootcamps, and software development company programs. Submit your details and our team will contact you.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Enquire &amp; <span class="accent">Enroll</span></h1>
    <p>Interested in a webinar, bootcamp, or software development company track? Complete the form below and our team will guide you through enrollment.</p>
  </div>
</header>

<main class="section pt-0" id="enquire-enroll">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">How It <span class="accent">Works</span></h2>
        <ul class="step-list">
          <?php foreach ($enrollSteps as $i => $step): ?>
          <li>
            <span class="step-num"><?= $i + 1 ?></span>
            <div class="step-content">
              <h3 class="step-title"><?= htmlspecialchars($step['title']) ?></h3>
              <p class="step-desc"><?= htmlspecialchars($step['desc']) ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="info-card mt-3">
          <div style="font-size:.8rem;color:var(--cyber-muted);margin-bottom:.35rem">Need help choosing?</div>
          <p style="font-size:.88rem;color:var(--cyber-muted);margin:0;line-height:1.65">Select &ldquo;General enquiry&rdquo; in the form and describe your goals — we will recommend the right program.</p>
        </div>
        <div class="info-card info-card-cta mt-3">
          <div class="info-card-cta-label">Browse programs</div>
          <a href="<?= url('webinars.php') ?>" class="btn-outline-cyber mb-2 d-block text-center"><i class="fas fa-video me-2"></i>All Webinars</a>
          <a href="<?= url('bootcamps.php') ?>" class="btn-outline-cyber d-block text-center"><i class="fas fa-graduation-cap me-2"></i>All Bootcamps</a>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <div class="enquiry-form-header">
            <h2 class="section-title" style="font-size:1.5rem;margin-bottom:0">Enquiry <span class="accent">Form</span></h2>
            <p class="enquiry-form-intro">Fields marked with <span style="color:var(--cyber-accent)">*</span> are required. We typically respond within one business day.</p>
          </div>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('enquire-enroll.php') ?>" id="enrollForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="enrollName">Full Name *</label>
                <input type="text" name="name" id="enrollName" class="form-control" placeholder="Your full name" required minlength="2" maxlength="120" autocomplete="name" value="<?= postVal('name', $prefillName) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="enrollEmail">Email Address *</label>
                <input type="email"
                  name="email"
                  id="enrollEmail"
                  class="form-control"
                  placeholder="you@gmail.com"
                  required
                  autocomplete="email"
                  inputmode="email"
                  autocapitalize="off"
                  spellcheck="false"
                  maxlength="254"
                  pattern="[a-zA-Z0-9]([a-zA-Z0-9._%+-]{0,62}[a-zA-Z0-9])?@gmail\.com"
                  title="<?= htmlspecialchars(GMAIL_VALIDATION_MESSAGE) ?>"
                  aria-describedby="enrollEmailError"
                  value="<?= postVal('email', $prefillEmail) ?>">
                <div id="enrollEmailError" class="field-error" role="alert" hidden><?= htmlspecialchars(GMAIL_VALIDATION_MESSAGE) ?></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="enrollPhone">Phone Number *</label>
                <input type="tel" name="phone" id="enrollPhone" class="form-control" placeholder="+91 98765 43210" required autocomplete="tel" inputmode="tel" minlength="8" maxlength="20" aria-describedby="enrollPhoneHint enrollPhoneError" value="<?= postVal('phone', $prefillPhone) ?>">
                <p class="form-hint" id="enrollPhoneHint">Include country code if outside India.</p>
                <div id="enrollPhoneError" class="field-error" role="alert" hidden>Please enter a valid phone number (at least 8 digits).</div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="courseSelection">Course / Webinar / Bootcamp *</label>
                <select name="course_selection" id="courseSelection" class="form-select" required>
                  <option value="general"<?= $selectedCourse === 'general' ? ' selected' : '' ?>>General enquiry — advise me</option>
                  <?php foreach ($courseGroups as $group): ?>
                  <optgroup label="<?= htmlspecialchars($group['label']) ?>">
                    <?php foreach ($group['options'] as $opt): ?>
                    <option value="<?= htmlspecialchars($opt['value']) ?>"<?= $selectedCourse === $opt['value'] ? ' selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="enrollMessage">Message / Requirements *</label>
                <textarea name="message" id="enrollMessage" class="form-control" rows="5" required minlength="10" maxlength="4000" placeholder="Tell us your background, goals, preferred start date, and any questions about the program..."><?= postVal('message') ?></textarea>
                <p class="form-hint">Minimum 10 characters.</p>
              </div>
              <div class="col-12">
                <span class="form-label d-block" id="preferredContactLabel">Contact Us / Preferred Reply Method *</span>
                <?php renderContactActionGrid($selectedContact, true); ?>
                <p class="form-hint">Tap the card to open your email app. Your selection is saved for our team when you submit this form.</p>
              </div>
              <div class="col-12 pt-1">
                <button type="submit" class="btn-primary-cyber w-100" id="enrollSubmitBtn" disabled>
                  <i class="fas fa-paper-plane me-2"></i>Submit Enquiry
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderSiteScripts(true); ?>
<?php renderContactActionGridScript(); ?>
<script src="<?= url('assets/js/gmail-email-validation.js') ?>"></script>
<script>
(function () {
  var form = document.getElementById('enrollForm');
  var submitBtn = document.getElementById('enrollSubmitBtn');
  var phoneInput = document.getElementById('enrollPhone');
  var phoneError = document.getElementById('enrollPhoneError');
  var messageInput = document.getElementById('enrollMessage');

  function phoneDigits() {
    return (phoneInput && phoneInput.value.replace(/\D/g, '')) || '';
  }

  function validatePhone() {
    if (!phoneInput) return true;
    var ok = phoneDigits().length >= 8;
    phoneInput.classList.toggle('is-invalid', !ok && phoneInput.value.trim() !== '');
    if (phoneError) phoneError.hidden = ok;
    return ok;
  }

  function validateMessage() {
    if (!messageInput) return true;
    var ok = messageInput.value.trim().length >= 10;
    messageInput.classList.toggle('is-invalid', !ok && messageInput.value.trim() !== '');
    return ok;
  }

  function syncSubmitState() {
    if (!submitBtn) return;
    var emailEl = document.getElementById('enrollEmail');
    var gmailOk = !window.CybeorchGmailValidation || !emailEl
      || window.CybeorchGmailValidation.isValidGmailAddress(emailEl.value);
    var ready = gmailOk && validatePhone() && validateMessage() && form && form.checkValidity();
    submitBtn.disabled = !ready;
  }

  var gmailField = window.CybeorchGmailValidation?.bindGmailEmailField(
    document.getElementById('enrollEmail'),
    document.getElementById('enrollEmailError'),
    null
  );

  document.getElementById('enrollEmail')?.addEventListener('input', syncSubmitState);
  document.getElementById('enrollEmail')?.addEventListener('blur', syncSubmitState);
  phoneInput?.addEventListener('input', syncSubmitState);
  phoneInput?.addEventListener('blur', validatePhone);
  messageInput?.addEventListener('input', syncSubmitState);

  form?.addEventListener('submit', function (e) {
    var ok = true;
    if (gmailField && !gmailField.validate()) {
      ok = false;
      document.getElementById('enrollEmail')?.focus();
    }
    if (!validatePhone()) {
      ok = false;
      phoneInput?.focus();
    }
    if (!validateMessage()) {
      ok = false;
      messageInput?.focus();
    }
    if (!ok) e.preventDefault();
  });

  syncSubmitState();
})();
</script>
</body>
</html>
