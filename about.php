<?php
/**
 * About Us page — full content (no redirect).
 * Works even when MySQL is offline (unlike index.php).
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/company-public.php';

startSession();

$companyTrust = getCompanyProfileSettings();
$founders = publicFetchTeamMembers('founder');
$teamMembers = publicFetchTeamMembers('team');
$awards = publicFetchAwards();
$trustBadges = publicFetchTrustBadges();

$pageTitle = 'About Us – CYBEORCH LABS';

$whatWeDo = [
    'Real-world Software Development Projects',
    'Full Stack Web & Mobile Application Development',
    'Cybersecurity & Secure Deployment Solutions',
    'Blockchain & Web3 Development',
    'UI/UX and Product Prototype Development',
    'AI & Automation Based Solutions',
    'Startup Technology Support & MVP Development',
    'Internship & Industrial Software Development Company Programs',
    'Freelance Hands-on Projects',
    'Skill Development Bootcamps',
    'Research, Testing & Deployment Activities',
];

$internshipPoints = [
    'Live client projects',
    'Team collaboration workflows',
    'Software deployment practices',
    'Version control & documentation',
    'Problem-solving and product development',
    'Industry-level project execution',
];

$freelancePoints = [
    'Client-based development tasks',
    'Internal product development',
    'Startup support systems',
    'Research and deployment projects',
    'Remote and hybrid collaboration models',
];

$whyLab = [
    'Practical Industry Exposure',
    'Live Development Environment',
    'Project-Based Learning',
    'Startup & Innovation Driven Culture',
    'Technology + Skill Development Integration',
    'Opportunities for Growth & Collaboration',
    'Focus on Execution, Deployment & Scalability',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LABS</title>
<meta name="description" content="CYBEORCH LABS — a real-world software development and innovation lab building practical technology solutions, industry-ready talent, and scalable digital products.">
<?php renderPublicPageHead(); ?>
<?php renderCompanyPageStyles(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.4);}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif;overflow-x:hidden}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}
a{text-decoration:none}
.section{padding:5rem 0;position:relative;z-index:1}
.page-hero{padding:4rem 0 2rem;text-align:center}
.page-hero h1{font-family:'Rajdhani',sans-serif;font-size:clamp(2.2rem,5vw,3.5rem);font-weight:700;margin-bottom:1rem}
.page-hero h1 .accent{color:var(--cyber-accent)}
.page-hero p{color:var(--cyber-muted);max-width:720px;margin:0 auto;line-height:1.8;font-size:1.05rem}
.section-title{font-family:'Rajdhani',sans-serif;font-size:clamp(1.8rem,4vw,2.5rem);font-weight:700;margin-bottom:.5rem}
.section-title .accent{color:var(--cyber-accent)}
.section-title .green{color:var(--cyber-green)}
.team-profile-heading{width:100%;text-align:center;margin-bottom:1rem}
.founder-profile-row{justify-content:center}
.awards-row{justify-content:center}
.award-card{text-align:center}
.award-card i{margin-left:auto;margin-right:auto}
.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin-bottom:1.5rem}
.about-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:2rem;height:100%;transition:border-color .2s,transform .2s}
.about-card:hover{border-color:rgba(0,212,255,0.45);transform:translateY(-4px)}
.about-card i{font-size:1.75rem;color:var(--cyber-accent);margin-bottom:1rem;display:block}
.about-card h3{font-family:'Rajdhani',sans-serif;font-size:1.35rem;margin-bottom:.75rem}
.about-card p{color:var(--cyber-muted);line-height:1.7;margin:0;font-size:.95rem}
.stat-box{text-align:center;padding:1.5rem;background:rgba(10,22,40,0.95);border:1px solid var(--cyber-border);border-radius:12px}
.stat-box .num{font-family:'Rajdhani',sans-serif;font-size:2.5rem;font-weight:700;color:var(--cyber-accent);line-height:1}
.stat-box .label{color:var(--cyber-muted);font-size:.88rem;margin-top:.35rem}
.ecosystem-item{display:flex;gap:1rem;padding:1.25rem;background:rgba(10,22,40,0.6);border:1px solid var(--cyber-border);border-radius:10px;margin-bottom:1rem}
.ecosystem-item:last-child{margin-bottom:0}
.ecosystem-item .icon{width:48px;height:48px;flex-shrink:0;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--cyber-accent)}
.ecosystem-item h4{font-family:'Rajdhani',sans-serif;font-size:1.1rem;margin-bottom:.35rem}
.ecosystem-item p{color:var(--cyber-muted);font-size:.9rem;margin:0;line-height:1.6}
.about-list{list-style:none;padding:0;margin:0}
.about-list li{position:relative;padding:.4rem 0 .4rem 1.35rem;color:var(--cyber-muted);line-height:1.65;font-size:.95rem}
.about-list li::before{content:'';position:absolute;left:0;top:.7rem;width:7px;height:7px;border-radius:2px;background:linear-gradient(135deg,var(--cyber-accent),var(--cyber-green))}
.cta-box{background:linear-gradient(135deg,rgba(0,212,255,0.08),rgba(0,255,136,0.06));border:1px solid var(--cyber-border);border-radius:16px;padding:3rem;text-align:center}
.btn-primary-cyber{display:inline-block;background:var(--cyber-accent);color:#050b18;border:none;padding:.85rem 2rem;border-radius:6px;font-weight:700;font-size:1rem;transition:all .2s}
.btn-primary-cyber:hover{background:var(--cyber-green);transform:translateY(-2px);color:#050b18}
.btn-outline-cyber{display:inline-block;border:1px solid var(--cyber-accent);color:var(--cyber-accent);padding:.85rem 2rem;border-radius:6px;font-weight:600;transition:all .2s;margin-left:.75rem}
.btn-outline-cyber:hover{background:rgba(0,212,255,0.1);color:var(--cyber-accent)}
.about-card.team-card{padding:20px}
@media(max-width:767px){.section{padding:3rem 0}.btn-outline-cyber{display:block;margin:.75rem 0 0}.about-card.team-card{padding:18px}}
</style>
</head>
<body>

<?php $navActive = 'about'; require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>About Us &ndash; <span class="brand-mark"><?= brandMark() ?></span></h1>
    <p>Welcome to <?= brandMark() ?> &mdash; a real-world software development and innovation lab focused on building practical technology solutions, industry-ready talent, and scalable digital products.</p>
  </div>
</header>

<main>
  <section class="section pt-0">
    <div class="container">
      <div class="row g-4 mb-5">
        <?php foreach (array_slice($whyLab, 0, 3) as $point): ?>
        <div class="col-md-4">
          <div class="stat-box">
            <div class="num"><i class="fas fa-check" style="font-size:1.5rem"></i></div>
            <div class="label"><?= htmlspecialchars($point) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
          <h2 class="section-title">About <span class="accent"><?= brandMark() ?></span></h2>
          <p style="color:var(--cyber-muted);line-height:1.8;margin-bottom:1rem">At <?= brandMark() ?>, we believe learning should go beyond theory. Our ecosystem is designed to help user registrations & trainees, fresh graduates, freelancers, and developers work on live industry projects in domains such as software development, cybersecurity, AI tools, web platforms, mobile applications, cloud systems, blockchain utilities, automation, and digital business solutions.</p>
          <p style="color:var(--cyber-muted);line-height:1.8;margin-bottom:0">We operate as a technology lab where innovation, development, deployment, and scaling happen through practical execution and collaborative teamwork.</p>
        </div>
        <div class="col-lg-6">
          <div class="about-card">
            <i class="fas fa-bullseye"></i>
            <h3>Our Mission</h3>
            <p>To bridge the gap between academic learning and real industry execution by creating a platform where individuals can learn, build, deploy, and grow through hands-on project experience.</p>
          </div>
          <div class="about-card mt-4">
            <i class="fas fa-eye" style="color:var(--cyber-green)"></i>
            <h3>Our Vision</h3>
            <p>To build a future-ready technology ecosystem that empowers innovators, developers, and young professionals to create impactful digital solutions for businesses, startups, and emerging industries.</p>
          </div>
        </div>
      </div>

      <h2 class="section-title text-center mb-2"><span class="accent">What We</span> Do</h2>
      <p class="text-center mb-5" style="color:var(--cyber-muted);max-width:600px;margin:0 auto 2.5rem">Hands-on services and programs across software, security, cloud, AI, and digital product development.</p>
      <div class="row g-4 mb-5">
        <div class="col-12">
          <div class="about-card">
            <i class="fas fa-layer-group"></i>
            <div class="row">
              <div class="col-md-6">
                <ul class="about-list">
                  <?php foreach (array_slice($whatWeDo, 0, 6) as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="col-md-6">
                <ul class="about-list">
                  <?php foreach (array_slice($whatWeDo, 6) as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php
        $internshipHalf = (int) ceil(count($internshipPoints) / 2);
        $internshipCol1 = array_slice($internshipPoints, 0, $internshipHalf);
        $internshipCol2 = array_slice($internshipPoints, $internshipHalf);
      ?>
      <h2 class="section-title text-center mb-2">Internship &amp; <span class="accent">Skill Development</span></h2>
      <p class="text-center mb-5" style="color:var(--cyber-muted);max-width:720px;margin:0 auto 2.5rem;line-height:1.8"><?= brandMark() ?> provides structured internship and skill development for user registrations & trainees, graduates, and aspiring developers. Participants gain practical exposure through hands-on projects — not only classroom software development company.</p>
      <div class="row g-4 mb-5">
        <div class="col-12">
          <div class="about-card">
            <i class="fas fa-user-graduate" style="color:var(--cyber-green)"></i>
            <div class="row">
              <div class="col-md-6">
                <ul class="about-list">
                  <?php foreach ($internshipCol1 as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="col-md-6">
                <ul class="about-list">
                  <?php foreach ($internshipCol2 as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php
        $freelanceHalf = (int) ceil(count($freelancePoints) / 2);
        $freelanceCol1 = array_slice($freelancePoints, 0, $freelanceHalf);
        $freelanceCol2 = array_slice($freelancePoints, $freelanceHalf);
      ?>
      <h2 class="section-title text-center mb-2">Freelance &amp; <span class="accent">On-Roll Opportunities</span></h2>
      <p class="text-center mb-5" style="color:var(--cyber-muted);max-width:720px;margin:0 auto 2.5rem;line-height:1.8">We collaborate with freelancers, developers, designers, cybersecurity professionals, and project contributors for short-term and long-term hands-on projects. Outstanding performers may receive on-roll project engagement based on performance and availability.</p>
      <div class="row g-4 mb-5">
        <div class="col-12">
          <div class="about-card">
            <i class="fas fa-briefcase" style="color:var(--cyber-orange)"></i>
            <div class="row">
              <div class="col-md-6">
                <ul class="about-list">
                  <?php foreach ($freelanceCol1 as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="col-md-6">
                <ul class="about-list">
                  <?php foreach ($freelanceCol2 as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <h2 class="section-title text-center mb-2">Why <span class="accent">CYBEORCH LABS</span>?</h2>
      <div class="row g-4 mb-4">
        <?php foreach ($whyLab as $point): ?>
        <div class="col-md-6 col-lg-4">
          <div class="about-card">
            <i class="fas fa-star" style="color:var(--cyber-green)"></i>
            <h3 style="font-size:1.1rem"><?= htmlspecialchars($point) ?></h3>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
<!--<p class="text-center mb-5" style="color:var(--cyber-muted);max-width:720px;margin:0 auto;line-height:1.8"><strong style="color:var(--cyber-text)">CYBEORCH LABS</strong> is not just a software development company platform &mdash; it is a collaborative technology ecosystem where ideas transform into real-world digital solutions.</p>-->

      <h2 class="section-title text-center mb-2 mt-2">Trust &amp; <span class="accent">Credibility</span></h2>
      <p class="text-center mb-4" style="color:var(--cyber-muted);max-width:680px;margin:0 auto 2rem">Verified credentials, leadership, and client trust indicators.</p>

      <div class="row g-4 mb-4">
        <?php if (showCompanyGstCinOnPublicSite() && !empty($companyTrust['gst_number'])): ?>
        <div class="col-md-6 col-lg-3">
          <div class="about-card text-center">
            <i class="fas fa-receipt"></i>
            <h3 style="font-size:1rem">GST Number</h3>
            <p style="font-family:monospace;letter-spacing:.5px"><?= htmlspecialchars((string) $companyTrust['gst_number']) ?></p>
          </div>
        </div>
        <?php endif; ?>
        <?php if (showCompanyGstCinOnPublicSite() && !empty($companyTrust['cin_number'])): ?>
        <div class="col-md-6 col-lg-3">
          <div class="about-card text-center">
            <i class="fas fa-building"></i>
            <h3 style="font-size:1rem">CIN Number</h3>
            <p style="font-family:monospace;letter-spacing:.5px"><?= htmlspecialchars((string) $companyTrust['cin_number']) ?></p>
          </div>
        </div>
        <?php endif; ?>
        <?php foreach ($trustBadges as $badge): ?>
        <div class="col-md-6 col-lg-3">
          <div class="about-card text-center">
            <?php if (!empty($badge['image_path'])): ?>
            <img src="<?= htmlspecialchars(companyPublicImageUrl((string) $badge['image_path'])) ?>" alt="" style="height:42px;margin-bottom:.75rem" loading="lazy">
            <?php else: ?>
            <i class="fas fa-shield-halved" style="color:var(--cyber-green)"></i>
            <?php endif; ?>
            <h3 style="font-size:1rem"><?= htmlspecialchars((string) $badge['title']) ?></h3>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($founders !== []): ?>
      <h3 class="section-title team-profile-heading" style="font-size:1.35rem">Founder <span class="accent">Profile</span></h3>
      <div class="row g-4 mb-5 founder-profile-row justify-content-center">
        <?php foreach ($founders as $founder): ?>
        <div class="col-md-6 col-lg-4 team-profile-col">
          <?php renderTeamProfileCard($founder); ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($teamMembers !== []): ?>
      <h3 class="section-title team-profile-heading" style="font-size:1.35rem">Team <span class="accent">Profiles</span></h3>
      <div class="row g-4 mb-5">
        <?php foreach ($teamMembers as $member): ?>
        <div class="col-md-6 col-lg-4 team-profile-col">
          <?php renderTeamProfileCard($member); ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($awards !== []): ?>
      <h3 class="section-title team-profile-heading" style="font-size:1.35rem">Awards &amp; Achievements</h3>
      <div class="row g-4 mb-4 awards-row justify-content-center">
        <?php foreach ($awards as $award): ?>
        <div class="col-md-8 col-lg-6">
          <div class="about-card award-card">
            <i class="fas fa-trophy" style="color:var(--cyber-orange)"></i>
            <h3 style="font-size:1.1rem"><?= htmlspecialchars((string) $award['title']) ?><?php if (!empty($award['award_year'])): ?> <span style="color:var(--cyber-muted);font-size:.85rem">(<?= htmlspecialchars((string) $award['award_year']) ?>)</span><?php endif; ?></h3>
            <p><?= nl2br(htmlspecialchars((string) ($award['description'] ?? ''))) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!--<section class="section pt-0 pb-5">
    <div class="container">
      <div class="cta-box">
        <h2 class="section-title mb-3">Ready to <span class="accent">Build With Us</span>?</h2>
        <p style="color:var(--cyber-muted);max-width:560px;margin:0 auto 2rem;line-height:1.8">Explore internships, bootcamps, and live projects. Register to join the CYBEORCH LABS ecosystem.</p>
        <a href="<?= url('index.php?register_required=1') ?>" class="btn-primary-cyber"><i class="fas fa-user-plus me-2"></i>Register Free</a>
        <a href="<?= url('register-freelancer.php') ?>" class="btn-outline-cyber"><i class="fas fa-user-tie me-2"></i>Register as Freelancer</a>
        <a href="<?= url('contact.php') ?>" class="btn-outline-cyber"><i class="fas fa-envelope me-2"></i>Contact Us</a>
      </div>
    </div>
  </section> -->
</main>

<?php renderPublicFooter(); ?>

<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
