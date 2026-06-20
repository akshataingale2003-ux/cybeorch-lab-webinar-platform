<?php
require_once __DIR__ . '/includes/public-enquiry.php';

startSession();

$navActive = 'services';
$pageTitle = 'Book Consulting';
$fixedSubject = 'Book Consulting';

handlePublicEnquiryPost('book-consulting.php', $fixedSubject, [
    'Company / Organization' => $_POST['company'] ?? '',
    'Consultation Topic'   => $_POST['consult_topic'] ?? '',
    'Preferred Date/Time'  => $_POST['preferred_time'] ?? '',
    'Meeting Mode'         => $_POST['meeting_mode'] ?? '',
]);

$consultTopics = [
    'Technology Strategy',
    'Product & MVP Planning',
    'Cybersecurity Review',
    'Cloud & Infrastructure',
    'Blockchain / Web3',
    'Team & Hiring',
    'Other',
];

$meetingModes = [
    'Video Call (Google Meet / Zoom)',
    'Phone Call',
    'In-person (if available)',
    'No preference',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH</title>
<meta name="description" content="Book a consulting session with CYBEORCH experts for strategy, technology, and product guidance.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Book <span class="accent">Consulting</span></h1>
    <p>Connect with our experts for strategy, architecture, security, and product advice. Pick a topic and tell us what you would like to discuss.</p>
  </div>
</header>

<main class="section pt-0">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">What to <span class="accent">Expect</span></h2>
        <ul class="step-list">
          <li><span class="step-num">1</span><span>Fill in the consulting form with your topic and availability.</span></li>
          <li><span class="step-num">2</span><span>We confirm your session by email.</span></li>
          <li><span class="step-num">3</span><span>30–60 minute call with a senior expert on your chosen area.</span></li>
        </ul>
        <div class="info-card info-card-cta mt-3">
          <div class="info-card-cta-label">Ready to start building?</div>
          <a href="<?= url('start-project.php') ?>" class="btn-outline-cyber"><i class="fas fa-rocket me-2"></i>Start Your Project</a>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <h2 class="section-title" style="font-size:1.5rem">Consulting <span class="accent">Request</span></h2>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('book-consulting.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Your Name *</label>
                <input type="text" name="name" class="form-control" placeholder="John Doe" required value="<?= postVal('name') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" placeholder="you@company.com" required value="<?= postVal('email') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" placeholder="+91 XXXXX XXXXX" required value="<?= postVal('phone') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Company / Organization</label>
                <input type="text" name="company" class="form-control" placeholder="Optional" value="<?= postVal('company') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Consultation Topic *</label>
                <select name="consult_topic" class="form-select" required>
                  <option value="">Select topic</option>
                  <?php foreach ($consultTopics as $topic): ?>
                  <option value="<?= htmlspecialchars($topic) ?>"<?= ($_POST['consult_topic'] ?? '') === $topic ? ' selected' : '' ?>><?= htmlspecialchars($topic) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Meeting Mode</label>
                <select name="meeting_mode" class="form-select">
                  <option value="">Select mode</option>
                  <?php foreach ($meetingModes as $mode): ?>
                  <option value="<?= htmlspecialchars($mode) ?>"<?= ($_POST['meeting_mode'] ?? '') === $mode ? ' selected' : '' ?>><?= htmlspecialchars($mode) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Preferred Date &amp; Time</label>
                <input type="text" name="preferred_time" class="form-control" placeholder="e.g. Mon 15 May, 3:00 PM IST" value="<?= postVal('preferred_time') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">What would you like to discuss? *</label>
                <textarea name="message" class="form-control" rows="6" placeholder="Share your goals, challenges, and questions for the session..." required><?= postVal('message') ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-cyber w-100"><i class="fas fa-calendar-check me-2"></i>Book Consulting Session</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
