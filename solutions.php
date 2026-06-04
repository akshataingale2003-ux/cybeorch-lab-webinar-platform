<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';

startSession();

$navActive = 'solutions';

$solutionAreas = [
    [
        'title' => 'Event platforms',
        'icon'  => 'fa-calendar-check',
        'desc'  => 'End-to-end digital infrastructure for conferences, expos, and hybrid experiences—from registration to live streaming and analytics.',
        'bullets' => [
            'Ticketing & access control',
            'Agenda, speakers, and networking',
            'Live / hybrid streaming hooks',
            'Sponsor & exhibitor portals',
        ],
    ],
    [
        'title' => 'EV Ecosystems',
        'icon'  => 'fa-charging-station',
        'desc'  => 'Software layers for charging, fleet, energy ops, and partner integrations across the electric mobility value chain.',
        'bullets' => [
            'Station & session management',
            'Fleet dashboards & billing',
            'OCPP / OEM integrations (as needed)',
            'Operator & partner APIs',
        ],
    ],
    [
        'title' => 'FinTech Solutions',
        'icon'  => 'fa-building-columns',
        'desc'  => 'Secure payments, wallets, lending workflows, and compliance-aware architectures for regulated financial products.',
        'bullets' => [
            'Payments & ledger patterns',
            'KYC / risk-aware flows (policy-driven)',
            'Reconciliation & reporting',
            'Banking & PSP integration paths',
        ],
    ],
    [
        'title' => 'Healthcare Systems',
        'icon'  => 'fa-heart-pulse',
        'desc'  => 'Clinical workflows, patient engagement, and operations tooling with privacy and reliability as first-class concerns.',
        'bullets' => [
            'Scheduling & care coordination',
            'EHR-friendly integrations',
            'Patient portals & reminders',
            'Audit trails & role-based access',
        ],
    ],
    [
        'title' => 'E-commerce Platform',
        'icon'  => 'fa-cart-shopping',
        'desc'  => 'Storefronts, catalog, checkout, and fulfillment glue—from D2C brands to multi-vendor marketplaces.',
        'bullets' => [
            'Catalog, promotions, inventory',
            'Checkout & payment orchestration',
            'OMS / logistics hooks',
            'Admin & seller consoles',
        ],
    ],
    [
        'title' => 'SaaS Platforms',
        'icon'  => 'fa-cloud',
        'desc'  => 'Multi-tenant products, subscription billing, and scalable APIs built for long-term maintainability.',
        'bullets' => [
            'Tenancy, RBAC, & audit',
            'Plans, usage, and billing readiness',
            'Public & partner APIs',
            'Observability & operational tooling',
        ],
    ],
    [
        'title' => 'Education Technology',
        'icon'  => 'fa-graduation-cap',
        'desc'  => 'LMS features, cohort learning, assessments, and content delivery aligned with how institutions and creators teach online.',
        'bullets' => [
            'Courses, modules, & progress',
            'Live cohorts & hands-on projects',
            'Certificates & outcomes',
            'B2B institution & B2C learner flows',
        ],
    ],
    [
        'title' => 'Eva One Solution',
        'icon'  => 'fa-wand-magic-sparkles',
        'desc'  => 'Eva One brings guided assistance, smart automation, and conversational experiences into your product—designed with security, clarity, and your brand voice in mind. Coming soon.',
        'upcoming' => true,
        'bullets' => [
            'Guided onboarding & contextual help',
            'FAQ, support, and workflow shortcuts',
            'Integrations with your data & tools (policy-safe)',
            'Insights to improve answers and adoption',
        ],
    ],
    [
        'title' => 'Startup MVP Solutions',
        'icon'  => 'fa-rocket',
        'desc'  => 'Rapid, de-risked first releases: scope discipline, secure defaults, and a path from prototype to production scale.',
        'bullets' => [
            'Discovery → build roadmap',
            'Core user journeys first',
            'CI/CD & hosting foundations',
            'Post-launch iteration support',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php renderStandardViewport(); ?>
<title>CYBEORCH Solutions</title>
<meta name="description" content="CYBEORCH builds event platforms, EV ecosystems, FinTech, healthcare systems, e-commerce, SaaS, EdTech, Eva One Solution, and startup MVPs.">
<?php renderPublicPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-yellow:#ffd166;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-orange:#ff6b35;--cyber-card:rgba(10,22,40,0.95);}
*{box-sizing:border-box}
body{margin:0;background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif}
a{text-decoration:none}
.section{padding:5rem 0}
.hero-title{font-family:'Rajdhani',sans-serif;font-size:clamp(2.5rem,6vw,4.5rem);font-weight:700;line-height:1.1;margin-bottom:1rem;color:var(--cyber-text)}
.hero-title .accent{color:var(--cyber-accent)}
.hero-title .green{color:var(--cyber-green)}
.hero-copy{color:var(--cyber-muted);max-width:760px;margin:0 auto;font-size:1.05rem;line-height:1.8}
.solution-card{position:relative;background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:1rem;padding:2rem;height:100%;transition:border-color .2s,transform .2s,box-shadow .2s}
.solution-card:hover{border-color:rgba(0,212,255,0.45);transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,212,255,0.12)}
.solution-card--upcoming{border-color:rgba(255,107,53,0.35)}
.solution-card--upcoming:hover{border-color:rgba(255,107,53,0.55);box-shadow:0 12px 40px rgba(255,107,53,0.1)}
.solution-badge-upcoming{position:absolute;top:1rem;right:1rem;font-family:'Rajdhani',sans-serif;font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#050b18;background:linear-gradient(135deg,var(--cyber-orange),#ffb347);padding:.35rem .65rem;border-radius:6px;line-height:1}
.solution-card .icon-wrap{width:3rem;height:3rem;border-radius:.75rem;background:rgba(0,212,255,0.08);border:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem;color:var(--cyber-accent);font-size:1.25rem}
.solution-card h3{font-family:'Rajdhani',sans-serif;margin-bottom:.75rem;font-size:1.35rem;color:var(--cyber-yellow)}
.solution-title{color:#ffd166}
.solution-card .lead-in{color:var(--cyber-muted);font-size:.95rem;line-height:1.7;margin-bottom:1rem}
.solution-list{list-style:none;padding:0;margin:0}
.solution-list li{position:relative;padding-left:1.65rem;margin-bottom:.65rem;color:var(--cyber-text);font-size:.9rem;line-height:1.5}
.solution-list li:before{content:'\f054';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;left:0;top:.15rem;color:var(--cyber-green);font-size:.65rem}
.cta-strip{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:1rem;padding:2.5rem;text-align:center}
.btn-primary-cyber{display:inline-block;background:var(--cyber-accent);color:#050b18;padding:.85rem 1.75rem;border-radius:8px;font-weight:700;transition:all .2s;border:none}
.btn-primary-cyber:hover{background:var(--cyber-green);color:#050b18}
.btn-outline-cyber{display:inline-block;background:transparent;border:1px solid var(--cyber-border);color:var(--cyber-accent);padding:.85rem 1.75rem;border-radius:8px;font-weight:600;margin-left:.75rem;transition:all .2s}
.btn-outline-cyber:hover{border-color:var(--cyber-accent);background:rgba(0,212,255,.08)}
.fade-in{opacity:0;transform:translateY(20px);transition:all .6s ease}
.fade-in.visible{opacity:1;transform:translateY(0)}
@media(max-width:767px){.section{padding:3rem 0}.btn-outline-cyber{margin-left:0;margin-top:.75rem;display:block}}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="section text-center pb-0">
  <div class="container">
    <h1 class="hero-title"><span class="accent">CYBEORCH</span> Solutions</h1>
    <p class="hero-copy">Product and platform engineering across domains we ship regularly: regulated workflows, marketplaces, learning systems, and investor-ready MVPs—secured and built to scale with your roadmap.</p>
  </div>
</header>

<main>
  <section class="section pt-4" id="solution-areas">
    <div class="container">
      <div class="row g-4">
        <?php foreach ($solutionAreas as $area):
            $isUpcoming = !empty($area['upcoming']);
            $cardClass = 'solution-card h-100' . ($isUpcoming ? ' solution-card--upcoming' : '');
        ?>
        <div class="col-md-6 col-lg-4 fade-in">
          <div class="<?= htmlspecialchars($cardClass) ?>">
            <?php if ($isUpcoming): ?>
            <span class="solution-badge-upcoming">Upcoming</span>
            <?php endif; ?>
            <div class="icon-wrap" aria-hidden="true">
              <i class="fas <?= htmlspecialchars($area['icon']) ?>"></i>
            </div>
            <h3><?= htmlspecialchars($area['title']) ?></h3>
            <p class="lead-in"><?= htmlspecialchars($area['desc']) ?></p>
            <ul class="solution-list">
              <?php foreach ($area['bullets'] as $b): ?>
              <li><?= htmlspecialchars($b) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- <section class="section pt-0">
    <div class="container">
      <div class="cta-strip fade-in">
        <h2 class="hero-title" style="font-size:clamp(1.75rem,4vw,2.5rem);margin-bottom:.75rem">Discuss your build</h2>
        <p class="hero-copy mb-4">Share context on sector, timelines, and compliance needs—we’ll map a practical architecture and delivery plan.</p>
        <a href="<?= url('contact.php') ?>" class="btn-primary-cyber"><i class="fas fa-paper-plane me-2"></i>Contact the team</a>
        <a href="<?= url('services.php') ?>" class="btn-outline-cyber"><i class="fas fa-layer-group me-2"></i>All services</a>
      </div>
    </div>
  </section> -->
</main>

<?php renderPublicFooter(); ?>

<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));
</script>
</body>
</html>
