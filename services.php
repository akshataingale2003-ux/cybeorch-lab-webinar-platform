<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/training-public.php';
startSession();

$serviceOfferings = [
    [
        'title' => 'Cybersecurity',
        'image' => 'assets/images/Cybersecurity.png',
        'desc'  => 'Protecting what matters most with threat detection, data protection, network security, and secure access.',
    ],
    [
        'title' => 'Web Development',
        'image' => 'assets/images/Web Development.png',
        'desc'  => 'Building responsive, fast, and SEO-friendly websites with clean code and modern frameworks.',
    ],
    [
        'title' => 'Cloud Solutions',
        'image' => 'assets/images/Cloud Solutions.png',
        'desc'  => 'Scalable, secure, and reliable cloud consulting, migration, and managed services.',
    ],
    [
        'title' => 'AI Automation',
        'image' => 'assets/images/AI Automation.png',
        'desc'  => 'Intelligent automation and AI-powered workflows to boost productivity and business growth.',
    ],
    [
        'title' => 'Training & Internship',
        'image' => 'assets/images/Training & Internship.png',
        'desc'  => 'Learn, practice, and grow with expert-led training, real projects, and internship opportunities.',
    ],
    [
        'title' => 'Blockchain Solutions',
        'image' => 'assets/images/Blockchain Solutions.png',
        'desc'  => 'Secure, transparent, and scalable blockchain solutions that build trust and deliver value.',
    ],
];
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
.section-title{font-family:'Rajdhani',sans-serif;font-size:clamp(2rem,4vw,2.8rem);font-weight:700;margin-bottom:.5rem;color:var(--cyber-text)}
.section-title .accent{color:var(--cyber-accent)}
.section-subtitle{color:var(--cyber-muted);font-size:1rem;margin-bottom:2.5rem;line-height:1.7}
.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin:.75rem auto 1rem;border-radius:2px}
.section-our-services{padding-top:0;padding-bottom:4rem}
@media(max-width:767px){.section{padding:3rem 0}.section-our-services{padding-bottom:3rem}}
</style>
<?php renderCatalogImageCardStyles(); ?>
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
            <p>Live bootcamps, internships, and software development company programs designed to build industry-ready talent.</p>
            <ul class="service-list">
              <li>30-Day Bootcamps</li>
              <li>Engineering Projects</li>
              <li>Live Internship Programs</li>
              <li>Corporate Software Development Company</li>
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

  <section class="section section-our-services" id="our-services">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="section-title">Our <span class="accent">Services</span></h2>
        <div class="divider mx-auto"></div>
        <p class="section-subtitle mb-0">Empowering businesses with cutting-edge technology solutions and digital transformation services</p>
      </div>
      <div class="row g-4">
        <?php foreach ($serviceOfferings as $service): ?>
        <div class="col-12 col-md-6 col-lg-4 fade-in">
          <article class="service-image-card h-100">
            <div class="service-image-wrap">
              <img
                src="<?= catalogImageUrl($service['image']) ?>"
                alt="<?= htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8') ?> — CYBEORCH"
                class="service-image-media"
                loading="lazy"
                decoding="async"
              >
            </div>
            <div class="service-image-body">
              <h3 class="service-image-title"><?= htmlspecialchars($service['title']) ?></h3>
              <p class="service-image-desc"><?= htmlspecialchars($service['desc']) ?></p>
            </div>
          </article>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <section class="section" id="contact">
    <div class="container">
      <div class="row g-4 align-items-center">
        <div class="col-lg-5">
          <h2>Start Your Project</h2>
          <p style="color:var(--cyber-muted);">Tell us about your requirements and weâ€™ll connect you with the right team for development, software development company, or consulting.</p>
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
