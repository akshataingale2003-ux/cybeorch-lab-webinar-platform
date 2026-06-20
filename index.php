<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/website-registration.php';
require_once __DIR__ . '/includes/site-contact.php';
require_once __DIR__ . '/includes/public-catalog.php';
require_once __DIR__ . '/includes/webinar-register-helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/auth-registration-popup.php';
require_once __DIR__ . '/includes/projects-public.php';

startSession();

$refCode = sanitize($_GET['ref'] ?? '');
$showRegistrationPopup = isPublicAuthEnabled() && !hasWebsiteAccess();
$websiteRegError       = '';
$websiteRegSuccess     = '';
if (!empty($_SESSION['website_register_error'])) {
    $websiteRegError = (string) $_SESSION['website_register_error'];
    unset($_SESSION['website_register_error']);
}
if (!empty($_GET['registered']) || !empty($_SESSION['website_register_success'])) {
    $websiteRegSuccess = (string) ($_SESSION['website_register_success'] ?? 'Registration successful! Welcome to CYBEORCH.');
    unset($_SESSION['website_register_success']);
    $showRegistrationPopup = false;
}

// IMPORTANT: Webinar seat counts must always be computed from webinar_registrations rows (no caching).
$upcomingWebinars = publicFetchUpcomingWebinars();
$homepageProjects = publicFetchHomepageProjects(3);

$interestPrefillName = $interestPrefillEmail = $interestPrefillPhone = '';
if (isLoggedIn()) {
    $interestUser = dbTry(
        fn () => db()->fetchOne('SELECT full_name, email, phone FROM users WHERE id=?', [(int) $_SESSION['user_id']]),
        null
    );
    if ($interestUser) {
        $interestPrefillName  = $interestUser['full_name'] ?? '';
        $interestPrefillEmail = $interestUser['email'] ?? '';
        $interestPrefillPhone = $interestUser['phone'] ?? '';
    }
}

$techStack = [
    ['name' => 'Flutter', 'logo' => 'assets/images/tech/flutter.svg'],
    ['name' => 'React', 'logo' => 'assets/images/tech/react.svg'],
    ['name' => 'Node.js', 'logo' => 'assets/images/tech/nodejs.svg'],
    ['name' => 'Python', 'logo' => 'assets/images/tech/python.svg'],
    ['name' => 'Next.js', 'logo' => 'assets/images/tech/nextjs.svg'],
    ['name' => 'Java', 'logo' => 'assets/images/tech/java.svg'],
    ['name' => 'Spring Boot', 'logo' => 'assets/images/tech/springboot.svg'],
    ['name' => 'Solidity', 'logo' => 'assets/images/tech/solidity.svg', 'mono' => true],
    ['name' => 'AWS', 'logo' => 'assets/images/tech/aws.svg'],
    ['name' => 'Firebase', 'logo' => 'assets/images/tech/firebase.svg'],
    ['name' => 'Docker', 'logo' => 'assets/images/tech/docker.svg'],
    ['name' => 'PostgreSQL', 'logo' => 'assets/images/tech/postgresql.svg'],
    ['name' => 'MySQL', 'logo' => 'assets/images/tech/mysql.svg'],
];

$workWithPartners = [
    ['label' => 'Startups', 'logo' => 'assets/images/partners/startups.svg'],
    ['label' => 'Enterprises', 'logo' => 'assets/images/partners/enterprises.svg'],
    ['label' => 'SMEs', 'logo' => 'assets/images/partners/smes.svg'],
    ['label' => 'Agencies', 'logo' => 'assets/images/partners/agencies.svg'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title>CYBEORCH LABS & Software Development Company</title>
<meta name="description" content="Learn cybersecurity, ethical hacking, and web security through live webinars and intensive bootcamps. Join CYBEORCH LABS today.">
<?php renderPublicPageHead(); ?>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
<?php if ($showRegistrationPopup) {
    renderAuthRegistrationPopupStyles();
} else {
    renderPublicAuthRegistrationAssets(false);
} ?>
<style>
:root {
  --cyber-dark: #050b18;
  --cyber-navy: #0a1628;
  --cyber-blue: #0f3460;
  --cyber-accent: #00d4ff;
  --cyber-green: #00ff88;
  --cyber-yellow: #ffd166;
  --cyber-red: #ff5f57;
  --cyber-light-blue: #7dd3fc;
  --cyber-orange: #ff6b35;
  --cyber-gold: #ffc107;
  --cyber-gold-dark: #b8860b;
  --cyber-text: #e0e8f0;
  --cyber-muted: #7a8fa6;
  --cyber-card: rgba(15,52,96,0.4);
  --cyber-border: rgba(0,212,255,0.2);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body {
  background: var(--cyber-dark);
  color: var(--cyber-text);
  font-family: 'DM Sans', sans-serif;
  overflow-x: clip;
  max-width: 100vw;
}

/* Grid background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(0,212,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,212,255,0.03) 1px, transparent 1px);
  background-size: 50px 50px;
  pointer-events: none;
  z-index: 0;
}

/* HERO banner — full image, no crop */
.hero {
  width: 100%;
  padding: 0;
  margin: 0;
  position: relative;
  background: var(--cyber-dark);
}
.hero .container-fluid,
.hero .row,
.hero .col-lg-12 {
  width: 100%;
  max-width: 100%;
  padding: 0;
  margin: 0;
}
.hero-terminal {
  width: 100%;
  position: relative;
  line-height: 0;
  background: var(--cyber-navy);
  overflow: visible;
}
.hero-terminal .hero-img {
  width: 100%;
  max-width: 100%;
  height: auto;
  display: block;
  vertical-align: middle;
}
.hero-intro {
  background: var(--cyber-navy);
  border-bottom: 1px solid var(--cyber-border);
  padding: 4rem 0;
  position: relative;
  z-index: 2;
}
.hero-intro .container {
  max-width: 900px;
}
.hero-intro-wrap {
  width: 100%;
  margin-bottom: clamp(1.5rem, 4vw, 2.5rem);
}
.hero-content {
  width: 100%;
  max-width: min(1120px, 100%);
  margin-left: auto;
  margin-right: auto;
  padding-inline: clamp(0.5rem, 2.5vw, 1.25rem);
  box-sizing: border-box;
}
.hero-intro-card {
  width: 100%;
  max-width: min(1000px, 100%);
  margin: 0 auto;
  padding: clamp(1.75rem, 4vw, 2.75rem) clamp(1.35rem, 4.5vw, 2.75rem);
  background: linear-gradient(145deg, rgba(15, 52, 96, 0.45) 0%, rgba(10, 22, 40, 0.75) 100%);
  border: 1px solid rgba(0, 212, 255, 0.28);
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35), 0 0 40px rgba(0, 212, 255, 0.08);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  position: relative;
  overflow: hidden;
  box-sizing: border-box;
}
.hero-intro-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  /*background: linear-gradient(90deg, var(--cyber-accent), var(--cyber-green)); */
}
.hero-intro-card .hero-subtitle {
  margin-bottom: 1.5rem;
  margin-left: auto;
  margin-right: auto;
  max-width: 58em;
  text-wrap: balance;
  line-height: 1.42;
}
.hero-intro-card .hero-page-title {
  margin-bottom: 0.25rem;
}
.hero-intro-card .divider {
  margin-bottom: 1.25rem;
}
h2.hero-title,
.hero-content .hero-title {
  font-size: clamp(2.5rem, 8vw, 5rem);
  font-weight: 700;
  line-height: 1.1;
  color: #f4c542;
  font-family: 'Rajdhani', sans-serif;
  margin-bottom: 1.25rem;
}
h1.hero-subtitle,
h3.hero-subtitle,
.hero-content .hero-subtitle {
  font-size: clamp(1.1rem, 2.5vw, 1.75rem);
  color: #fff;
  line-height: 1.6;
  margin-top: 0;
  margin-bottom: 1.75rem;
  font-family: 'DM Sans', sans-serif;
  font-weight: 400;
}
.hero-content .hero-subtitle-brand {
  display: block;
  font-size: clamp(1rem, 2.5vw, 1.35rem);
  font-family: 'Rajdhani', sans-serif;
  font-weight: 600;
  color: var(--cyber-accent);
  margin: 0.35rem 0 0;
  letter-spacing: 0.04em;
}
.hero-content .accent { color: var(--cyber-accent); }
.hero-content .yellow { color: var(--cyber-yellow); }
.hero-page-title {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.5rem;
}
.hero-title-logo {
  width: auto;
  height: clamp(56px, 12vw, 88px);
  max-width: min(280px, 75vw);
  object-fit: contain;
  filter: drop-shadow(0 8px 24px rgba(0,0,0,0.35));
}
.hero-title-name {
  font-family: 'Rajdhani', sans-serif;
  font-size: clamp(2rem, 6vw, 3.25rem);
  font-weight: 700;
  margin: 0;
  line-height: 1.15;
}
.hero-title-tagline {
  color: var(--cyber-muted);
  font-size: clamp(1rem, 2.5vw, 1.2rem);
  margin: 0.35rem 0 0;
  line-height: 1.5;
}
.hero-buttons {
  display: flex;
  gap: clamp(0.65rem, 2vw, 1.25rem);
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  width: 100%;
}
.hero-intro-card .hero-buttons .btn-primary-cyber,
.hero-intro-card .hero-buttons .btn-outline-cyber {
  flex: 0 1 auto;
  min-width: 0;
  text-align: center;
}
.btn-primary-cyber {
  background: var(--cyber-accent);
  color: #000;
  border: none;
  padding: 0.875rem 1.875rem;
  border-radius: 6px;
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.3s;
  display: inline-block;
  cursor: pointer;
  font-family: inherit;
}
button.btn-primary-cyber {
  width: auto;
}
button.btn-primary-cyber.w-100 {
  width: 100%;
}
.btn-primary-cyber:hover {
  background: var(--cyber-green);
  color: #000;
  transform: translateY(-3px);
}
.btn-outline-cyber {
  border: 2px solid var(--cyber-accent);
  color: var(--cyber-accent);
  padding: 0.875rem 1.875rem;
  border-radius: 6px;
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.3s;
  display: inline-block;
}
.btn-outline-cyber:hover {
  background: var(--cyber-accent);
  color: #000;
}
.hero-stats {
  display: flex;
  gap: 2.5rem;
  margin-top: 2.5rem;
  flex-wrap: wrap;
  justify-content: center;
}
.hero-stats .stat-item { color: var(--cyber-text); }
.hero-stats .stat-num {
  display: block;
  font-family: 'Rajdhani', sans-serif;
  font-size: 2.375rem;
  font-weight: 700;
  color: var(--cyber-accent);
  line-height: 1;
}
.hero-stats .stat-label {
  font-size: 0.875rem;
  color: var(--cyber-muted);
  margin-top: 0.25rem;
}
.hero-badge {
  display: inline-block;
  background: rgba(0,212,255,0.1);
  border: 1px solid var(--cyber-border);
  color: var(--cyber-accent);
  padding: 0.4rem 1rem;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 500;
  letter-spacing: 1px;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}
.terminal-header {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 1rem;
  padding-bottom: 0.75rem;
  border-bottom: 1px solid var(--cyber-border);
}
.dot { width: 12px; height: 12px; border-radius: 50%; }
.dot-red { background: #ff5f57; }
.dot-yellow { background: #febc2e; }
.dot-green { background: #28c840; }
.terminal-title { color: var(--cyber-muted); font-size: 0.75rem; margin-left: auto; }
.t-line { margin: 0.3rem 0; }
.t-prompt { color: var(--cyber-green); }
.t-cmd { color: var(--cyber-text); }
.t-output { color: var(--cyber-muted); padding-left: 1rem; }
.t-success { color: var(--cyber-green); }
.t-accent { color: var(--cyber-accent); }
.cursor {
  display: inline-block;
  width: 8px; height: 14px;
  background: var(--cyber-accent);
  animation: blink 1s step-end infinite;
  vertical-align: text-bottom;
  margin-left: 2px;
}
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

/* â”€â”€ SECTION STYLES â”€â”€ */
.section { padding: 5rem 0; position: relative; z-index: 1; }
.section-title {
  font-family: 'Rajdhani', sans-serif;
  font-size: clamp(2rem, 4vw, 2.8rem);
  font-weight: 700;
  margin-bottom: 0.5rem;
}
.section-title .accent { color: var(--cyber-accent); }
.section-subtitle { color: var(--cyber-muted); font-size: 1rem; margin-bottom: 3rem; }
.divider {
  width: 60px;
  height: 3px;
  background: linear-gradient(90deg, var(--cyber-accent), var(--cyber-green));
  margin: 0.75rem 0 1rem;
  border-radius: 2px;
}

/* CYBEORCH LABS + Services banner spacing */
.section-cybeorch-lab {
  padding: 2.5rem 0 2rem;
  background: var(--cyber-dark);
  position: relative;
  z-index: 1;
}
.section-cybeorch-services {
  padding: 0 0 2.75rem;
  background: var(--cyber-dark);
  position: relative;
  z-index: 1;
}
.cybeorch-services-card {
  background: #000;
  border: 1px solid rgba(0, 212, 255, 0.28);
  border-radius: 18px;
  padding: 1rem 1.15rem;
  margin: 0 auto;
  max-width: 100%;
  box-shadow:
    0 0 0 1px rgba(0, 212, 255, 0.06),
    0 12px 42px rgba(0, 212, 255, 0.14),
    0 8px 32px rgba(0, 0, 0, 0.55);
  overflow: hidden;
  transition: box-shadow 0.3s ease, border-color 0.3s ease;
}
.cybeorch-services-card:hover {
  border-color: rgba(0, 212, 255, 0.42);
  box-shadow:
    0 0 0 1px rgba(0, 212, 255, 0.1),
    0 16px 48px rgba(0, 212, 255, 0.2),
    0 10px 36px rgba(0, 0, 0, 0.6);
}
.cybeorch-services-img {
  display: block;
  width: 100%;
  max-width: 100%;
  height: auto;
  border-radius: 12px;
  object-fit: contain;
}
#webinars.section {
  padding-top: 2.5rem;
}
@media (max-width: 991.98px) {
  .section-cybeorch-lab { padding: 2rem 0 1.5rem; }
  .section-cybeorch-services { padding-bottom: 2.25rem; }
  .cybeorch-services-card { border-radius: 16px; padding: 0.85rem; }
}
@media (max-width: 575.98px) {
  .section-cybeorch-lab { padding: 1.5rem 0 1.25rem; }
  .section-cybeorch-services { padding-bottom: 1.75rem; }
  .cybeorch-services-card { border-radius: 15px; padding: 0.65rem; }
  .cybeorch-services-img { border-radius: 10px; }
}

/* â”€â”€ WEBINAR CARDS â”€â”€ */
.webinar-card {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  padding: 1.5rem;
  transition: all 0.3s;
  height: 100%;
  width: 100%;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.webinar-card-body { flex: 1 1 auto; min-height: 0; }
.webinar-card-footer {
  margin-top: auto;
  flex-shrink: 0;
  padding-top: 0.75rem;
}
.webinar-paid-compact {
  font-size: 0.88rem;
  font-weight: 600;
  color: var(--cyber-gold-dark);
  margin: 0 0 0.75rem;
  letter-spacing: 0.02em;
}
.webinar-card-footer .webinar-fee-notice--free {
  min-height: 2.25rem;
  margin: 0 0 0.75rem;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
}
.webinar-card-footer .btn-primary-cyber { display: block; width: 100%; }
.webinar-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg, var(--cyber-accent), var(--cyber-green));
  opacity: 0;
  transition: opacity 0.3s;
}
.webinar-card:hover { border-color: var(--cyber-accent); transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,212,255,0.15); }
.webinar-card:hover::before { opacity: 1; }
.webinar-thumb-wrap {
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(0,212,255,0.22);
  background: rgba(10,22,40,0.7);
  margin-bottom: 1rem;
  padding: .75rem;
}
.webinar-thumb {
  display: block;
  width: 100%;
  height: auto;
  max-width: 100%;
  object-fit: contain;
  object-position: center;
  border-radius: 10px;
}
.webinar-meta { color: var(--cyber-muted); font-size: 0.85rem; margin: 0.75rem 0; display: flex; gap: 1rem; flex-wrap: wrap; }
.webinar-meta span { display: flex; align-items: center; gap: 0.3rem; }
.webinar-title { font-family: 'Rajdhani', sans-serif; font-size: 1.25rem; font-weight: 600; color: var(--cyber-text); margin-bottom: 0.5rem; }
.webinar-desc { color: var(--cyber-muted); font-size: 0.88rem; line-height: 1.6; margin-bottom: 1rem; }
.webinar-instructor { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; }
.instructor-avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--cyber-accent), var(--cyber-blue));
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem; font-weight: 700; color: var(--cyber-dark);
}
.instructor-name { font-size: 0.85rem; color: var(--cyber-text); }
.seats-bar {
  background: rgba(255,255,255,0.1);
  border-radius: 2px;
  height: 4px;
  margin: 0.5rem 0;
  overflow: hidden;
}
.seats-fill { height: 100%; background: linear-gradient(90deg, var(--cyber-accent), var(--cyber-green)); border-radius: 2px; }

/* â”€â”€ TECH STACK SLIDER â”€â”€ */
.tech-slider {
  overflow: hidden;
  position: relative;
  padding: 1rem 0 2rem;
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
  mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
}
.tech-slider-track {
  display: flex;
  align-items: stretch;
  gap: 1.5rem;
  width: max-content;
  animation: techSliderScroll 45s linear infinite;
}
.tech-slider:hover .tech-slider-track { animation-play-state: paused; }
@keyframes techSliderScroll {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}
.tech-slide-item {
  flex: 0 0 auto;
  width: 150px;
}
.tech-slide-card {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  padding: 1.25rem 1rem;
  text-align: center;
  height: 100%;
  transition: all 0.3s;
}
.tech-slide-card:hover {
  border-color: var(--cyber-accent);
  transform: translateY(-4px);
  box-shadow: 0 10px 32px rgba(0,212,255,0.15);
}
.tech-slide-logo {
  width: 56px;
  height: 56px;
  margin: 0 auto 0.85rem;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tech-slide-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 4px 12px rgba(0,212,255,0.2));
}
.tech-slide-logo img.tech-logo-mono {
  filter: brightness(0) invert(1) drop-shadow(0 4px 12px rgba(0,212,255,0.25));
}
.tech-slide-name {
  font-family: 'Rajdhani', sans-serif;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--cyber-text);
  letter-spacing: 0.3px;
}

/* â”€â”€ WE WORK WITH â”€â”€ */
.partner-card {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  padding: 2rem 1.25rem;
  text-align: center;
  height: 100%;
  transition: all 0.3s;
}
.partner-card:hover {
  border-color: var(--cyber-accent);
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0,212,255,0.12);
}
.partner-logo {
  width: 88px;
  height: 88px;
  margin: 0 auto 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}
.partner-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 6px 16px rgba(0,212,255,0.2));
}
.partner-card h3 {
  font-family: 'Rajdhani', sans-serif;
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0;
  color: var(--cyber-text);
  letter-spacing: 0.5px;
}

/* â”€â”€ CONTACT â”€â”€ */
.contact-form {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  padding: 2.5rem;
}
.form-control {
  background: rgba(255,255,255,0.05) !important;
  border: 1px solid var(--cyber-border) !important;
  color: var(--cyber-text) !important;
  border-radius: 6px !important;
  padding: 0.75rem 1rem !important;
  transition: border-color 0.2s !important;
}
.form-control:focus {
  border-color: var(--cyber-accent) !important;
  box-shadow: 0 0 0 3px rgba(0,212,255,0.1) !important;
  outline: none !important;
}
.form-control::placeholder { color: var(--cyber-muted) !important; }
.form-label { color: var(--cyber-muted); font-size: 0.88rem; margin-bottom: 0.4rem; }

/* â”€â”€ ALERTS â”€â”€ */
.alert { padding: 1rem 1.5rem; border-radius: 6px; margin: 1rem 0; font-size: 0.9rem; }
.alert-success { background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.3); color: var(--cyber-green); }
.alert-error { background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.3); color: #ff4444; }
.alert-info { background: rgba(0,212,255,0.1); border: 1px solid var(--cyber-border); color: var(--cyber-accent); }

/* Hero intro card — shared (webinars + start-project) */
@media (min-width: 768px) {
  .hero-content {
    max-width: min(1080px, 96vw);
  }
  .hero-intro-card {
    max-width: min(960px, 100%);
  }
  .hero-intro-card .hero-subtitle {
    font-size: clamp(1.12rem, 1.65vw, 1.58rem);
    line-height: 1.38;
  }
}
@media (min-width: 992px) {
  .hero-content {
    max-width: min(1120px, 94vw);
  }
  .hero-intro-card {
    max-width: min(1000px, 100%);
    padding-inline: clamp(2rem, 4vw, 3rem);
  }
  .hero-intro-card .hero-subtitle {
    font-size: clamp(1.18rem, 1.45vw, 1.62rem);
    line-height: 1.35;
  }
  .hero-intro-card .hero-buttons {
    gap: 1rem 1.35rem;
  }
}
@media (min-width: 1200px) {
  .hero-intro-card {
    max-width: 1000px;
  }
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .hero-intro { padding: 3rem 0; }
  .hero-content {
    text-align: center;
    padding-inline: clamp(0.25rem, 3vw, 0.75rem);
  }
  .hero-intro-card {
    max-width: 100%;
    padding: clamp(1.35rem, 5vw, 1.85rem) clamp(1rem, 4vw, 1.35rem);
  }
  .hero-intro-card .hero-subtitle {
    font-size: clamp(1rem, 3.8vw, 1.2rem);
    line-height: 1.5;
    max-width: none;
  }
  .hero-buttons { justify-content: center; }
  .hero-stats {
    justify-content: center;
    gap: 1.25rem;
  }
  .hero-stats .stat-num { font-size: 1.75rem; }
  .section { padding: 3rem 0; }
}

/* Animations */
.fade-in { opacity: 0; transform: translateY(20px); transition: all 0.6s ease; }
.fade-in.visible { opacity: 1; transform: translateY(0); }
</style>
</head>
<body class="<?= $showRegistrationPopup ? 'auth-reg-locked cybeorch-registration-locked' : '' ?>">

<?php $navActive = 'home'; require __DIR__ . '/includes/public-navbar.php'; ?>

<!-- HERO SECTION START -->
<section class="hero">
  <div class="container-fluid p-0">
    <div class="row g-0">
      <div class="col-lg-12">
        <div class="hero-terminal">
          <img
            src="<?= htmlspecialchars(urlVersioned('assets/images/Blue_home_white.png'), ENT_QUOTES, 'UTF-8') ?>"
            alt="CYBEORCH LABS"
            class="hero-img"
            width="1536"
            height="1024"
            decoding="async"
            fetchpriority="high"
          >
        </div>
      </div>
    </div>
  </div>
</section>
<!-- HERO SECTION END -->

<!-- CYBEORCH LABS -->
<section class="section section-cybeorch-lab" id="cybeorch-lab">
  <div class="container">
    <div class="hero-content hero-intro-wrap text-center">
      <div class="hero-intro-card fade-in">
        <div class="hero-page-title mt-2">
          <div>
            <h2 class="hero-title-name"><?= brandMark() ?></h2>
           
          </div>
        </div>

        <div class="divider mx-auto"></div>

        <h1 class="hero-subtitle">
          AI-native software engineering and digital transformation partner for startups, SMEs, and enterprises.
        </h1>

        <div class="hero-buttons">
          <a href="<?= url('hands-on-projects.php') ?>" class="btn-primary-cyber"><i class="fas fa-briefcase me-2"></i>Hands-on Projects</a>
          <a href="<?= url('enquire-enroll.php') ?>" class="btn-outline-cyber"><i class="fas fa-graduation-cap me-2"></i>Register for a Bootcamp</a>
          <a href="<?= url('register-freelancer.php') ?>" class="btn-outline-cyber"><i class="fas fa-user-tie me-2"></i>Register as a Freelancer</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
if ($homepageProjects['live'] !== []) {
    renderHomepageProjectSection(
        'live-projects',
        'Live',
        'Projects',
        'Collaborate on real-world builds across AI, blockchain, fintech, and security.',
        $homepageProjects['live'],
        url('live-projects.php'),
        'live'
    );
}
if ($homepageProjects['hands_on'] !== []) {
    renderHomepageProjectSection(
        'hands-on-projects',
        'Hands-On',
        'Projects',
        'Internships and practical roles for students and early-career professionals.',
        $homepageProjects['hands_on'],
        url('hands-on-projects.php'),
        'hands-on'
    );
}
if ($homepageProjects['freelance'] !== []) {
    renderHomepageProjectSection(
        'freelancer-projects',
        'Freelancer',
        'Projects',
        'Scoped engagements for registered CYBEORCH freelancers.',
        $homepageProjects['freelance'],
        url('freelance-projects.php'),
        'freelance'
    );
}
?>

<!-- CYBEORCH SERVICES BANNER -->
<section class="section section-cybeorch-services" id="cybeorch-services" aria-labelledby="cybeorch-services-title">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="section-title mt-3" id="cybeorch-services-title">Our <span class="accent">Services</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Empowering businesses with cutting-edge technology solutions and digital transformation services</p>
    </div>
    <div class="cybeorch-services-card fade-in">
      <img
        src="<?= htmlspecialchars(str_replace(' ', '%20', urlVersioned('assets/images/Cybeorch Services.png')), ENT_QUOTES, 'UTF-8') ?>"
        alt="CYBEORCH Services — Secure, Build, Innovate, Transform"
        class="cybeorch-services-img"
        width="1920"
        height="960"
        loading="lazy"
        decoding="async"
      >
    </div>
  </div>
</section>

<!-- UPCOMING WEBINARS -->
<section class="section" id="webinars">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="section-title mt-3">Upcoming <span class="accent">Webinars</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Register early for scheduled sessions on cybersecurity and emerging tech</p>
    </div>
    <?php if ($upcomingWebinars === []): ?>
    <p class="text-center" style="color:var(--cyber-muted)">No upcoming webinars scheduled right now.</p>
    <?php else: ?>
    <?php renderPublicWebinarCardGrid($upcomingWebinars); ?>
    <?php endif; ?>
  </div>
</section>

<!-- TECHNOLOGY STACK -->
<section class="section" id="tech-stack" style="background:rgba(10,22,40,0.5)">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="section-title">Technology <span class="accent">Stack</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Tools and platforms we use across projects, labs, and product builds</p>
    </div>
    <?php $techSliderItems = array_merge($techStack, $techStack); ?>
    <div class="tech-slider fade-in" aria-label="Technology stack logos">
      <div class="tech-slider-track">
        <?php foreach ($techSliderItems as $tech): ?>
        <div class="tech-slide-item">
          <div class="tech-slide-card">
            <div class="tech-slide-logo">
              <img src="<?= url($tech['logo']) ?>" alt="<?= htmlspecialchars($tech['name']) ?> logo" width="56" height="56" loading="lazy"<?= !empty($tech['mono']) ? ' class="tech-logo-mono"' : '' ?>>
            </div>
            <div class="tech-slide-name"><?= htmlspecialchars($tech['name']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- WE WORK WITH -->
<section class="section" id="work-with">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="section-title">We Work <span class="accent">With</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Partnering with teams of every size to deliver secure, scalable technology solutions</p>
    </div>
    <div class="row g-4 justify-content-center">
      <?php foreach ($workWithPartners as $partner): ?>
      <div class="col-6 col-md-3 fade-in">
        <div class="partner-card">
          <div class="partner-logo">
            <img src="<?= url($partner['logo']) ?>" alt="<?= htmlspecialchars($partner['label']) ?> logo" width="88" height="88" loading="lazy">
          </div>
          <h3><?= htmlspecialchars($partner['label']) ?></h3>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- READY TO BUILD -->
<section class="section pt-0" id="start-project">
  <div class="container">
    <div class="text-center">
      <div class="hero-content hero-intro-wrap text-center">
        <div class="hero-intro-card fade-in">
          <div class="hero-page-title mt-2">
            <div>
              <h2 class="hero-title-name">Ready to Build Your <span class="accent">Next Project</span>?</h2>
            </div>
          </div>

          <div class="divider mx-auto"></div>

          <h2 class="hero-subtitle">
            Connect with our experts and turn your ideas into powerful digital solutions.
          </h2>

          <div class="hero-buttons">
            <a href="<?= url('start-project.php') ?>" class="btn-primary-cyber">
              <i class="fas fa-rocket me-2"></i>Start Your Project
            </a>
            <a href="<?= url('book-consulting.php') ?>" class="btn-outline-cyber">
              <i class="fas fa-calendar-check me-2"></i>Book Consulting
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CONTACT -->
<section class="section" id="contact" style="background:rgba(10,22,40,0.5)">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-5">
        <h2 class="section-title">Get In <span class="accent">Touch</span></h2>
        <p style="color:var(--cyber-muted); line-height:1.8">Have questions about our programs? Want to collaborate? We're here to help you navigate your cybersecurity career path.</p>
        <div class="mt-4">
          <?php renderContactActionStyles(); renderContactActionGrid(); ?>
          <div class="d-flex align-items-center gap-3 mb-3 mt-3">
            <div style="width:40px;height:40px;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center;">
              <i class="fab fa-x-twitter" style="color:var(--cyber-text)"></i>
            </div>
            <div>
              <div style="font-size:0.8rem;color:var(--cyber-muted)">X (Twitter)</div>
              <div style="font-size:0.9rem">
                <a href="<?= htmlspecialchars(SITE_TWITTER_URL) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--cyber-accent);text-decoration:none">@<?= htmlspecialchars(SITE_TWITTER_HANDLE) ?></a>
              </div>
            </div>
          </div>
          <!-- <div class="d-flex align-items-center gap-3">
            <div style="width:40px;height:40px;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center;">
              <i class="fab fa-discord" style="color:#7289da"></i>
            </div>
           <div><div style="font-size:0.8rem;color:var(--cyber-muted)">Discord</div><div style="font-size:0.9rem">discord.gg/CYBEORCHlab</div></div> 
          </div> -->
        </div>
      </div>
      <div class="col-lg-7">
        <div class="contact-form">
          <?= showFlash() ?>
          <form method="POST" action="<?= url('contact.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Your Name *</label>
                <input type="text" name="name" class="form-control" placeholder="John Doe" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" placeholder="john@email.com" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="+91 XXXXX XXXXX">
              </div>
              <div class="col-md-6">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="Webinar enquiry">
              </div>
              <div class="col-12">
                <label class="form-label">Message *</label>
                <textarea name="message" class="form-control" rows="4" placeholder="Tell us what's on your mind..." required></textarea>
              </div>
              <div class="col-12">
                <button type="submit" class="btn-primary-cyber w-100"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($showRegistrationPopup): ?>
<?php $GLOBALS['cybeorch_skip_public_reg_assets'] = true; ?>
<?php
renderAuthRegistrationPopup(buildAuthRegistrationPopupConfig([
    'mandatory'              => true,
    'csrf_token'             => generateCSRF(),
    'success_url'            => authRegistrationSuccessUrl(),
    'ref_code'               => $refCode,
    'prefill_name'           => $interestPrefillName,
    'prefill_email'          => $interestPrefillEmail,
    'prefill_phone'          => $interestPrefillPhone,
    'login_action'           => url('login.php'),
    'login_redirect'         => 'index.php',
    'website_error'          => $websiteRegError,
    'show_register_required' => !empty($_GET['register_required']),
    'banner_tagline'         => '',
]));
?>
<?php
renderAuthRegistrationPopupScripts(false);
?>
<?php elseif ($websiteRegSuccess !== ''): ?>
<div class="alert alert-success container mt-3" style="position:relative;z-index:10" role="status">
  <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($websiteRegSuccess) ?>
</div>
<?php endif; ?>

<?php renderPublicFooter(); ?>

<?php renderSiteScripts(true); ?>
<script>
// Scroll animation
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

</script>
<?php if ($showRegistrationPopup): ?>
<script src="<?= url('assets/js/registration-otp.js') ?>"></script>
<?php endif; ?>
<?php
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/nxl-wallet.php';
    renderNxlRewardPopups();
}
?>
</body>
</html>
