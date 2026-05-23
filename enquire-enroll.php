<?php
require_once __DIR__ . '/includes/public-enquiry.php';

startSession();

$navActive = 'bootcamps';
$pageTitle = 'Free Bootcamps & Webinars Registration';

$interestTypes = [
    'free-webinar' => 'Free Webinar',
    'bootcamp'     => 'Bootcamp Program',
    'both'         => 'Free Webinar + Bootcamp',
    'pro-trial'    => 'Pro Learner Trial (₹999/month)',
];

$proTrialPlan = [
    'label' => 'Pro Learner',
    'price' => '₹999/month',
    'desc'  => 'Unlimited webinars, 50% off bootcamps, mentorship & career support.',
];

$freeWebinars = dbTry(
    fn () => db()->fetchAll(
        "SELECT title, slug, scheduled_at FROM webinars WHERE is_free = 1 AND status IN ('upcoming','live') ORDER BY scheduled_at ASC"
    ),
    []
);

$programs = [
    '30-day-bootcamp' => [
        'label' => '30-Day Bootcamp',
        'price' => '₹14,999',
        'desc'  => 'Fast-track foundation with live labs and certificate.',
    ],
    '90-day-bootcamp' => [
        'label' => '90-Day Industry Bootcamp',
        'price' => '₹24,999',
        'desc'  => 'Deeper specialization with capstone and career support.',
    ],
    'premium-industry-lab' => [
        'label' => 'Premium Industry Lab Program',
        'price' => '₹35,000+',
        'desc'  => 'Elite lab access and enterprise mentors.',
    ],
];

$bootcamps = dbTry(
    fn () => db()->fetchAll("SELECT title, slug, discounted_fee FROM bootcamps WHERE status = 'open' ORDER BY start_date ASC"),
    []
);

$selectedProgram = (string) ($_GET['program'] ?? $_POST['program'] ?? '30-day-bootcamp');
if (!isset($programs[$selectedProgram])) {
    $selectedProgram = '30-day-bootcamp';
}

$selectedBootcamp = (string) ($_GET['bootcamp'] ?? $_POST['bootcamp_slug'] ?? '');
$selectedInterest = (string) ($_GET['interest'] ?? $_POST['interest_type'] ?? 'free-webinar');
if (($_GET['plan'] ?? '') === 'pro-trial') {
    $selectedInterest = 'pro-trial';
}
if (!isset($interestTypes[$selectedInterest])) {
    $selectedInterest = 'free-webinar';
}
$isProTrial = $selectedInterest === 'pro-trial';
$selectedWebinar = (string) ($_GET['webinar'] ?? $_POST['webinar_slug'] ?? '');

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

$programLabel = $programs[$selectedProgram]['label'];
$bootcampLabel = '';
foreach ($bootcamps as $b) {
    if ($b['slug'] === $selectedBootcamp) {
        $bootcampLabel = $b['title'] . ' (' . formatRupee((float) $b['discounted_fee']) . ')';
        break;
    }
}
$webinarLabel = '';
foreach ($freeWebinars as $w) {
    if ($w['slug'] === $selectedWebinar) {
        $webinarLabel = $w['title'] . ' (' . date('d M Y', strtotime((string) $w['scheduled_at'])) . ')';
        break;
    }
}

$enquirySubject = $isProTrial ? 'Pro Learner Trial Registration' : 'Free Bootcamps & Webinars Registration';
handlePublicEnquiryPost('enquire-enroll.php', $enquirySubject, [
    'Registration For'  => $interestTypes[$_POST['interest_type'] ?? $selectedInterest] ?? $selectedInterest,
    'Membership Plan'   => ($_POST['interest_type'] ?? '') === 'pro-trial' ? $proTrialPlan['label'] . ' — ' . $proTrialPlan['price'] : '',
    'Free Webinar'      => $webinarLabel !== '' ? $webinarLabel : ($_POST['webinar_slug'] ?? ''),
    'Program Track'     => $programLabel,
    'Specific Bootcamp' => $bootcampLabel !== '' ? $bootcampLabel : ($_POST['bootcamp_slug'] ?? ''),
    'Skill Level'       => $_POST['skill_level'] ?? '',
    'Preferred Start'   => $_POST['preferred_start'] ?? '',
]);

$skillLevels = [
    'Beginner',
    'Intermediate',
    'Advanced',
];

$enrollSteps = [
    [
        'title' => 'Choose Your Program',
        'desc'  => 'Pick a free webinar, bootcamp track, or both — then share your contact details.',
    ],
    [
        'title' => 'Team Review',
        'desc'  => 'Our enrollment team confirms your slot and sends joining instructions within 24 hours.',
    ],
    [
        'title' => 'Join & Learn',
        'desc'  => 'Attend live sessions, access labs, and start earning NxL rewards on participation.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LAB</title>
<meta name="description" content="Register for free CYBEORCH LAB webinars and bootcamp programs. Quick enrollment form for upcoming live sessions.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <?php if ($isProTrial): ?>
    <h1>Pro Learner <span class="accent">Trial</span></h1>
    <p>Start your Pro Learner trial (₹999/month). Submit your details and our team will activate your plan with full webinar and bootcamp benefits.</p>
    <?php else: ?>
    <h1>Free Bootcamps &amp; <span class="accent">Webinars</span></h1>
    <p>Register for free live webinars and bootcamp programs. Select what you want to join, submit your details, and we&rsquo;ll confirm your seat.</p>
    <?php endif; ?>
  </div>
</header>

<main class="section pt-0">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">Enrollment <span class="accent">Process</span></h2>
        <div class="divider"></div>
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
          <div style="font-size:.8rem;color:var(--cyber-muted);margin-bottom:.35rem">Selected plan</div>
          <?php if ($isProTrial): ?>
          <div style="font-family:'Rajdhani',sans-serif;font-size:1.15rem;color:#fff;font-weight:600"><?= htmlspecialchars($proTrialPlan['label']) ?></div>
          <div style="color:var(--cyber-green);font-weight:700;margin-top:.25rem"><?= htmlspecialchars($proTrialPlan['price']) ?></div>
          <p style="font-size:.85rem;color:var(--cyber-muted);margin:.75rem 0 0;line-height:1.6"><?= htmlspecialchars($proTrialPlan['desc']) ?></p>
          <?php else: ?>
          <div style="font-family:'Rajdhani',sans-serif;font-size:1.15rem;color:#fff;font-weight:600"><?= htmlspecialchars($programs[$selectedProgram]['label']) ?></div>
          <div style="color:var(--cyber-green);font-weight:700;margin-top:.25rem"><?= htmlspecialchars($programs[$selectedProgram]['price']) ?></div>
          <?php endif; ?>
        </div>
        <div class="info-card info-card-cta mt-3">
          <div class="info-card-cta-label">Browse upcoming sessions</div>
          <a href="<?= url('webinars.php') ?>" class="btn-outline-cyber mb-2 d-block text-center"><i class="fas fa-video me-2"></i>All Webinars</a>
          <a href="<?= url('bootcamps.php') ?>" class="btn-outline-cyber d-block text-center"><i class="fas fa-graduation-cap me-2"></i>All Bootcamps</a>
        </div>
       <!-- <div class="info-card mt-3">
          <div style="font-size:.8rem;color:var(--cyber-muted);margin-bottom:.35rem">Need a platform account?</div>
          <a href="<?= url('index.php?register_required=1') ?>" style="color:var(--cyber-accent);font-weight:600">Create free account &rarr;</a>
        </div> -->
      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <h2 class="section-title" style="font-size:1.5rem">Registration <span class="accent">Form</span></h2>
          <div class="divider"></div>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('enquire-enroll.php') ?>" id="enrollForm">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">I want to register for *</label>
                <select name="interest_type" class="form-select" required id="interestSelect">
                  <?php foreach ($interestTypes as $key => $label): ?>
                  <option value="<?= htmlspecialchars($key) ?>"<?= $selectedInterest === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12" id="webinarField"<?= in_array($selectedInterest, ['bootcamp', 'pro-trial'], true) ? ' hidden' : '' ?>>
                <label class="form-label">Free Webinar <?= $selectedInterest === 'free-webinar' ? '*' : '(optional)' ?></label>
                <select name="webinar_slug" class="form-select" id="webinarSelect"<?= $selectedInterest === 'free-webinar' ? ' required' : '' ?>>
                  <option value="">Select a free webinar</option>
                  <?php foreach ($freeWebinars as $w): ?>
                  <option value="<?= htmlspecialchars($w['slug']) ?>"<?= $selectedWebinar === $w['slug'] ? ' selected' : '' ?>>
                    <?= htmlspecialchars($w['title']) ?> — <?= date('d M Y', strtotime((string) $w['scheduled_at'])) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <?php if (empty($freeWebinars)): ?>
                <p class="mt-2 mb-0" style="font-size:.85rem;color:var(--cyber-muted)">No free webinars scheduled right now — choose bootcamp or leave a message below.</p>
                <?php endif; ?>
              </div>
              <div id="bootcampFields"<?= in_array($selectedInterest, ['free-webinar', 'pro-trial'], true) ? ' hidden' : '' ?>>
                <div class="col-12">
                  <label class="form-label">Program Track *</label>
                  <select name="program" class="form-select" id="programSelect"<?= $selectedInterest !== 'free-webinar' ? ' required' : '' ?>>
                    <?php foreach ($programs as $key => $prog): ?>
                    <option value="<?= htmlspecialchars($key) ?>"<?= $selectedProgram === $key ? ' selected' : '' ?>>
                      <?= htmlspecialchars($prog['label']) ?> — <?= htmlspecialchars($prog['price']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php if ($bootcamps): ?>
                <div class="col-12 mt-3">
                  <label class="form-label">Specific Bootcamp (optional)</label>
                  <select name="bootcamp_slug" class="form-select">
                    <option value="">General enquiry for selected track</option>
                    <?php foreach ($bootcamps as $b): ?>
                    <option value="<?= htmlspecialchars($b['slug']) ?>"<?= $selectedBootcamp === $b['slug'] ? ' selected' : '' ?>>
                      <?= htmlspecialchars($b['title']) ?> — <?= formatRupee((float) $b['discounted_fee']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <label class="form-label">Your Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Full name" required value="<?= postVal('name', $prefillName) ?>">
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
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" placeholder="+91 XXXXX XXXXX" required value="<?= postVal('phone', $prefillPhone) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Your current skill level *</label>
                <?php $skillLevelVal = (string) ($_POST['skill_level'] ?? ''); ?>
                <select name="skill_level" class="form-select form-select-placeholder" required>
                  <option value="" disabled hidden<?= $skillLevelVal === '' ? ' selected' : '' ?>>Select skill level</option>
                  <?php foreach ($skillLevels as $level): ?>
                  <option value="<?= htmlspecialchars($level) ?>"<?= $skillLevelVal === $level ? ' selected' : '' ?>><?= htmlspecialchars($level) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Preferred Start</label>
                <input type="text" name="preferred_start" class="form-control" placeholder="e.g. Next month, June 2026, ASAP" value="<?= postVal('preferred_start') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Message / Questions *</label>
                <textarea name="message" class="form-control" rows="5" id="enrollMessage" placeholder="<?= $isProTrial ? 'Tell us your learning goals and when you would like to start the Pro Learner trial...' : 'Tell us your goals, background, and any questions about the program...' ?>" required><?= postVal('message') ?></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-cyber w-100" id="enrollSubmitBtn" disabled><i class="fas fa-paper-plane me-2"></i><?= $isProTrial ? 'Start Pro Trial' : 'Submit Registration' ?></button>
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
<script src="<?= url('assets/js/gmail-email-validation.js') ?>"></script>
<script>
(function () {
  var gmailField = window.CybeorchGmailValidation?.bindGmailEmailField(
    document.getElementById('enrollEmail'),
    document.getElementById('enrollEmailError'),
    document.getElementById('enrollSubmitBtn')
  );

  document.getElementById('enrollForm')?.addEventListener('submit', function (e) {
    if (!gmailField || gmailField.validate()) return;
    e.preventDefault();
    document.getElementById('enrollEmail')?.focus();
  });

  var interest = document.getElementById('interestSelect');
  var webinarField = document.getElementById('webinarField');
  var bootcampFields = document.getElementById('bootcampFields');
  var webinarSelect = document.getElementById('webinarSelect');
  var programSelect = document.getElementById('programSelect');

  var messageEl = document.getElementById('enrollMessage');
  var submitBtn = document.getElementById('enrollSubmitBtn');
  var proPlaceholder = 'Tell us your learning goals and when you would like to start the Pro Learner trial...';
  var defaultPlaceholder = 'Tell us your goals, background, and any questions about the program...';

  function syncFields() {
    var v = interest ? interest.value : 'free-webinar';
    var isPro = v === 'pro-trial';
    var showWebinar = !isPro && (v === 'free-webinar' || v === 'both');
    var showBootcamp = !isPro && (v === 'bootcamp' || v === 'both');
    if (webinarField) webinarField.hidden = !showWebinar;
    if (bootcampFields) bootcampFields.hidden = !showBootcamp;
    if (webinarSelect) webinarSelect.required = showWebinar && v === 'free-webinar' && webinarSelect.options.length > 1;
    if (programSelect) programSelect.required = showBootcamp;
    if (messageEl) messageEl.placeholder = isPro ? proPlaceholder : defaultPlaceholder;
    if (submitBtn) submitBtn.innerHTML = isPro
      ? '<i class="fas fa-star me-2"></i>Start Pro Trial'
      : '<i class="fas fa-paper-plane me-2"></i>Submit ';
    if (isPro) {
      var u = new URL(window.location.href);
      u.searchParams.set('plan', 'pro-trial');
      window.history.replaceState({}, '', u);
    }
  }

  interest?.addEventListener('change', syncFields);
  syncFields();

  programSelect?.addEventListener('change', function () {
    var u = new URL(window.location.href);
    u.searchParams.set('program', this.value);
    window.history.replaceState({}, '', u);
  });
})();
</script>
</body>
</html>
