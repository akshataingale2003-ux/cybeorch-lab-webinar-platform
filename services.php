<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
startSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php renderStandardViewport(); ?>
<title>CYBEORCH Services</title>
<?php renderPublicPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-orange:#ff6b35;}
*{box-sizing:border-box}
body{margin:0;background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif}
a{text-decoration:none}
.section{padding:5rem 0}
.hero-title{font-family:'Rajdhani',sans-serif;font-size:clamp(2.5rem,6vw,4.5rem);font-weight:700;line-height:1.1;margin-bottom:1rem;color:var(--cyber-text)}
.hero-title .accent{color:var(--cyber-accent)}
.hero-title .green{color:var(--cyber-green)}
.brand-cybeorch{color:#00d4ff}
.brand-labs{color:#00ff88}
.brand-academy{color:#ffd166}
.brand-studio{color:#ff6b35}
.brand-consult{color:#ffffff}
.service-card h3 .brand-cybeorch,.service-card h3 .accent{color:#00d4ff}
.hero-copy{color:var(--cyber-muted);max-width:760px;margin:0 auto;font-size:1.05rem;line-height:1.8}
.service-card{background:rgba(10,22,40,0.95);border:1px solid var(--cyber-border);border-radius:1rem;padding:2rem;height:100%}
.service-card h3{font-family:'Rajdhani',sans-serif;margin-bottom:1rem}
.service-card p{color:var(--cyber-muted);margin-bottom:1rem}
.service-list{list-style:none;padding:0;margin:0}
.service-list li{position:relative;padding-left:1.8rem;margin-bottom:.9rem;color:var(--cyber-text)}
.service-list li:before{content:'\f054';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;left:0;top:0;color:var(--cyber-accent)}
.contact-card{background:rgba(10,22,40,0.95);border:1px solid var(--cyber-border);border-radius:1rem;padding:2rem}
.form-control{background:rgba(255,255,255,0.04);border:1px solid var(--cyber-border);color:var(--cyber-text)}
.form-control:focus{border-color:var(--cyber-accent);box-shadow:0 0 0 .15rem rgba(0,212,255,.12);background:rgba(255,255,255,0.08)}
.btn-primary{background:var(--cyber-accent);border:none;box-shadow:0 12px 40px rgba(0,212,255,0.18)}
.fade-in{opacity:0;transform:translateY(20px);transition:all .6s ease}
.fade-in.visible{opacity:1;transform:translateY(0)}
@media(max-width:767px){.section{padding:3rem 0}}
</style>
</head>
<body>
<?php $navActive = $navActive ?? 'services'; require __DIR__ . '/includes/public-navbar.php'; ?>
<header class="section text-center">
  <div class="container">
    <h1 class="hero-title"><span class="accent">CYBEORCH</span> Services</h1>
    <p class="hero-copy">Discover the four divisions that power CYBEORCH: Labs, Academy, Studio, and Consulting. Each service line is built to help startups, user registrations & trainees, and enterprises launch secure products and scale digital teams.</p>
  </div>
</header>
<main>
  <section class="section" id="services-list">
    <div class="container">
      <div class="row g-4">
        <div class="col-md-6 col-lg-3">
          <div class="service-card h-100">
            <h3><span class="brand-cybeorch">CYBEORCH</span> <span class="brand-academy">LABS</span></h3>
            <p>Product engineering, cyber resilience, cloud apps, and SaaS delivery for startups and enterprises.</p>
            <ul class="service-list">
              <li>Software Development</li>
              <li>AI Solutions</li>
              <li>Web & Mobile Apps</li>
              <li>Cloud & APIs</li>
              <li>Cybersecurity</li>
              <li>Startup MVP Delivery</li>
              <li>Enterprise Integrations</li>
            </ul>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="service-card h-100">
            <h3><span class="brand-cybeorch">CYBEORCH</span> <span class="brand-academy">ACADEMY</span></h3>
            <p>Live bootcamps, internships, and training programs designed to build industry-ready talent.</p>
            <ul class="service-list">
              <li>30-Day Bootcamps</li>
              <li>Engineering Projects</li>
              <li>Live Internship Programs</li>
              <li>Corporate Training</li>
              <li>Client-Side Experience</li>
            </ul>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="service-card h-100">
            <h3><span class="brand-cybeorch">CYBEORCH</span> <span class="brand-academy">STUDIO</span></h3>
            <p>Startup productization and internal platform development across wallets, POS, marketplaces, and niche SaaS.</p>
            <ul class="service-list">
              <li>SaaS Products</li>
              <li>Internal Apps</li>
              <li>Platform Modules</li>
              <li>POS Systems</li>
              <li>Wallet Systems</li>
              <li>Marketplaces</li>
            </ul>
          </div>
        </div>
        <div class="col-md-6 col-lg-3">
          <div class="service-card h-100">
            <h3><span class="brand-cybeorch">CYBEORCH</span> <span class="brand-academy">CONSULTING</span></h3>
            <p>Technology advisory, automation, security roadmap, and digital transformation support.</p>
            <ul class="service-list">
              <li>IT Consulting</li>
              <li>Technology Advisory</li>
              <li>Digital Transformation</li>
              <li>ERP / POS Integration</li>
              <li>API Architecture</li>
              <li>Process Automation</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>
  <section class="section" id="contact">
    <div class="container">
      <div class="row g-4 align-items-center">
        <div class="col-lg-5">
          <h2>Start Your Project</h2>
          <p style="color:var(--cyber-muted);">Tell us about your requirements and weâ€™ll connect you with the right team for development, training, or consulting.</p>
          <p style="color:var(--cyber-muted);"><i class="fas fa-envelope me-2"></i>info@CYBEORCH.com</p>
        </div>
        <div class="col-lg-7">
          <div class="contact-card text-center" style="padding:2.5rem">
            <p style="color:var(--cyber-muted);line-height:1.8;margin-bottom:1.5rem">Use our dedicated forms to share project requirements or book a consulting session with our experts.</p>
            <a href="<?= url('start-project.php') ?>" class="btn btn-primary me-2 mb-2"><i class="fas fa-rocket me-2"></i>Start Your Project</a>
            <a href="<?= url('book-consulting.php') ?>" class="btn btn-outline-light mb-2"><i class="fas fa-calendar-check me-2"></i>Book Consulting</a>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php renderPublicFooter(); ?>

<?php renderSiteScripts(true); ?>
<script>
// Scroll animation
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
</script>
</body>
</html>
