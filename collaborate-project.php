<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/public-catalog.php';
require_once __DIR__ . '/includes/contact-messages.php';
require_once __DIR__ . '/includes/collaboration-inquiries.php';
require_once __DIR__ . '/includes/mailer.php';

startSession();

$navActive = 'live-projects';
$pageTitle = 'Collaborate on this Project';

$projectSlug = trim((string) ($_GET['slug'] ?? $_POST['project_slug'] ?? ''));
$project = $projectSlug !== '' ? publicFetchLiveProjectBySlug($projectSlug) : null;
$projectTitle = (string) ($project['title'] ?? ($_POST['project_title'] ?? ''));

$categoryOptions = [
    'Cyber Security',
    'Java Full Stack',
    'MERN Full Stack',
    'Data Science',
    'AI & ML',
    'FinTech',
    'Blockchain / Web3',
    'Mobile',
    'DevOps / Cloud',
    'Other',
];

$budgetOptions = [
    'Under ₹50,000',
    '₹50,000 – ₹2,00,000',
    '₹2,00,000 – ₹10,00,000',
    '₹10,00,000+',
    'Not sure yet',
];

$collabTypes = ['Startup', 'Freelance', 'Partnership', 'Internship', 'Other'];

function postv(string $k, string $default = ''): string
{
    return htmlspecialchars((string) ($_POST[$k] ?? $default), ENT_QUOTES, 'UTF-8');
}

function collabDisplayText(string $text): string
{
    return htmlspecialchars(html_entity_decode(trim($text), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

/** Encode asset paths safely (spaces, &, etc.) while keeping slashes. */
function assetUrl(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $parts = array_map('rawurlencode', explode('/', $relativePath));
    return url(implode('/', $parts));
}

function uploadsDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'collaboration';
}

function ensureUploadsDir(): void
{
    $dir = uploadsDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$msg = '';
$msgType = '';

// Anti-spam: create a timestamp on GET; require minimal fill time.
if (empty($_SESSION['collab_form_ts'])) {
    $_SESSION['collab_form_ts'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $msgType = 'error';
        $msg = 'Invalid request. Please refresh and try again.';
    } elseif (!empty($_POST['website'])) { // honeypot
        $msgType = 'error';
        $msg = 'Submission blocked.';
    } elseif (time() - (int) ($_SESSION['collab_form_ts'] ?? time()) < 3) {
        $msgType = 'error';
        $msg = 'Please take a moment and submit again.';
    } else {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = sanitize($_POST['phone'] ?? '');
        $company = sanitize($_POST['company'] ?? '');
        $projectTitlePost = sanitize($_POST['project_title'] ?? '');
        $projectCategory = sanitize($_POST['project_category'] ?? '');
        $budgetRange = sanitize($_POST['budget_range'] ?? '');
        $timeline = sanitize($_POST['timeline'] ?? '');
        $collabType = sanitize($_POST['collaboration_type'] ?? '');
        $description = trim((string) sanitize($_POST['description'] ?? ''));

        $errors = [];
        if (strlen($fullName) < 2) $errors[] = 'Full name is required.';
        if (!isValidEmail($email)) $errors[] = 'Please enter a valid email address.';
        if (strlen(preg_replace('/\\D+/', '', $phone) ?? '') < 8) $errors[] = 'Phone number is required.';
        if (strlen($projectTitlePost) < 3) $errors[] = 'Project title is required.';
        if ($projectCategory === '') $errors[] = 'Please select a project category.';
        if (strlen($description) < 10) $errors[] = 'Project description must be at least 10 characters.';
        if ($collabType === '') $errors[] = 'Please select a collaboration type.';

        // Optional upload
        $storedFileRel = '';
        $storedFileOriginal = '';
        if (!empty($_FILES['requirement_file']['name'])) {
            $file = $_FILES['requirement_file'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[] = 'Could not upload requirement file.';
            } else {
                $maxBytes = 5 * 1024 * 1024;
                if (($file['size'] ?? 0) > $maxBytes) {
                    $errors[] = 'Requirement file must be under 5MB.';
                } else {
                    $orig = (string) ($file['name'] ?? '');
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    $allowed = ['pdf','doc','docx','png','jpg','jpeg','webp'];
                    if (!in_array($ext, $allowed, true)) {
                        $errors[] = 'Unsupported file type. Upload PDF, DOC/DOCX, PNG, JPG, or WEBP.';
                    } else {
                        ensureUploadsDir();
                        $storedFileOriginal = $orig;
                        $safeBase = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($orig, PATHINFO_FILENAME)) ?: 'requirements';
                        $fileName = $safeBase . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $destAbs = uploadsDir() . DIRECTORY_SEPARATOR . $fileName;
                        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destAbs)) {
                            $errors[] = 'Could not save uploaded file.';
                        } else {
                            $storedFileRel = 'uploads/collaboration/' . $fileName;
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            $msgType = 'error';
            $msg = implode(' ', $errors);
        } else {
            // Save to dedicated table
            $id = dbTry(fn () => insertCollaborationInquiry([
                'project_slug' => $projectSlug !== '' ? $projectSlug : null,
                'project_title' => $projectTitlePost,
                'project_category' => $projectCategory,
                'collaboration_type' => $collabType,
                'budget_range' => $budgetRange,
                'timeline' => $timeline,
                'company' => $company,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'description' => $description,
                'requirement_file' => $storedFileRel !== '' ? $storedFileRel : null,
                'requirement_file_original' => $storedFileOriginal !== '' ? $storedFileOriginal : null,
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]), 0);

            // Also store to contact_messages so it appears in Admin -> Messages
            $message = "Project title: {$projectTitlePost}\n"
                . "Category: {$projectCategory}\n"
                . ($budgetRange !== '' ? "Budget: {$budgetRange}\n" : '')
                . ($timeline !== '' ? "Timeline: {$timeline}\n" : '')
                . "Collaboration type: {$collabType}\n"
                . ($company !== '' ? "Company: {$company}\n" : '')
                . ($projectSlug !== '' ? "Live project slug: {$projectSlug}\n" : '')
                . ($storedFileRel !== '' ? "Requirement file: {$storedFileRel}\n" : '')
                . "\n---\n\n"
                . $description;

            dbTry(fn () => insertContactMessage($fullName, $email, $phone, 'Project Collaboration', $message, 'collaborate-project.php', 'collaborate-project'), null);

            // Email admin notification (best-effort)
            $mailSubject = 'New collaboration inquiry — ' . $projectTitlePost;
            $html = '<div style="font-family:Arial,sans-serif;max-width:720px;margin:0 auto;padding:18px">'
                . '<h2 style="margin:0 0 10px;color:#0a1628">New collaboration inquiry</h2>'
                . '<p><strong>Name:</strong> ' . htmlspecialchars($fullName) . '<br>'
                . '<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>'
                . '<strong>Phone:</strong> ' . htmlspecialchars($phone) . '<br>'
                . '<strong>Company:</strong> ' . htmlspecialchars($company) . '</p>'
                . '<p><strong>Project:</strong> ' . htmlspecialchars($projectTitlePost) . '<br>'
                . '<strong>Category:</strong> ' . htmlspecialchars($projectCategory) . '<br>'
                . '<strong>Type:</strong> ' . htmlspecialchars($collabType) . '<br>'
                . '<strong>Budget:</strong> ' . htmlspecialchars($budgetRange) . '<br>'
                . '<strong>Timeline:</strong> ' . htmlspecialchars($timeline) . '</p>'
                . ($storedFileRel !== '' ? '<p><strong>Requirement file:</strong> ' . htmlspecialchars(SITE_URL . '/' . $storedFileRel) . '</p>' : '')
                . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:14px 0">'
                . '<pre style="white-space:pre-wrap;background:#f7f7f7;padding:12px;border-radius:8px">' . htmlspecialchars($description) . '</pre>'
                . '<p style="color:#64748b;font-size:12px;margin-top:12px">Inquiry ID: ' . (int) $id . '</p>'
                . '</div>';

            $text = $mailSubject . "\n\n" . $message;
            try {
                sendAdminNotificationEmail($mailSubject, $html, $text, ADMIN_EMAIL);
            } catch (Throwable $e) {
                // best-effort: ignore email errors (SMTP not configured, composer missing, etc.)
            }

            // Reset timer + show success
            $_SESSION['collab_form_ts'] = time();
            $msgType = 'success';
            $msg = 'Thank you! Your collaboration request has been submitted.';
            $_POST = []; // clear form
        }
    }
}

$heroTitle = 'Collaborate on this Project';
$heroSubtitle = 'A professional collaboration request form—share scope, timeline, and requirements. Our team will respond within 24 hours.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <?php renderStandardViewport(); ?>
  <title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LABS</title>
  <meta name="description" content="Collaborate on a CYBEORCH live project. Submit scope, timeline, budget, and requirements.">
  <?php renderPublicPageHead(); ?>
  <style>
    :root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);}
    body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;overflow-x:hidden}
    body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}
    .page-hero{padding:3rem 0 1.25rem;text-align:center;position:relative;z-index:1}
    .page-hero h1{font-family:'Rajdhani',sans-serif;font-size:clamp(2rem,5vw,3rem);font-weight:700;margin-bottom:.75rem}
    .page-hero h1 .accent{color:var(--cyber-accent)}
    .page-hero p{color:#ffffff;max-width:760px;margin:0 auto;line-height:1.75}
    .section{padding:2rem 0 4rem;position:relative;z-index:1}
    .section-title{font-family:'Rajdhani',sans-serif;font-size:clamp(1.6rem,4vw,2.2rem);font-weight:700;color:#fff}
    .section-title .accent{color:var(--cyber-accent)}
    .divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin:.75rem 0 1.25rem}

    .collab-wrap{display:grid;grid-template-columns:1fr;gap:1.5rem}
    @media(min-width:992px){.collab-wrap{grid-template-columns:0.9fr 1.1fr;gap:2rem}}

    .collab-context-card .collab-context-intro{color:rgba(255,255,255,0.92);font-size:.92rem;line-height:1.7}
    .collab-context-card .collab-context-name{font-weight:700;font-size:1.08rem;color:#fff !important;line-height:1.35;margin:0}
    .collab-context-card .collab-context-desc{color:#fff !important;margin-top:.4rem;line-height:1.65;font-size:.9rem}
    .collab-context-card .collab-context-meta{margin-top:1rem;color:#fff !important;font-size:.88rem;display:flex;flex-direction:column;gap:.35rem}
    .collab-context-card .collab-context-meta div{color:#fff !important;display:flex;align-items:flex-start;gap:.45rem;line-height:1.5}
    .collab-context-card .collab-context-meta i{color:#fff !important;opacity:.95;margin-top:.15rem;flex-shrink:0}
    .collab-context-card .collab-context-status{display:inline-block;margin-top:.15rem;padding:.2rem .65rem;border-radius:4px;font-size:.72rem;font-weight:700;text-transform:uppercase;color:#fff !important;background:rgba(0,255,136,0.2);border:1px solid rgba(0,255,136,0.35)}
    .collab-context-card .collab-context-empty{color:rgba(255,255,255,0.9);font-size:.92rem;line-height:1.7}

    .card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;backdrop-filter:blur(10px);overflow:hidden}
    .card:hover{border-color:rgba(0,212,255,0.45);box-shadow:0 16px 50px rgba(0,212,255,0.10);transform:translateY(-2px)}
    .card{transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease}
    .card-head{padding:1.25rem 1.35rem;border-bottom:1px solid rgba(0,212,255,0.12);background:linear-gradient(135deg,rgba(0,212,255,0.10),rgba(0,255,136,0.06))}
    .card-body{padding:1.35rem}

    .alertx{padding:.85rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:.92rem;border:1px solid transparent}
    .alertx.success{background:rgba(0,255,136,0.10);border-color:rgba(0,255,136,0.28);color:var(--cyber-green)}
    .alertx.error{background:rgba(255,68,68,0.10);border-color:rgba(255,68,68,0.28);color:#ff6666}

    .form-label{color:var(--cyber-muted);font-size:.85rem;margin-bottom:.4rem}
    .form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:10px!important;padding:.8rem 1rem!important}
    .form-control:focus,.form-select:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.10)!important}
    .form-text{color:var(--cyber-muted)!important}
    .btn-primary-cyber{background:var(--cyber-accent);color:#050b18;border:none;padding:.85rem 1.2rem;border-radius:10px;font-weight:800;transition:all .2s;cursor:pointer}
    .btn-primary-cyber:hover{background:var(--cyber-green);transform:translateY(-1px)}

    .reveal{opacity:0;transform:translateY(10px);animation:reveal .35s ease-out forwards}
    @keyframes reveal{to{opacity:1;transform:translateY(0)}}
  </style>
  <?php renderFormSelectStyles(); ?>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1><span style="color:#fff">Collaborate</span> on this <span style="color:#fff">Project</span></h1>
    <p><?= htmlspecialchars($heroSubtitle) ?></p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <div class="collab-wrap">
      <div class="card collab-context-card reveal">
        <div class="card-head">
          <div class="section-title">Project <span class="accent">Context</span></div>
          <div class="collab-context-intro">
            <?php if ($projectTitle !== ''): ?>
              You are collaborating on <strong style="color:#fff"><?= collabDisplayText($projectTitle) ?></strong>.
            <?php else: ?>
              You can submit collaboration without selecting a project.
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body">
          <?php if ($project): ?>
            <h2 class="collab-context-name"><?= collabDisplayText((string) $project['title']) ?></h2>
            <?php
              $contextDesc = trim((string) ($project['short_desc'] ?: $project['description'] ?? ''));
              if ($contextDesc !== ''):
            ?>
            <p class="collab-context-desc"><?= collabDisplayText($contextDesc) ?></p>
            <?php endif; ?>
            <div class="collab-context-meta">
              <?php if (!empty($project['category'])): ?>
              <div><i class="fas fa-tag"></i><?= collabDisplayText((string) $project['category']) ?></div>
              <?php endif; ?>
              <?php if (!empty($project['stack'])): ?>
              <div><i class="fas fa-layer-group"></i><?= collabDisplayText((string) $project['stack']) ?></div>
              <?php endif; ?>
              <?php if (!empty($project['status'])): ?>
              <div><span class="collab-context-status"><?= collabDisplayText((string) $project['status']) ?></span></div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="collab-context-empty">
              Tip: open the live projects page and click “Collaborate on this project” to pre-fill the project context.
              <div style="margin-top:.75rem">
                <a href="<?= url('live-projects.php') ?>" style="color:var(--cyber-accent)"><i class="fas fa-arrow-left me-1"></i>Back to live projects</a>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card reveal" style="animation-delay:.06s">
        <div class="card-head">
          <div class="section-title">Collaborate <span class="accent">Now</span></div>
          <div style="color:var(--cyber-muted);font-size:.92rem;line-height:1.7">
            Fill the form with your requirements. We’ll respond within 24 hours.
          </div>
        </div>
        <div class="card-body">
          <?php if ($msg !== ''): ?>
            <div class="alertx <?= $msgType === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>

          <form id="collabForm" method="POST" enctype="multipart/form-data" action="<?= htmlspecialchars(url('collaborate-project.php' . ($projectSlug !== '' ? '?slug=' . urlencode($projectSlug) : ''))) ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="project_slug" value="<?= htmlspecialchars($projectSlug) ?>">
            <div style="display:none">
              <label>Website</label>
              <input type="text" name="website" value="">
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input class="form-control" type="text" name="full_name" required value="<?= postv('full_name') ?>" placeholder="Your full name">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input class="form-control" type="email" name="email" required value="<?= postv('email') ?>" placeholder="you@example.com">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input class="form-control" type="tel" name="phone" required value="<?= postv('phone') ?>" placeholder="+91 XXXXX XXXXX">
              </div>
              <div class="col-md-6">
                <label class="form-label">Company/Organization Name</label>
                <input class="form-control" type="text" name="company" value="<?= postv('company') ?>" placeholder="Company name">
              </div>

              <div class="col-md-6">
                <label class="form-label">Project Title *</label>
                <input class="form-control" type="text" name="project_title" required value="<?= postv('project_title', $projectTitle) ?>" placeholder="e.g. FinTech Credits System">
              </div>
              <div class="col-md-6">
                <label class="form-label">Project Category *</label>
                <select class="form-select" name="project_category" required>
                  <option value="">Select category</option>
                  <?php foreach ($categoryOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"<?= postv('project_category') === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Budget Range</label>
                <select class="form-select" name="budget_range">
                  <option value="">Select budget</option>
                  <?php foreach ($budgetOptions as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"<?= postv('budget_range') === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Timeline/Deadline</label>
                <input class="form-control" type="text" name="timeline" value="<?= postv('timeline') ?>" placeholder="e.g. 3 months / ASAP / Q3 2026">
              </div>

              <div class="col-md-6">
                <label class="form-label">Collaboration Type *</label>
                <select class="form-select" name="collaboration_type" required>
                  <option value="">Select type</option>
                  <?php foreach ($collabTypes as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"<?= postv('collaboration_type') === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Upload Requirement File (optional)</label>
                <input class="form-control" type="file" name="requirement_file" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp">
                <div class="form-text">Max 5MB. PDF/DOC/DOCX/PNG/JPG/WEBP.</div>
              </div>

              <div class="col-12">
                <label class="form-label">Project Description *</label>
                <textarea class="form-control" name="description" rows="6" required placeholder="Describe scope, features, deliverables, constraints, and collaboration expectations."><?= postv('description') ?></textarea>
              </div>

              <div class="col-12">
                <button class="btn-primary-cyber w-100" type="submit">
                  <i class="fas fa-handshake me-2"></i>Collaborate Now
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  var form = document.getElementById('collabForm');
  if(!form) return;
  form.addEventListener('submit', function(e){
    var required = ['full_name','email','phone','project_title','project_category','collaboration_type','description'];
    for (var i=0;i<required.length;i++){
      var el = form.querySelector('[name=\"'+required[i]+'\"]');
      if(!el) continue;
      if(!String(el.value||'').trim()){
        el.focus();
        e.preventDefault();
        return;
      }
    }
    var file = form.querySelector('[name=\"requirement_file\"]');
    if(file && file.files && file.files[0]){
      if(file.files[0].size > 5*1024*1024){
        alert('Requirement file must be under 5MB.');
        e.preventDefault();
      }
    }
  });
})();
</script>
</body>
</html>

