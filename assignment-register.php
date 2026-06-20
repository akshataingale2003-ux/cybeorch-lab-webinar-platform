<?php
require_once __DIR__ . '/includes/public-enquiry.php';
require_once __DIR__ . '/includes/assignments-data.php';

startSession();

$navActive = 'assignments';
$pageTitle = HANDS_ON_PROJECTS_REGISTRATION_SUBJECT;
$assignments = getAssignments();

$selectedSlug = sanitize($_GET['assignment'] ?? $_POST['assignment_slug'] ?? '');
$assignment = getAssignmentBySlug($selectedSlug);
if (!$assignment && $selectedSlug !== '') {
    header('Location: ' . url('hands-on-projects.php'));
    exit;
}
if (!$assignment && $assignments) {
    $assignment = $assignments[0];
    $selectedSlug = $assignment['slug'];
}

$prefillName = $prefillEmail = $prefillPhone = '';
if (isLoggedIn()) {
    $u = dbTry(fn () => db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id=?', [(int) $_SESSION['user_id']]), null);
    if ($u) {
        $prefillName = $u['full_name'] ?? '';
        $prefillEmail = $u['email'] ?? '';
        $prefillPhone = $u['phone'] ?? '';
    }
}

$redirectQuery = $selectedSlug !== '' ? '?assignment=' . rawurlencode($selectedSlug) : '';
handlePublicEnquiryPost('assignment-register.php' . $redirectQuery, HANDS_ON_PROJECTS_REGISTRATION_SUBJECT, [
    'Hands-on Projects'      => $_POST['assignment_title'] ?? ($assignment['title'] ?? ''),
    'Hands-on Project Type'  => $_POST['assignment_type'] ?? ($assignment['type'] ?? ''),
    'Category'               => $_POST['assignment_category'] ?? ($assignment['category'] ?? ''),
    'Skill Level'            => $_POST['skill_level'] ?? '',
    'Preferred Start'        => $_POST['preferred_start'] ?? '',
], true);

$skillLevels = [
    'Beginner',
    'Intermediate',
    'Advanced',
];

$registerSteps = [
    ['title' => 'Choose hands-on project', 'desc' => 'Select the open role you want to apply for from our hands-on projects list.'],
    ['title' => 'Submit application', 'desc' => 'Share your contact details, experience, and why you are a good fit.'],
    ['title' => 'Team review', 'desc' => 'Our team reviews applications and contacts shortlisted candidates within 2–3 business days.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> &ndash; CYBEORCH LABS</title>
<meta name="description" content="Apply for open hands-on projects at CYBEORCH LABS — internships, projects, and freelance roles.">
<?php renderPublicPageHead(); renderPublicEnquiryStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Hands-on Projects <span class="accent">Registration</span></h1>
    <p>Apply for an open role at CYBEORCH LABS. Complete the form below and our team will review your application.</p>
  </div>
</header>

<main class="section pt-0">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">How it <span class="accent">works</span></h2>
        <ul class="step-list">
          <?php foreach ($registerSteps as $i => $step): ?>
          <li>
            <span class="step-num"><?= $i + 1 ?></span>
            <div class="step-content">
              <h3 class="step-title"><?= htmlspecialchars($step['title']) ?></h3>
              <p class="step-desc"><?= htmlspecialchars($step['desc']) ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php if ($assignment): ?>
        <div class="info-card mt-3">
          <div style="font-size:.8rem;color:var(--cyber-muted);margin-bottom:.35rem">Applying for</div>
          <div style="font-family:'Rajdhani',sans-serif;font-size:1.15rem;color:#fff;font-weight:600"><?= htmlspecialchars($assignment['title']) ?></div>
          <div class="d-flex flex-wrap gap-2 mt-2">
            <span class="webinar-badge <?= assignmentTypeBadgeClass($assignment['type']) ?>" style="font-size:.65rem"><?= htmlspecialchars($assignment['type']) ?></span>
            <span class="webinar-badge <?= assignmentStatusBadgeClass($assignment['status']) ?>" style="font-size:.65rem"><?= htmlspecialchars($assignment['status']) ?></span>
          </div>
          <p style="font-size:.85rem;color:var(--cyber-muted);margin:.75rem 0 0;line-height:1.6"><?= htmlspecialchars($assignment['description']) ?></p>
          <p style="font-size:.82rem;color:var(--cyber-muted);margin-top:.5rem">
            <i class="far fa-clock me-1"></i><?= htmlspecialchars($assignment['duration']) ?>
            &nbsp;&middot;&nbsp;
            <i class="fas fa-location-dot me-1"></i><?= htmlspecialchars($assignment['mode']) ?>
          </p>
        </div>
        <?php endif; ?>
        <div class="info-card info-card-cta mt-3">
          <div class="info-card-cta-label">Browse all roles</div>
          <a href="<?= url('hands-on-projects.php') ?>" class="btn-outline-cyber d-block text-center"><i class="fas fa-briefcase me-2"></i>Open Hands-on Projects</a>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="enquiry-form">
          <h2 class="section-title" style="font-size:1.5rem">Application <span class="accent">Form</span></h2>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('assignment-register.php' . $redirectQuery) ?>" id="assignmentRegisterForm" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Hands-on Projects *</label>
                <select name="assignment_slug" class="form-select" required id="assignmentSelect">
                  <?php foreach ($assignments as $a): ?>
                  <option value="<?= htmlspecialchars($a['slug']) ?>"
                    data-title="<?= htmlspecialchars($a['title']) ?>"
                    data-type="<?= htmlspecialchars($a['type']) ?>"
                    data-category="<?= htmlspecialchars($a['category']) ?>"
                    <?= ($assignment['slug'] ?? '') === $a['slug'] ? ' selected' : '' ?>>
                    <?= htmlspecialchars($a['title']) ?> — <?= htmlspecialchars($a['type']) ?> (<?= htmlspecialchars($a['status']) ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="assignment_title" id="assignmentTitle" value="<?= htmlspecialchars($assignment['title'] ?? '') ?>">
                <input type="hidden" name="assignment_type" id="assignmentType" value="<?= htmlspecialchars($assignment['type'] ?? '') ?>">
                <input type="hidden" name="assignment_category" id="assignmentCategory" value="<?= htmlspecialchars($assignment['category'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Your Name *</label>
                <input type="text" name="name" class="form-control" placeholder="Full name" required value="<?= postVal('name', $prefillName) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" required value="<?= postVal('email', $prefillEmail) ?>">
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
                <input type="text" name="preferred_start" class="form-control" placeholder="e.g. Next month, ASAP" value="<?= postVal('preferred_start') ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Why are you a good fit? *</label>
                <textarea name="message" class="form-control" rows="5" placeholder="Tell us about your background, relevant skills, and interest in this hands-on project..." required><?= postVal('message') ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Resume / CV *</label>
                <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                <p class="form-hint">Attach your Resume or CV in PDF, DOC, or DOCX format (max <?= (int) (MAX_UPLOAD_SIZE / 1024 / 1024) ?>MB).</p>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-cyber w-100"><i class="fas fa-paper-plane me-2"></i>Submit Application</button>
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
  var sel = document.getElementById('assignmentSelect');
  var title = document.getElementById('assignmentTitle');
  var type = document.getElementById('assignmentType');
  var category = document.getElementById('assignmentCategory');
  function syncHidden() {
    if (!sel || !sel.selectedOptions[0]) return;
    var o = sel.selectedOptions[0];
    if (title) title.value = o.dataset.title || '';
    if (type) type.value = o.dataset.type || '';
    if (category) category.value = o.dataset.category || '';
    var u = new URL(window.location.href);
    u.searchParams.set('assignment', sel.value);
    window.history.replaceState({}, '', u);
  }
  sel?.addEventListener('change', syncHidden);
})();
</script>
</body>
</html>
