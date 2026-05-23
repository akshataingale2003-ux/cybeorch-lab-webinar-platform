<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function renderPublicFooterStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('renderSiteResponsiveStyles')) {
        renderSiteResponsiveStyles();
    }
    echo '<style>'
        . 'footer{background:var(--cyber-navy);border-top:1px solid var(--cyber-border);padding:4rem 0 1.5rem;position:relative;z-index:1}'
        . '.footer-logo{display:inline-block}'
        . '.footer-links h6{color:var(--cyber-text);font-weight:600;letter-spacing:1px;font-size:.85rem;text-transform:uppercase;margin-bottom:1rem}'
        . '.footer-links a{color:var(--cyber-muted);text-decoration:none;font-size:.88rem;display:block;margin-bottom:.5rem;transition:color .2s}'
        . '.footer-links a:hover{color:var(--cyber-accent)}'
        . '.social-link{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border:1px solid var(--cyber-border);border-radius:6px;color:var(--cyber-muted);font-size:1rem;margin-right:.5rem;transition:all .2s}'
        . '.social-link:hover{border-color:var(--cyber-accent);color:var(--cyber-accent)}'
        . '.footer-bottom{border-top:1px solid var(--cyber-border);margin-top:3rem;padding-top:1.5rem}'
        . '</style>' . "\n";
}

function renderPublicFooter(): void
{
    ?>
<footer>
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4">
        <a class="footer-logo" href="<?= url('index.php') ?>" aria-label="CYBEORCH home">
          <img src="<?= url('assets/images/Footer_logo.jpeg') ?>" alt="CYBEORCH" style="display:block;max-width:220px;width:100%;height:auto;filter:drop-shadow(0 8px 22px rgba(0,0,0,.35))">
        </a>
        <p style="color:var(--cyber-muted);font-size:.88rem;margin:1rem 0 1.5rem;line-height:1.7">We provide internship opportunities and real-world exposure within our company. Based on performance, we offer placement opportunities to deserving candidates and help them build a successful career.</p>
        <div>
          <a href="<?= htmlspecialchars(SITE_TWITTER_URL) ?>" class="social-link" target="_blank" rel="noopener noreferrer" aria-label="X @<?= htmlspecialchars(SITE_TWITTER_HANDLE) ?>" title="@<?= htmlspecialchars(SITE_TWITTER_HANDLE) ?>"><i class="fab fa-x-twitter"></i></a>
          <a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-link" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
          <a href="#" class="social-link" aria-label="Discord"><i class="fab fa-discord"></i></a>
        </div>
      </div>
      <div class="col-lg-2 col-6 footer-links">
        <h6>Platform</h6>
        <a href="<?= url('webinars.php') ?>">Webinars</a>
        <a href="<?= url('bootcamps.php') ?>">Bootcamps</a>
        <a href="<?= url('index.php') ?>#pricing">Pricing</a>
        <a href="<?= url('dashboard.php') ?>">Dashboard</a>
      </div>
      <div class="col-lg-2 col-6 footer-links">
        <h6>Company</h6>
        <a href="<?= url('about.php') ?>">About Us</a>
        <a href="<?= url('contact.php') ?>">Contact</a>
        <a href="<?= url('freelancer.php') ?>">Freelancers</a>
      </div>
      <div class="col-lg-2 col-6 footer-links">
        <h6>Support</h6>
        <a href="<?= url('supportdesk.php') ?>">Support Desk</a>
        <a href="<?= url('faq.php') ?>">FAQ</a>
        <a href="<?= url('refund.php') ?>">Refund Policy</a>
        <a href="<?= url('privacy.php') ?>">Privacy Policy</a>
        <a href="<?= url('terms.php') ?>">Terms of Service</a>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="row align-items-center">
        <div class="col-md-6" style="color:var(--cyber-muted);font-size:.85rem">&copy; <?= date('Y') ?> CYBEORCH. All rights reserved.</div>
        <div class="col-md-6 text-md-end" style="color:var(--cyber-muted);font-size:.8rem;margin-top:.5rem">
          Built with <span style="color:var(--cyber-orange)">&#9829;</span> for the Cybersecurity Community | Secured with SSL
        </div>
      </div>
    </div>
  </div>
</footer>
    <?php
}
