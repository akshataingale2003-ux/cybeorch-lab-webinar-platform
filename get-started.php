<?php
require_once __DIR__ . '/includes/public-enquiry.php';
require_once __DIR__ . '/includes/get-started-inquiries.php';

startSession();

$navActive = 'services';
$pageTitle = 'Get Started';
$contactMethods = getStartedContactMethods();
$serviceOptions = getStartedServiceOptions();
$projectTypes = getStartedProjectTypeOptions();
$budgetRanges = getStartedBudgetOptions();

$selectedService = trim((string) ($_POST['service_interested'] ?? $_GET['service'] ?? ''));
if ($selectedService !== '' && !in_array($selectedService, $serviceOptions, true)) {
    $selectedService = '';
}

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

handleGetStartedInquiryPost('get-started.php');

$steps = [
    ['title' => 'Share Your Requirements', 'desc' => 'Tell us about your goals, service interest, and how we should reach you.'],
    ['title' => 'Expert Review', 'desc' => 'Our team reviews your inquiry and matches the right solution or program within one business day.'],
    ['title' => 'Next Steps', 'desc' => 'We contact you by your preferred method with recommendations, pricing, or enrollment details.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LAB</title>
<meta name="description" content="Get started with CYBEORCH — web, mobile, cloud, cybersecurity, AI, software development company, and project services. Submit your inquiry and our team will contact you.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Get <span class="accent">Started</span></h1>
    <p>Tell us what you need — development, security, cloud, AI, software development company, or a live project. We will review your inquiry and respond quickly.</p>
  </div>
</header>

<main class="section pt-0" id="get-started">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">How It <span class="accent">Works</span></h2>
        <div class="divider"></div>
        <ul class="step-list">
          <?php foreach ($steps as $i => $step): ?>
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
          <div style="font-size:.8rem;color:var(--cyber-muted);margin-bottom:.35rem">Already know your path?</div>
          <p style="font-size:.88rem;color:var(--cyber-muted);margin:0 0 .75rem;line-height:1.65">Browse programs or start a detailed project brief anytime.</p>
          <a href="<?= url('webinars.php') ?>" class="btn-outline-cyber mb-2 d-block text-center"><i class="fas fa-video me-2"></i>Webinars</a>
          <a href="<?= url('start-project.php') ?>" class="btn-outline-cyber d-block text-center"><i class="fas fa-rocket me-2"></i>Start Your Project</a>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <div class="enquiry-form-header">
            <h2 class="section-title" style="font-size:1.5rem;margin-bottom:0">Inquiry <span class="accent">Form</span></h2>
            <div class="divider" style="margin-bottom:0"></div>
            <p class="enquiry-form-intro">Fields marked with <span style="color:var(--cyber-accent)">*</span> are required.</p>
          </div>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('get-started.php') ?>" id="getStartedForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;opacity:0">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="gsName">Full Name *</label>
                <input type="text" name="full_name" id="gsName" class="form-control" required minlength="2" maxlength="120" autocomplete="name" value="<?= postVal('full_name', $prefillName) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gsEmail">Email Address *</label>
                <input type="email" name="email" id="gsEmail" class="form-control" required autocomplete="email" maxlength="254" value="<?= postVal('email', $prefillEmail) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gsPhone">Phone Number *</label>
                <input type="tel" name="phone" id="gsPhone" class="form-control" required autocomplete="tel" inputmode="tel" minlength="8" maxlength="20" value="<?= postVal('phone', $prefillPhone) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gsCompany">Company / Organization</label>
                <input type="text" name="company" id="gsCompany" class="form-control" maxlength="180" autocomplete="organization" value="<?= postVal('company') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gsService">Service Interested In *</label>
                <select name="service_interested" id="gsService" class="form-select" required>
                  <option value="">Select a service</option>
                  <?php foreach ($serviceOptions as $service): ?>
                  <option value="<?= htmlspecialchars($service) ?>"<?= $selectedService === $service ? ' selected' : '' ?>><?= htmlspecialchars($service) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gsProjectType">Project Type</label>
                <select name="project_type" id="gsProjectType" class="form-select">
                  <option value="">Select project type (optional)</option>
                  <?php foreach ($projectTypes as $type): ?>
                  <option value="<?= htmlspecialchars($type) ?>"<?= postVal('project_type') === $type ? ' selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="gsBudget">Budget Range</label>
                <select name="budget_range" id="gsBudget" class="form-select">
                  <option value="">Select budget (optional)</option>
                  <?php foreach ($budgetRanges as $range): ?>
                  <option value="<?= htmlspecialchars($range) ?>"<?= postVal('budget_range') === $range ? ' selected' : '' ?>><?= htmlspecialchars($range) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label" for="gsMessage">Project Requirements / Message *</label>
                <textarea name="message" id="gsMessage" class="form-control" rows="5" required minlength="10" maxlength="4000" placeholder="Describe your goals, timeline, features, and any questions..."><?= postVal('message') ?></textarea>
                <p class="form-hint">Minimum 10 characters.</p>
              </div>
              <div class="col-12">
                <span class="form-label d-block">Preferred Contact Method *</span>
                <div class="d-flex flex-wrap gap-3">
                  <?php foreach ($contactMethods as $key => $label): ?>
                  <label class="d-inline-flex align-items-center gap-2" style="cursor:pointer;font-size:.9rem;color:var(--cyber-text)">
                    <input type="radio" name="preferred_contact" value="<?= htmlspecialchars($key) ?>"<?= $selectedContact === $key ? ' checked' : '' ?> required>
                    <?= htmlspecialchars($label) ?>
                  </label>
                  <?php endforeach; ?>
                </div>
              </div>
              <div class="col-12 pt-1">
                <button type="submit" class="btn-primary-cyber w-100" id="gsSubmitBtn">
                  <i class="fas fa-paper-plane me-2"></i>Submit Inquiry
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
<script>
(function () {
  var form = document.getElementById('getStartedForm');
  var phone = document.getElementById('gsPhone');
  var message = document.getElementById('gsMessage');

  function phoneOk() {
    return ((phone && phone.value.replace(/\D/g, '')) || '').length >= 8;
  }

  function messageOk() {
    return message && message.value.trim().length >= 10;
  }

  form && form.addEventListener('submit', function (e) {
    var ok = true;
    if (phone && !phoneOk()) {
      phone.classList.add('is-invalid');
      ok = false;
    } else if (phone) {
      phone.classList.remove('is-invalid');
    }
    if (message && !messageOk()) {
      message.classList.add('is-invalid');
      ok = false;
    } else if (message) {
      message.classList.remove('is-invalid');
    }
    if (!ok) e.preventDefault();
  });
})();
</script>
</body>
</html>
