<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

startSession();

$loggedIn = isLoggedIn();
$currentUser = null;
if ($loggedIn) {
    $currentUser = db()->fetchOne('SELECT * FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
}

$error = '';
$refCode = sanitize($_GET['ref'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        require_once __DIR__ . '/includes/resume-upload.php';
        $resumeResult = validateAndStoreResumeUpload($_FILES['resume'] ?? []);
        if (!$resumeResult['ok']) {
            $error = $resumeResult['error'];
        } else {
            $userId = $loggedIn ? (int) $_SESSION['user_id'] : null;
            $postData = $_POST;
            $postData['resume_path'] = $resumeResult['path'];
            $postData['resume_original_name'] = $resumeResult['original_name'];
            $result = Auth::registerFreelancer($postData, $userId);
            if ($result['success']) {
                if (!$loggedIn && !empty($_POST['email']) && !empty($_POST['password'])) {
                    Auth::login($_POST['email'], $_POST['password']);
                }
                header('Location: ' . url('freelancer-register-success.php'));
                exit;
            }
            $error = $result['message'];
        }
    }
}

$roleLabels = [
    'developer'      => 'Software Developer',
    'designer'       => 'UI/UX Designer',
    'cybersecurity'  => 'Cybersecurity',
    'devops'         => 'DevOps / Cloud',
    'qa'             => 'QA / Testing',
    'data'           => 'Data / AI',
    'mobile'         => 'Mobile Developer',
    'other'          => 'Other',
];

require_once __DIR__ . '/includes/admin-schema.php';
$appliedRoles = [];
if ($loggedIn && $currentUser) {
    $appliedRoles = getFreelancerAppliedRoles((int) $currentUser['id']);
} elseif (!empty($_POST['email'])) {
    $appliedRoles = getFreelancerAppliedRoles(null, (string) $_POST['email']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>Register as Freelancer – CYBEORCH</title>
<?php renderAuthPageHead(); ?>
<style>
:root {
  --cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-blue:#0f3460;
  --cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-muted:#7a8fa6;
  --cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.3);
}
*{box-sizing:border-box;margin:0;padding:0;}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;min-height:100vh;padding:2rem 1rem;}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;}
.auth-container{width:100%;max-width:680px;margin:0 auto;position:relative;z-index:1;}
.auth-logo{display:block;text-align:center;margin-bottom:0.25rem;text-decoration:none;}
.auth-logo img{max-width:200px;width:100%;height:auto;filter:drop-shadow(0 8px 22px rgba(0,0,0,.35));}
.auth-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:16px;padding:2.5rem;backdrop-filter:blur(10px);margin-top:1.5rem;}
.auth-title{font-family:'Rajdhani',sans-serif;font-size:1.6rem;font-weight:700;text-align:center;margin-bottom:0.25rem;}
.auth-subtitle{color:var(--cyber-muted);font-size:0.88rem;text-align:center;margin-bottom:1.5rem;line-height:1.6;}
.form-label{color:var(--cyber-muted);font-size:0.85rem;margin-bottom:0.4rem;}
.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:8px!important;padding:0.75rem 1rem!important;}
.form-control:focus,.form-select:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.1)!important;outline:none!important;}
.form-control::placeholder{color:var(--cyber-muted)!important;}
.input-group .form-control{border-radius:8px 0 0 8px!important;}
.input-group .btn{background:rgba(255,255,255,0.05);border:1px solid var(--cyber-border);border-left:none;color:var(--cyber-muted);border-radius:0 8px 8px 0;}
.input-group .btn:hover{color:var(--cyber-accent);}
.btn-submit{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.85rem;border-radius:8px;font-weight:700;font-size:1rem;width:100%;transition:all 0.2s;margin-top:1rem;}
.btn-submit:hover{background:var(--cyber-green);transform:translateY(-1px);box-shadow:0 8px 24px rgba(0,255,136,0.25);}
.btn-outline-link{display:inline-block;margin-top:1rem;color:var(--cyber-accent);font-size:0.88rem;text-decoration:none;}
.btn-outline-link:hover{color:var(--cyber-green);}
.auth-footer{text-align:center;margin-top:1.5rem;color:var(--cyber-muted);font-size:0.88rem;}
.auth-footer a{color:var(--cyber-accent);text-decoration:none;font-weight:500;}
.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;}
.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}
.info-badge{background:rgba(0,212,255,0.08);border:1px solid rgba(0,212,255,0.2);border-radius:8px;padding:0.85rem 1rem;margin-bottom:1.5rem;font-size:0.85rem;color:var(--cyber-accent);display:flex;align-items:flex-start;gap:0.75rem;line-height:1.5;}
.logged-badge{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.2);border-radius:8px;padding:0.75rem 1rem;margin-bottom:1.5rem;font-size:0.85rem;color:#00ff88;}
.section-heading{font-family:'Rajdhani',sans-serif;font-size:1rem;font-weight:600;color:var(--cyber-accent);margin:1.25rem 0 0.75rem;padding-bottom:0.35rem;border-bottom:1px solid var(--cyber-border);}
textarea.form-control{min-height:90px;resize:vertical;}
</style>
<?php renderFormSelectStyles(); ?>
</head>
<body>
<div class="auth-container">
  <a href="<?= url('freelancer.php') ?>" class="auth-logo" aria-label="CYBEORCH Freelancer Network">
    <img src="<?= url('assets/images/Footer_logo.jpeg') ?>" alt="CYBEORCH">
  </a>
  <div class="auth-card">
    <h1 class="auth-title">Register as a Freelancer</h1>
    <p class="auth-subtitle">Join CYBEORCH for client projects, product development, and remote collaboration on software, cybersecurity, and AI hands-on projects.</p>

    <div class="info-badge">
      <i class="fas fa-briefcase mt-1"></i>
      <span>Selected freelancers may work on live client tasks, internal products, startup support, and deployment projects. Outstanding performers may receive on-roll opportunities.</span>
    </div>

    <?php if ($loggedIn && $currentUser): ?>
    <div class="logged-badge"><i class="fas fa-check-circle me-2"></i>Logged in as <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<?= htmlspecialchars($currentUser['email']) ?>)</div>
    <?php endif; ?>

    <?php if ($appliedRoles): ?>
    <div class="info-badge">
      <i class="fas fa-layer-group mt-1"></i>
      <span>You have already applied for:
        <strong><?= htmlspecialchars(implode(', ', array_map(static fn ($r) => $roleLabels[$r] ?? ucfirst($r), $appliedRoles))) ?></strong>.
        You may submit a new application for a different role below.
      </span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <?php if ($loggedIn && $currentUser): ?>
      <input type="hidden" name="full_name" value="<?= htmlspecialchars($currentUser['full_name']) ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars($currentUser['email']) ?>">
      <?php endif; ?>

      <div class="section-heading"><i class="fas fa-user me-2"></i>Personal Details</div>

      <?php if (!$loggedIn): ?>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Full Name *</label>
          <input type="text" name="full_name" class="form-control" placeholder="Your full name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="you@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number *</label>
          <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">City / Location</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Mumbai, Remote" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>
      </div>
      <?php else: ?>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile" value="<?= htmlspecialchars($_POST['phone'] ?? ($currentUser['phone'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">City / Location</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Mumbai, Remote" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>
      </div>
      <?php endif; ?>

      <div class="section-heading"><i class="fas fa-code me-2"></i>Professional Profile</div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Primary Role *</label>
          <select name="primary_role" id="primaryRole" class="form-select" required>
            <option value="">Select role</option>
            <?php foreach ($roleLabels as $val => $label): ?>
            <?php $alreadyApplied = in_array($val, $appliedRoles, true); ?>
            <option value="<?= $val ?>" <?= ($_POST['primary_role'] ?? '') === $val ? 'selected' : '' ?> <?= $alreadyApplied ? 'disabled' : '' ?>><?= htmlspecialchars($label) ?><?= $alreadyApplied ? ' (already applied)' : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Experience Level *</label>
          <select name="experience_level" class="form-select" required>
            <option value="">Select experience</option>
            <option value="fresher" <?= ($_POST['experience_level'] ?? '') === 'fresher' ? 'selected' : '' ?>>Fresher / User Registration & Trainee</option>
            <option value="1-2" <?= ($_POST['experience_level'] ?? '') === '1-2' ? 'selected' : '' ?>>1–2 years</option>
            <option value="3-5" <?= ($_POST['experience_level'] ?? '') === '3-5' ? 'selected' : '' ?>>3–5 years</option>
            <option value="5+" <?= ($_POST['experience_level'] ?? '') === '5+' ? 'selected' : '' ?>>5+ years</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Availability *</label>
          <select name="availability" class="form-select" required>
            <option value="">Select availability</option>
            <option value="full_time" <?= ($_POST['availability'] ?? '') === 'full_time' ? 'selected' : '' ?>>Full-time</option>
            <option value="part_time" <?= ($_POST['availability'] ?? '') === 'part_time' ? 'selected' : '' ?>>Part-time</option>
            <option value="project_based" <?= ($_POST['availability'] ?? '') === 'project_based' ? 'selected' : '' ?>>Project-based</option>
          </select>
        </div>
      </div>

      <div class="mb-3 mt-3">
        <label class="form-label">Skills &amp; Tech Stack *</label>
        <textarea name="skills" class="form-control" placeholder="e.g. PHP, React, Python, AWS, Penetration Testing, Figma..." required><?= htmlspecialchars($_POST['skills'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">About You *</label>
        <textarea name="about" class="form-control" placeholder="Brief introduction, past projects, and what kind of hands-on projects you are looking for..." required><?= htmlspecialchars($_POST['about'] ?? '') ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Resume / CV *</label>
        <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
        <p style="font-size:0.8rem;color:var(--cyber-muted);margin-top:0.35rem;line-height:1.45">Attach your Resume or CV in PDF, DOC, or DOCX format (max <?= (int) (MAX_UPLOAD_SIZE / 1024 / 1024) ?>MB).</p>
      </div>

      <div class="section-heading"><i class="fas fa-link me-2"></i>Portfolio Links <span style="color:var(--cyber-muted);font-weight:400;font-size:0.8rem">(optional)</span></div>

      <div class="row g-3 mb-3">
        <div class="col-md-4">
          <label class="form-label">Portfolio URL</label>
          <input type="url" name="portfolio_url" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($_POST['portfolio_url'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">GitHub</label>
          <input type="url" name="github_url" class="form-control" placeholder="https://github.com/..." value="<?= htmlspecialchars($_POST['github_url'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">LinkedIn</label>
          <input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/..." value="<?= htmlspecialchars($_POST['linkedin_url'] ?? '') ?>">
        </div>
      </div>

      <?php if (!$loggedIn): ?>
      <div class="section-heading"><i class="fas fa-lock me-2"></i>Account Access</div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <div class="input-group">
            <input type="password" name="password" id="password" class="form-control" placeholder="Min 8 chars, 1 uppercase, 1 number" required>
            <button type="button" class="btn" onclick="togglePass('password','eyePass')"><i class="fas fa-eye" id="eyePass"></i></button>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm Password *</label>
          <div class="input-group">
            <input type="password" name="confirm_password" id="confirm_pass" class="form-control" placeholder="Repeat password" required>
            <button type="button" class="btn" onclick="togglePass('confirm_pass','eyeConfirm')"><i class="fas fa-eye" id="eyeConfirm"></i></button>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Referral Code <span style="color:var(--cyber-muted)">(optional)</span></label>
          <input type="text" name="referral_code" class="form-control" placeholder="Friend's referral code" value="<?= htmlspecialchars($refCode) ?>">
        </div>
      </div>
      <?php endif; ?>

      <div class="mb-3 mt-3 d-flex align-items-start gap-2">
        <input type="checkbox" name="agree_terms" id="terms" required style="margin-top:3px;accent-color:var(--cyber-accent)">
        <label for="terms" style="font-size:0.82rem;color:var(--cyber-muted)">I agree to the <a href="<?= url('terms.php') ?>" style="color:var(--cyber-accent)">Terms of Service</a> and <a href="<?= url('privacy.php') ?>" style="color:var(--cyber-accent)">Privacy Policy</a></label>
      </div>

      <button type="submit" class="btn-submit"><i class="fas fa-paper-plane me-2"></i>Submit Freelancer Application</button>
    </form>

    <p class="text-center mb-0">
      <a href="<?= url('freelancer.php') ?>" class="btn-outline-link"><i class="fas fa-users me-1"></i>View Freelancer Network</a>
    </p>
  </div>
  <div class="auth-footer">
    <?php if ($loggedIn): ?>
    <a href="<?= url('dashboard.php') ?>">Back to Dashboard</a>
    <?php elseif (isPublicAuthEnabled()): ?>
    Already have an account? <a href="<?= url('login.php') ?>">Sign in here</a>
    <?php endif; ?>
  </div>
</div>

<script>
function togglePass(id, iconId) {
  const inp = document.getElementById(id);
  const icon = document.getElementById(iconId);
  if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
}
document.querySelector('form')?.addEventListener('submit', function(e) {
  const confirm = document.getElementById('confirm_pass');
  const pass = document.getElementById('password');
  if (pass && confirm && pass.value !== confirm.value) {
    e.preventDefault();
    alert('Passwords do not match!');
    return;
  }

  const appliedRoles = <?= json_encode(array_values($appliedRoles), JSON_UNESCAPED_UNICODE) ?>;
  const roleSelect = document.getElementById('primaryRole');
  if (roleSelect && appliedRoles.indexOf(roleSelect.value) !== -1) {
    e.preventDefault();
    alert('You have already applied for this freelancer role.');
  }
});
</script>
<?php renderSiteScripts(); ?>
</body>
</html>
