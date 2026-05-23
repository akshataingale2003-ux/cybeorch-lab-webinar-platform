<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';

startSession();

$pageTitle = 'Careers';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LAB</title>
<meta name="description" content="Explore careers at CYBEORCH — startup roles, consulting opportunities, and college collaborations.">
<?php renderPublicPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.4);}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}
a{text-decoration:none}
.section{padding:4rem 0;position:relative;z-index:1}
.page-hero{padding:4rem 0 1rem;text-align:center}
.page-hero h1{font-family:'Rajdhani',sans-serif;font-size:clamp(2.2rem,5vw,3.5rem);font-weight:700;margin-bottom:1rem}
.page-hero h1 .accent{color:var(--cyber-accent)}
.page-hero p{color:var(--cyber-muted);max-width:720px;margin:0 auto 2rem;line-height:1.8}
.career-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:2rem}
.career-card i{font-size:1.75rem;color:var(--cyber-accent);margin-bottom:1rem}
.career-card h2,.career-card h3{font-family:'Rajdhani',sans-serif;margin-bottom:.75rem}
.career-card h2{font-size:1.75rem}
.career-card h3{font-size:1.2rem;color:var(--cyber-green);margin-top:1.25rem}
.career-card p,.career-card li{color:var(--cyber-muted);line-height:1.7}
.career-list{list-style:none;padding:0;margin:1rem 0 0}
.career-list li{position:relative;padding-left:1.5rem;margin-bottom:.6rem}
.career-list li::before{content:'\f054';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;left:0;color:var(--cyber-accent);font-size:.7rem;top:.35rem}
.btn-primary-cyber{display:inline-block;background:var(--cyber-accent);color:#050b18;padding:.75rem 1.5rem;border-radius:6px;font-weight:700;transition:all .2s;margin-top:1rem}
.btn-primary-cyber:hover{background:var(--cyber-green);color:#050b18}
@media(max-width:767px){.section{padding:2.5rem 0}}
</style>
</head>
<body>

<?php $navActive = 'about'; require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Careers at <span class="brand-mark"><?= brandMark() ?></span></h1>
    <p>Join our ecosystem across startups, consulting, and education. Build secure products, mentor learners, and grow your career in cybersecurity and technology.</p>
  </div>
</header>

<main>
  <section class="section pt-0" id="startup">
    <div class="container">
      <div class="career-card">
        <i class="fas fa-rocket"></i>
        <h2>Startup Careers</h2>
        <p>Work with early-stage teams inside the CYBEORCH Studio and Labs divisions. Help ship MVPs, integrate security from day one, and scale products across fintech, edtech, and SaaS.</p>
        <h3>Open roles</h3>
        <ul class="career-list">
          <li>Full-Stack Developer (PHP / React)</li>
          <li>Junior Cybersecurity Analyst</li>
          <li>UI/UX Designer</li>
          <li>DevOps & Cloud Intern</li>
          <li>Product Management Intern</li>
        </ul>
        <a href="<?= url('contact.php') ?>?subject=Startup+Career" class="btn-primary-cyber">Apply for Startup Roles</a>
      </div>
    </div>
  </section>

  <section class="section pt-0" id="consulting">
    <div class="container">
      <div class="career-card">
        <i class="fas fa-briefcase" style="color:var(--cyber-green)"></i>
        <h2>Consulting Careers</h2>
        <p>Join CYBEORCH Consulting to advise enterprises on digital transformation, API architecture, ERP/POS integrations, and security roadmaps.</p>
        <h3>Open roles</h3>
        <ul class="career-list">
          <li>IT Consultant</li>
          <li>Security Advisory Associate</li>
          <li>Business Analyst</li>
          <li>Technical Project Coordinator</li>
          <li>Automation Engineer</li>
        </ul>
        <a href="<?= url('contact.php') ?>?subject=Consulting+Career" class="btn-primary-cyber">Apply for Consulting Roles</a>
      </div>
    </div>
  </section>

  <section class="section pt-0" id="college">
    <div class="container">
      <div class="career-card">
        <i class="fas fa-university"></i>
        <h2>College Collaborations</h2>
        <p>Partner with CYBEORCH Academy as a campus ambassador, training coordinator, or workshop facilitator. Bring industry-ready cybersecurity programs to your institution.</p>
        <h3>Opportunities</h3>
        <ul class="career-list">
          <li>Campus Ambassador Program</li>
          <li>Workshop & Webinar Host</li>
          <li>Internship Program Coordinator</li>
          <li>College Partnership Lead</li>
          <li>User Registration & Trainee Mentor (Bootcamp)</li>
        </ul>
        <a href="<?= url('contact.php') ?>?subject=College+Collaboration" class="btn-primary-cyber">Partner With Us</a>
      </div>
    </div>
  </section>
</main>

<?php renderPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
