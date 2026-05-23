<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';
require_once __DIR__ . '/includes/contact-messages.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        redirectWith('contact.php', 'error', 'Invalid form submission. Please try again.');
    }

    $name    = sanitize($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = sanitize($_POST['phone'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '') ?: 'Contact';
    $message = sanitize($_POST['message'] ?? '');

    if (strlen($name) < 2 || !isValidEmail($email) || strlen($message) < 10) {
        redirectWith('contact.php', 'error', 'Please fill in all required fields correctly.');
    }

    try {
        insertContactMessage($name, $email, $phone, $subject, $message);
        redirectWith('contact.php', 'success', 'Thank you! We\'ll get back to you within 24 hours.');
    } catch (Throwable $e) {
        redirectWith('contact.php', 'error', 'Could not send your message. Please try again later.');
    }
}

$prefillSubject = sanitize($_GET['subject'] ?? '');

$pageTitle = 'Contact Us';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> â€“ CYBEORCH LAB</title>
<meta name="description" content="Contact CYBEORCH LAB for webinars, bootcamps, consulting, and collaboration enquiries.">
<?php renderPublicPageHead(); ?>
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
.page-hero p{color:var(--cyber-muted);max-width:640px;margin:0 auto;line-height:1.8}
.section-title{font-family:'Rajdhani',sans-serif;font-size:clamp(1.8rem,4vw,2.5rem);font-weight:700;margin-bottom:.5rem}
.section-title .accent{color:var(--cyber-accent)}
.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin-bottom:1.5rem}
.contact-form{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:2.5rem}
.contact-info-card{background:rgba(10,22,40,0.95);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;margin-bottom:1rem}
.contact-info-card i{color:var(--cyber-accent);width:20px}
.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:6px!important;padding:.75rem 1rem!important}
.form-control:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.1)!important}
.form-control::placeholder{color:var(--cyber-muted)!important}
.form-label{color:var(--cyber-muted);font-size:.88rem;margin-bottom:.4rem}
.btn-primary-cyber{background:var(--cyber-accent);color:#050b18;border:none;padding:.85rem 1.5rem;border-radius:6px;font-weight:700;font-size:1rem;transition:all .2s;cursor:pointer}
.btn-primary-cyber:hover{background:var(--cyber-green);transform:translateY(-2px)}
@media(max-width:767px){.section{padding:3rem 0}}
</style>
</head>
<body>

<?php $navActive = $navActive ?? 'contact'; require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1>Get In <span class="accent">Touch</span></h1>
    <p>Have questions about webinars, bootcamps, or consulting? Send us a message and our team will respond within 24 hours.</p>
  </div>
</header>

<main class="section pt-0" id="contact">
  <div class="container">
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2 class="section-title">Contact <span class="accent">Info</span></h2>
        <div class="divider"></div>
        <p style="color:var(--cyber-muted);line-height:1.8;margin-bottom:2rem">Reach out for program enquiries, partnerships, corporate training, or technical support.</p>

        <div class="contact-info-card d-flex align-items-center gap-3">
          <div style="width:44px;height:44px;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="fas fa-envelope"></i>
          </div>
          <div>
            <div style="font-size:.8rem;color:var(--cyber-muted)">Email</div>
            <div><a href="mailto:<?= htmlspecialchars(SITE_EMAIL) ?>" style="color:var(--cyber-text)"><?= htmlspecialchars(SITE_EMAIL) ?></a></div>
          </div>
        </div>

        <div class="contact-info-card d-flex align-items-center gap-3">
          <div style="width:44px;height:44px;background:rgba(0,255,136,0.1);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="fab fa-whatsapp" style="color:var(--cyber-green)"></i>
          </div>
          <div>
            <div style="font-size:.8rem;color:var(--cyber-muted)">WhatsApp</div>
            <div>+91 97640 96069</div>
          </div>
        </div>

        <div class="contact-info-card d-flex align-items-center gap-3">
          <div style="width:44px;height:44px;background:rgba(255,255,255,0.08);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="fab fa-x-twitter" style="color:var(--cyber-text)"></i>
          </div>
          <div>
            <div style="font-size:.8rem;color:var(--cyber-muted)">X (Twitter)</div>
            <div>
              <a href="<?= htmlspecialchars(SITE_TWITTER_URL) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--cyber-accent)">@<?= htmlspecialchars(SITE_TWITTER_HANDLE) ?></a>
            </div>
          </div>
        </div>

       <!-- <div class="contact-info-card d-flex align-items-center gap-3">
          <div style="width:44px;height:44px;background:rgba(114,137,218,0.15);border:1px solid var(--cyber-border);border-radius:8px;display:flex;align-items:center;justify-content:center">
            <i class="fab fa-discord" style="color:#7289da"></i>
          </div>
          <div>
            <div style="font-size:.8rem;color:var(--cyber-muted)">Discord</div>
            <div>discord.gg/CYBEORCHlab</div>
          </div>
        </div> -->

        <div class="contact-info-card">
          <div style="font-size:.8rem;color:var(--cyber-muted);margin-bottom:.5rem">Office hours</div>
          <div><i class="fas fa-clock me-2"></i>Mon - Sat, 10:00 AM 7:00 PM IST</div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="contact-form">
          <h2 class="section-title" style="font-size:1.5rem">Send a <span class="accent">Message</span></h2>
          <div class="divider"></div>
          <?= showFlash() ?>
          <form method="POST" action="<?= url('contact.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Your Name *</label>
                <input type="text" name="name" class="form-control" placeholder="John Doe" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" placeholder="john@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number</label>
                <input type="tel" name="phone" class="form-control" placeholder="+91 XXXXX XXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="Webinar enquiry" value="<?= htmlspecialchars($_POST['subject'] ?? $prefillSubject) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Message *</label>
                <textarea name="message" class="form-control" rows="5" placeholder="Tell us what's on your mind..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
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
</main>

<?php renderPublicFooter(); ?>

<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
