<?php
require_once __DIR__ . '/includes/public-enquiry.php';

startSession();

$navActive = 'services';
$pageTitle = 'Start Your Project';
$fixedSubject = 'Start Your Project';

handlePublicEnquiryPost('start-project.php', $fixedSubject, [
    'Company / Organization' => $_POST['company'] ?? '',
    'Project Type'           => $_POST['project_type'] ?? '',
    'Budget Range'           => $_POST['budget'] ?? '',
    'Timeline'               => $_POST['timeline'] ?? '',
]);

$projectTypes = [
    'Web Application',
    'Mobile Application',
    'Full Stack Product',
    'Blockchain / Web3',
    'Cybersecurity',
    'AI / Automation',
    'Cloud & DevOps',
    'Other',
];

$budgetRanges = [
    'Under ₹50,000',
    '₹50,000 – ₹2,00,000',
    '₹2,00,000 – ₹10,00,000',
    '₹10,00,000+',
    'Not sure yet',
];

$howItWorksSteps = [
    [
        'title' => 'Submit Your Project Brief',
        'desc'  => 'Complete the form with your contact details, project type, budget, timeline, and a clear description of what you want to build.',
    ],
    [
        'title' => 'Expert Review & Scoping',
        'desc'  => 'Our technical team reviews your requirements, recommends the right stack (web, mobile, cloud, security, AI, and more), and clarifies scope if needed.',
    ],
    [
        'title' => 'Proposal & Roadmap',
        'desc'  => 'You receive a tailored plan with milestones, timeline, and investment estimate — usually within 24–48 business hours.',
    ],
    [
        'title' => 'Kickoff & Development',
        'desc'  => 'After your approval, we assign the right developers and start building with regular updates until launch and handover.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH</title>
<meta name="description" content="Tell us about your project. CYBEORCH experts will help you build secure, scalable digital solutions.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Start Your <span class="accent">Project</span></h1>
    <p>Share your vision and requirements. Our team will review your brief and get back with a tailored plan and next steps.</p>
  </div>
</header>

<main class="section pt-0">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">How It <span class="accent">Works</span></h2>
        <div class="divider"></div>
        <p style="color:var(--cyber-muted);font-size:.92rem;line-height:1.7;margin-bottom:1.5rem">From idea to launch — a simple, transparent process for startups, SMEs, and enterprises.</p>
        <ul class="step-list">
          <?php foreach ($howItWorksSteps as $i => $step): ?>
          <li>
            <span class="step-num"><?= $i + 1 ?></span>
            <div class="step-content">
              <h3 class="step-title"><?= htmlspecialchars($step['title']) ?></h3>
              <p class="step-desc"><?= htmlspecialchars($step['desc']) ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="info-card info-card-cta mt-4">
          <div class="info-card-cta-label">Need quick advice first?</div>
          <a href="<?= url('book-consulting.php') ?>" class="btn-outline-cyber"><i class="fas fa-calendar-check me-2"></i>Book Consulting</a>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <h2 class="section-title" style="font-size:1.5rem">Project <span class="accent">Brief</span></h2>
          <div class="divider"></div>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('start-project.php') ?>">
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
                <input type="text" name="company" class="form-control" placeholder="Your company name" value="<?= postVal('company') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Project Type *</label>
                <select name="project_type" class="form-select" required>
                  <option value="">Select type</option>
                  <?php foreach ($projectTypes as $type): ?>
                  <option value="<?= htmlspecialchars($type) ?>"<?= ($_POST['project_type'] ?? '') === $type ? ' selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Budget Range</label>
                <select name="budget" class="form-select">
                  <option value="">Select budget</option>
                  <?php foreach ($budgetRanges as $range): ?>
                  <option value="<?= htmlspecialchars($range) ?>"<?= ($_POST['budget'] ?? '') === $range ? ' selected' : '' ?>><?= htmlspecialchars($range) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Expected Timeline</label>
                <input type="text" name="timeline" class="form-control" placeholder="e.g. 3 months, ASAP, Q3 2026" value="<?= postVal('timeline') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Project Description *</label>
                <textarea name="message" class="form-control" rows="6" placeholder="Describe your goals, features, target users, and any technical preferences..." required><?= postVal('message') ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-cyber w-100"><i class="fas fa-rocket me-2"></i>Submit Project Request</button>
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
