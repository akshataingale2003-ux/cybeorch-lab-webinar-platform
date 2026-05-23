<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/website-registration.php';

startSession();

$showRegistrationPopup = !hasWebsiteAccess();
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

$webinars = dbTry(
    fn () => db()->fetchAll(
        "SELECT * FROM webinars WHERE status IN ('upcoming','live') ORDER BY scheduled_at ASC LIMIT 6"
    ),
    []
);
$bootcamps = dbTry(
    fn () => db()->fetchAll(
        "SELECT * FROM bootcamps WHERE status = 'open' ORDER BY start_date ASC LIMIT 4"
    ),
    []
);

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
<title>CYBEORCH LAB &ndash; Cybersecurity Education &amp; Training</title>
<meta name="description" content="Learn cybersecurity, ethical hacking, and web security through live webinars and intensive bootcamps. Join CYBEORCH LAB today.">
<?php renderPublicPageHead(); ?>
<link rel="stylesheet" href="<?= url('assets/style.css') ?>">
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
}
.hero-terminal .hero-img {
  width: 100%;
  max-width: 100%;
  height: auto;
  display: block;
  object-fit: contain;
  object-position: center top;
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

/* â”€â”€ WEBINAR CARDS â”€â”€ */
.webinar-card {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  padding: 1.5rem;
  transition: all 0.3s;
  height: 100%;
  position: relative;
  overflow: hidden;
}
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
.webinar-badge {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  border-radius: 4px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.badge-free { background: rgba(0,255,136,0.15); color: var(--cyber-green); border: 1px solid rgba(0,255,136,0.3); }
.badge-paid { background: rgba(255,107,53,0.15); color: var(--cyber-orange); border: 1px solid rgba(255,107,53,0.3); }
.badge-live { background: rgba(255,0,0,0.2); color: #ff4444; border: 1px solid rgba(255,0,0,0.3); }
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

/* â”€â”€ BOOTCAMP CARDS â”€â”€ */
.bootcamp-card {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.3s;
}
.bootcamp-card:hover { border-color: var(--cyber-green); transform: translateY(-4px); box-shadow: 0 12px 40px rgba(0,255,136,0.12); }
.bootcamp-header {
  background: linear-gradient(135deg, var(--cyber-blue), rgba(0,212,255,0.2));
  padding: 2rem;
  position: relative;
}
.bootcamp-price {
  position: absolute;
  top: 1rem; right: 1rem;
  text-align: right;
}
.price-original { font-size: 0.85rem; color: var(--cyber-muted); text-decoration: line-through; }
.price-current { font-family: 'Rajdhani', sans-serif; font-size: 1.8rem; font-weight: 700; color: var(--cyber-green); }
.bootcamp-body { padding: 1.5rem; }
.feature-list { list-style: none; margin: 1rem 0; }
.feature-list li { padding: 0.35rem 0; font-size: 0.88rem; color: var(--cyber-muted); display: flex; align-items: flex-start; gap: 0.55rem; }
.feature-list li i, .feature-list li .fa { flex-shrink: 0; margin-top: 0.12rem; width: 1.15em; text-align: center; color: var(--cyber-accent); }
.feature-list li .fa-coins { color: var(--cyber-green); }

/* â”€â”€ PRICING â”€â”€ */
.pricing-card {
  background: var(--cyber-card);
  border: 1px solid var(--cyber-border);
  border-radius: 12px;
  padding: 2rem;
  text-align: center;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
}
.pricing-card.featured {
  border-color: var(--cyber-accent);
  background: rgba(0,212,255,0.05);
}
.pricing-card.featured::before {
  content: 'MOST POPULAR';
  position: absolute;
  top: 0; left: 50%; transform: translateX(-50%);
  background: var(--cyber-accent);
  color: var(--cyber-dark);
  font-size: 0.7rem;
  font-weight: 700;
  padding: 0.2rem 1.5rem;
  border-radius: 0 0 8px 8px;
  letter-spacing: 1px;
}
.pricing-name { font-family: 'Rajdhani', sans-serif; font-size: 1.4rem; font-weight: 600; margin-bottom: 1rem; }
.pricing-price { font-family: 'Rajdhani', sans-serif; font-size: 3rem; font-weight: 700; color: var(--cyber-accent); }
.pricing-period { font-size: 0.85rem; color: var(--cyber-muted); }
.pricing-features { list-style: none; margin: 1.5rem 0; text-align: left; }
.pricing-features li { padding: 0.4rem 0; font-size: 0.9rem; color: var(--cyber-muted); display: flex; align-items: center; gap: 0.5rem; }
.pricing-features li i { color: var(--cyber-green); width: 16px; }

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
.form-select {
  background: rgba(255,255,255,0.05) !important;
  border: 1px solid var(--cyber-border) !important;
  color: var(--cyber-text) !important;
  border-radius: 6px !important;
  padding: 0.75rem 1rem !important;
}
.form-select option { background: #0a1628; color: var(--cyber-text); }
.form-select:focus {
  border-color: var(--cyber-accent) !important;
  box-shadow: 0 0 0 3px rgba(0,212,255,0.1) !important;
}

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
<body class="<?= $showRegistrationPopup ? 'cybeorch-registration-locked' : '' ?>">

<?php $navActive = 'home'; require __DIR__ . '/includes/public-navbar.php'; ?>

<!-- HERO SECTION START -->
<section class="hero">
  <div class="container-fluid p-0">
    <div class="row g-0">
      <div class="col-lg-12">
        <div class="hero-terminal">
          <img
            src="<?= url('assets/images/Blue_home_white.png') ?>"
            alt="CYBEORCH LAB"
            class="hero-img"
            decoding="async"
            fetchpriority="high"
          >
        </div>
      </div>
    </div>
  </div>
</section>
<!-- HERO SECTION END -->


<!-- WEBINARS -->
<section class="section" id="webinars">
  <div class="container">
    <div class="text-center mb-5">
      
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
          <a href="<?= url('assignments.php') ?>" class="btn-primary-cyber"><i class="fas fa-briefcase me-2"></i>Assignments</a>
          <a href="<?= url('enquire-enroll.php') ?>" class="btn-outline-cyber"><i class="fas fa-graduation-cap me-2"></i>Register for a Bootcamp</a>
          <a href="<?= url('register-freelancer.php') ?>" class="btn-outline-cyber"><i class="fas fa-user-tie me-2"></i>Register as a Freelancer</a>
        </div>
      </div>
    </div>
    <br><br>
      <h2 class="section-title mt-3">Upcoming <span class="accent">Webinars</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Expert-led live sessions on the latest cybersecurity topics</p>
    </div>
    <div class="row g-4">
      <?php foreach ($webinars as $w): ?>
      <div class="col-md-6 col-lg-4 fade-in">
        <div class="webinar-card">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="webinar-badge <?= $w['is_free'] ? 'badge-free' : 'badge-paid' ?>">
              <?= $w['is_free'] ? 'FREE' : formatRupee((float) $w['fee']) ?>
            </span>
            <?php if ($w['status'] === 'live'): ?>
            <span class="webinar-badge badge-live"><i class="fas fa-circle me-1" style="font-size:0.6rem"></i>LIVE</span>
            <?php endif; ?>
          </div>
          <h3 class="webinar-title"><?= htmlspecialchars($w['title']) ?></h3>
          <p class="webinar-desc"><?= htmlspecialchars($w['short_desc']) ?></p>
          <div class="webinar-meta">
            <span><i class="fa-solid fa-calendar-days me-1" aria-hidden="true"></i><?= date('d M Y', strtotime($w['scheduled_at'])) ?></span>
            <span><i class="fa-solid fa-clock me-1" aria-hidden="true"></i><?= date('h:i A', strtotime($w['scheduled_at'])) ?></span>
            <span><i class="fa-solid fa-hourglass-half me-1" aria-hidden="true"></i><?= (int) $w['duration_mins'] ?> min</span>
          </div>
          <?php if ($w['instructor']): ?>
          <div class="webinar-instructor">
            <div class="instructor-avatar"><?= strtoupper(substr($w['instructor'], 0, 1)) ?></div>
            <span class="instructor-name"><?= htmlspecialchars($w['instructor']) ?></span>
          </div>
          <?php endif; ?>
          <?php
            $fillPct = $w['max_seats'] > 0 ? min(100, ($w['registered_seats'] / $w['max_seats']) * 100) : 0;
          ?>
          <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.8rem; color:var(--cyber-muted)">
            <span><?= $w['registered_seats'] ?> registered</span>
            <span><?= $w['max_seats'] - $w['registered_seats'] ?> seats left</span>
          </div>
          <div class="seats-bar"><div class="seats-fill" style="width:<?= $fillPct ?>%"></div></div>
          <div class="mt-3">
            <a href="<?= url('webinar.php?slug=' . urlencode($w['slug'])) ?>" class="btn-primary-cyber w-100 text-center" style="font-size:0.9rem; padding:0.65rem">
              <?= $w['is_free'] ? '<i class="fas fa-bolt me-1"></i>Register Free' : '<i class="fas fa-ticket me-1"></i>Register Now' ?>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($webinars)): ?>
      <div class="col-12 text-center" style="color:var(--cyber-muted); padding:3rem">
        <i class="fas fa-calendar-xmark" style="font-size:3rem; opacity:0.3"></i>
        <p class="mt-2">No upcoming webinars. Check back soon!</p>
      </div>
      <?php endif; ?>
    </div>
    <div class="text-center mt-4">
      <a href="<?= url('webinars.php') ?>" class="btn-outline-cyber">View All Webinars <i class="fas fa-arrow-right ms-2"></i></a>
    </div>
  </div>
</section>

<!-- BOOTCAMPS -->
<section class="section" id="bootcamps" style="background:rgba(10,22,40,0.5)">
  <div class="container">
    <div class="text-center mb-5">
      
      <h2 class="section-title mt-3">Cybersecurity <span class="accent">Bootcamps</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Hands-on intensive training programs with certificate of completion</p>
    </div>
    <div class="row g-4">
      <?php foreach ($bootcamps as $b): ?>
      <div class="col-md-6 fade-in">
        <div class="bootcamp-card">
          <div class="bootcamp-header">
            <div class="bootcamp-price">
              <div class="price-original">₹<?= number_format($b['original_fee']) ?></div>
              <div class="price-current">₹<?= number_format($b['discounted_fee']) ?></div>
            </div>
            <span class="webinar-badge badge-free mb-2"><?= htmlspecialchars($b['category']) ?></span>
            <h3 style="font-family:'Rajdhani',sans-serif; font-size:1.4rem; font-weight:600; color:#fff; margin-top:0.5rem"><?= htmlspecialchars($b['title']) ?></h3>
            <div style="color:rgba(255,255,255,0.6); font-size:0.85rem; margin-top:0.5rem">
              <i class="far fa-calendar me-1"></i><?= date('d M', strtotime($b['start_date'])) ?> “ <?= date('d M Y', strtotime($b['end_date'])) ?>
              <span class="ms-3"><i class="fas fa-clock me-1"></i><?= $b['duration_weeks'] ?> Weeks</span>
            </div>
          </div>
          <div class="bootcamp-body">
            <p style="color:var(--cyber-muted); font-size:0.88rem; margin-bottom:1rem"><?= htmlspecialchars($b['short_desc']) ?></p>
            <ul class="feature-list">
              <li><i class="fa-solid fa-flask" aria-hidden="true"></i> Hands-on lab exercises &amp; CTF challenges</li>
              <li><i class="fa-solid fa-user-tie" aria-hidden="true"></i> Industry expert mentorship</li>
              <?php if ($b['certificate']): ?><li><i class="fa-solid fa-certificate" aria-hidden="true"></i> Certificate of completion</li><?php endif; ?>
              <li><i class="fa-solid fa-users" aria-hidden="true"></i> <?= (int) ($b['total_seats'] - $b['enrolled_seats']) ?> seats remaining</li>
              <li><i class="fa-solid fa-coins" aria-hidden="true"></i> Earn <?= (int) NXL_BOOTCAMP_REWARD ?> NxL tokens on enrollment</li>
            </ul>
            <a href="<?= url('bootcamp.php?slug=' . urlencode($b['slug'])) ?>" class="btn-primary-cyber w-100 text-center" style="font-size:0.9rem">
              <i class="fas fa-rocket me-2"></i>Enroll Now ₹<?= number_format($b['discounted_fee']) ?>
            </a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- PRICING -->
<section class="section" id="pricing">
  <div class="container">
    <div class="text-center mb-5">
      <h2 class="section-title">Simple <span class="accent">Pricing</span></h2>
      <div class="divider mx-auto"></div>
      <p class="section-subtitle">Choose the plan that suits your learning journey</p>
    </div>
    <div class="row g-4 justify-content-center">
      <div class="col-md-4">
        <div class="pricing-card">
          <div class="pricing-name">Starter</div>
          <div class="pricing-price">Free</div>
          <div class="pricing-period">Forever free</div>
          <ul class="pricing-features">
            <li><i class="fas fa-check"></i>Access to free webinars</li>
            <li><i class="fas fa-check"></i>NxL wallet & tokens</li>
            <li><i class="fas fa-check"></i>Community access</li>
            <li><i class="fas fa-check"></i>Referral rewards</li>
            <li style="opacity:0.4"><i class="fas fa-times"></i>Paid webinars</li>
            <li style="opacity:0.4"><i class="fas fa-times"></i>Bootcamp access</li>
          </ul>
          <a href="<?= url('enquire-enroll.php') ?>" class="btn-outline-cyber w-100 text-center">Get Started</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="pricing-card featured">
          <div class="pricing-name" style="margin-top:1rem">Pro Learner</div>
          <div class="pricing-price">₹999</div>
          <div class="pricing-period">per month</div>
          <ul class="pricing-features">
            <li><i class="fas fa-check"></i>Unlimited webinar access</li>
            <li><i class="fas fa-check"></i>50% off bootcamps</li>
            <li><i class="fas fa-check"></i>Priority NxL rewards</li>
            <li><i class="fas fa-check"></i>Certificate of completion</li>
            <li><i class="fas fa-check"></i>1:1 mentorship sessions</li>
            <li><i class="fas fa-check"></i>Career support</li>
          </ul>
          <a href="<?= url('enquire-enroll.php?plan=pro-trial') ?>" class="btn-primary-cyber w-100 text-center">Start Pro Trial</a>
        </div>
      </div>
      <div class="col-md-4">
        <div class="pricing-card">
          <div class="pricing-name">Enterprise</div>
          <div class="pricing-price">Custom</div>
          <div class="pricing-period">For teams & institutes</div>
          <ul class="pricing-features">
            <li><i class="fas fa-check"></i>Team dashboards</li>
            <li><i class="fas fa-check"></i>Bulk enrollments</li>
            <li><i class="fas fa-check"></i>Custom training modules</li>
            <li><i class="fas fa-check"></i>Dedicated support</li>
            <li><i class="fas fa-check"></i>White-label options</li>
            <li><i class="fas fa-check"></i>API access</li>
          </ul>
          <a href="<?= url('contact.php') ?>" class="btn-outline-cyber w-100 text-center">Contact Us</a>
        </div>
      </div>
    </div>
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
        <div class="divider"></div>
        <p style="color:var(--cyber-muted); line-height:1.8">Have questions about our programs? Want to collaborate? We're here to help you navigate your cybersecurity career path.</p>
        <div class="mt-4">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:40px;height:40px;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center;">
              <i class="fas fa-envelope" style="color:var(--cyber-accent)"></i>
            </div>
            <div><div style="font-size:0.8rem;color:var(--cyber-muted)">Email</div><div style="font-size:0.9rem">info@CYBEORCH.com</div></div>
          </div>
          <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:40px;height:40px;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center;">
              <i class="fab fa-whatsapp" style="color:var(--cyber-green)"></i>
            </div>
            <div><div style="font-size:0.8rem;color:var(--cyber-muted)">WhatsApp</div><div style="font-size:0.9rem">+91 97640 96069</div></div>
          </div>
          <div class="d-flex align-items-center gap-3 mb-3">
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
<!-- Mandatory registration popup — POST only, cannot close without registering -->
<div class="cybeorch-reg-overlay" id="cybeorchRegOverlay" role="dialog" aria-modal="true" aria-labelledby="cybeorchRegTitle">
  <div class="cybeorch-reg-card">
    <div class="cybeorch-reg-brand">
      <h2 id="cybeorchRegTitle">WELCOME TO <span class="accent">CYBEORCH</span></h2>
    </div>
    <p class="cybeorch-reg-tagline">Registration is required to access this website. Complete the form below to continue.</p>

    <?php if ($websiteRegError !== ''): ?>
    <div class="cybeorch-reg-alert cybeorch-reg-alert-error" role="alert">
      <i class="fas fa-exclamation-circle"></i><span><?= htmlspecialchars($websiteRegError) ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['register_required'])): ?>
    <div class="cybeorch-reg-alert cybeorch-reg-alert-info" role="status">
      <i class="fas fa-lock"></i><span>Please register to unlock webinars, programs, and your dashboard.</span>
    </div>
    <?php endif; ?>

    <div id="cybeorchRegAjaxAlert" class="cybeorch-reg-alert cybeorch-reg-alert-info" role="status" hidden></div>

    <div class="cybeorch-reg-scroll">
    <form id="cybeorchRegForm" novalidate
      data-send-url="<?= htmlspecialchars(url('send_otp.php')) ?>"
      data-verify-url="<?= htmlspecialchars(url('verify_otp.php')) ?>"
      data-register-url="<?= htmlspecialchars(url('register-website.php')) ?>"
      data-success-url="<?= htmlspecialchars(url('index.php?registered=1')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <input type="text" name="company_url" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="cybeorch-reg-field">
        <label for="regFullName">Full Name *</label>
        <input type="text" name="full_name" id="regFullName" placeholder="Your full name" required minlength="2" maxlength="255" autocomplete="name" value="<?= htmlspecialchars($interestPrefillName) ?>">
      </div>

      <div class="cybeorch-reg-field">
        <label for="regMobile">Contact Number *</label>
        <input type="tel" name="mobile" id="regMobile" placeholder="10-digit mobile number" required minlength="8" maxlength="20" autocomplete="tel" value="<?= htmlspecialchars($interestPrefillPhone) ?>">
      </div>

      <div class="cybeorch-reg-field">
        <label for="regEmail">Email Address *</label>
        <div class="cybeorch-reg-email-row">
          <input type="email" name="email" id="regEmail" placeholder="you@email.com" required maxlength="255" autocomplete="email" value="<?= htmlspecialchars($interestPrefillEmail) ?>">
          <button type="button" class="cybeorch-reg-btn-secondary" id="cybeorchBtnSendOtp">
            <span class="cybeorch-reg-spinner" aria-hidden="true"></span>
            <span>Send OTP</span>
          </button>
        </div>
      </div>

      <div class="cybeorch-reg-field cybeorch-reg-otp-block">
        <label for="regOtp">OTP <span class="cybeorch-reg-otp-hint">(Email Verification) *</span></label>
        <div class="cybeorch-reg-otp-row">
          <input type="text" name="otp" id="regOtp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP" autocomplete="one-time-code" aria-required="true">
          <button type="button" class="cybeorch-reg-btn-secondary" id="cybeorchBtnVerifyOtp">
            <span class="cybeorch-reg-spinner" aria-hidden="true"></span>
            <span>Verify</span>
          </button>
        </div>
        <p class="cybeorch-reg-timer" id="cybeorchOtpTimer" aria-live="polite"></p>
        <div class="cybeorch-reg-otp-actions">
          <button type="button" class="cybeorch-reg-btn-secondary" id="cybeorchBtnResendOtp" disabled>Resend OTP</button>
        </div>
      </div>

      <div id="registerAfterOtp" class="register-after-otp">
        <div class="cybeorch-reg-field">
          <label for="password">Password *</label>
          <input type="password" name="password" id="password" placeholder="Min 8 chars, 1 uppercase, 1 number" required autocomplete="new-password" disabled>
        </div>

        <div class="cybeorch-reg-field">
          <label for="confirm_pass">Confirm Password *</label>
          <input type="password" name="confirm_password" id="confirm_pass" placeholder="Repeat password" required autocomplete="new-password" disabled>
        </div>

        <button type="submit" class="cybeorch-reg-submit" id="cybeorchRegSubmit" disabled aria-disabled="true">
          <span class="cybeorch-reg-spinner" aria-hidden="true"></span>
          <span><i class="fas fa-user-plus"></i> Sign Up</span>
        </button>
      </div>
    </form>
    <div class="cybeorch-reg-footer">
      <p class="cybeorch-reg-footer-note">By registering you agree to our <a href="<?= url('terms.php') ?>" target="_blank" rel="noopener">Terms</a> and <a href="<?= url('privacy.php') ?>" target="_blank" rel="noopener">Privacy Policy</a>.</p>
      <p class="cybeorch-reg-footer-note">Already have an account? <a href="<?= url('login.php') ?>">Sign in</a></p>
    </div>
    </div>
  </div>
</div>
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
</body>
</html>
