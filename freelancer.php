<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/public-footer.php';

startSession();

$navActive = 'freelancers';
$registerUrl = url('register-freelancer.php');

$roleLabels = [
    'developer'     => 'Software Developer',
    'designer'      => 'UI/UX Designer',
    'cybersecurity' => 'Cybersecurity',
    'devops'        => 'DevOps / Cloud',
    'qa'            => 'QA / Testing',
    'data'          => 'Data / AI',
    'mobile'        => 'Mobile Developer',
    'other'         => 'Other',
];

$categoryMeta = [
    'developer'     => ['icon' => 'fa-code', 'desc' => 'Web, API, and full-stack builds for client and internal products.'],
    'designer'      => ['icon' => 'fa-palette', 'desc' => 'Product UI, design systems, and user research for digital experiences.'],
    'cybersecurity' => ['icon' => 'fa-shield-halved', 'desc' => 'Assessments, hardening, SOC workflows, and secure architecture reviews.'],
    'devops'        => ['icon' => 'fa-cloud', 'desc' => 'CI/CD, cloud infra, monitoring, and deployment automation.'],
    'qa'            => ['icon' => 'fa-bug', 'desc' => 'Manual and automated testing, test plans, and release quality gates.'],
    'data'          => ['icon' => 'fa-brain', 'desc' => 'Analytics pipelines, ML integrations, and data product features.'],
    'mobile'        => ['icon' => 'fa-mobile-screen', 'desc' => 'Android, iOS, and cross-platform app delivery.'],
    'other'         => ['icon' => 'fa-layer-group', 'desc' => 'Specialized contributors across emerging tech domains.'],
];

$availabilityOptions = [
    'full_time'     => ['label' => 'Full-time', 'icon' => 'fa-clock', 'desc' => 'Dedicated weekly capacity for ongoing product or client work.'],
    'part_time'     => ['label' => 'Part-time', 'icon' => 'fa-calendar-week', 'desc' => 'Consistent hours alongside studies or another role.'],
    'project_based' => ['label' => 'Project-based', 'icon' => 'fa-diagram-project', 'desc' => 'Scoped deliverables with clear milestones and handoffs.'],
];

$experienceLabels = [
    'fresher' => 'Fresher / User Registration & Trainee',
    '1-2'     => '1–2 years',
    '3-5'     => '3–5 years',
    '5+'      => '5+ years',
];

$networkMembers = [];
$portfolioItems = [];
try {
    $networkMembers = db()->fetchAll(
        "SELECT full_name, primary_role, experience_level, skills, availability, location,
                portfolio_url, github_url, linkedin_url, about, status
         FROM freelancer_registrations
         WHERE status IN ('active', 'shortlisted')
         ORDER BY updated_at DESC
         LIMIT 12"
    );
    foreach ($networkMembers as $m) {
        if (!empty($m['portfolio_url']) || !empty($m['github_url']) || !empty($m['linkedin_url'])) {
            $portfolioItems[] = $m;
        }
    }
} catch (Throwable $e) {
    $networkMembers = [];
}

$featuredNetwork = [
    [
        'full_name'         => 'Arjun Mehta',
        'primary_role'      => 'developer',
        'experience_level'  => '3-5',
        'availability'      => 'project_based',
        'location'          => 'Mumbai, India',
        'skills'            => 'PHP, Laravel, React, REST APIs, MySQL',
        'about'             => 'Full-stack developer focused on secure web platforms and SaaS MVPs for startups and enterprise clients.',
        'portfolio_url'     => '',
        'github_url'        => 'https://github.com',
        'linkedin_url'      => 'https://linkedin.com',
        'featured'          => true,
    ],
    [
        'full_name'         => 'Priya Sharma',
        'primary_role'      => 'cybersecurity',
        'experience_level'  => '1-2',
        'availability'      => 'part_time',
        'location'          => 'Remote',
        'skills'            => 'Penetration Testing, OWASP, VAPT, Network Security',
        'about'             => 'Cybersecurity specialist supporting assessments, hardening, and secure SDLC reviews for client engagements.',
        'portfolio_url'     => '',
        'github_url'        => '',
        'linkedin_url'      => 'https://linkedin.com',
        'featured'          => true,
    ],
    [
        'full_name'         => 'Rahul Desai',
        'primary_role'      => 'devops',
        'experience_level'  => '5+',
        'availability'      => 'full_time',
        'location'          => 'Pune, India',
        'skills'            => 'AWS, Docker, Kubernetes, CI/CD, Linux',
        'about'             => 'Cloud and DevOps engineer delivering scalable deployment pipelines and production observability.',
        'portfolio_url'     => '',
        'github_url'        => 'https://github.com',
        'linkedin_url'      => '',
        'featured'          => true,
    ],
    [
        'full_name'         => 'Sneha Iyer',
        'primary_role'      => 'designer',
        'experience_level'  => '3-5',
        'availability'      => 'project_based',
        'location'          => 'Bangalore, India',
        'skills'            => 'Figma, UI/UX, Design Systems, Prototyping',
        'about'             => 'Product designer crafting intuitive dashboards, mobile flows, and brand-aligned design systems.',
        'portfolio_url'     => 'https://example.com',
        'github_url'        => '',
        'linkedin_url'      => 'https://linkedin.com',
        'featured'          => true,
    ],
    [
        'full_name'         => 'Vikram Patel',
        'primary_role'      => 'data',
        'experience_level'  => '3-5',
        'availability'      => 'part_time',
        'location'          => 'Ahmedabad, India',
        'skills'            => 'Python, Machine Learning, SQL, Power BI',
        'about'             => 'Data and AI contributor building analytics pipelines and ML-powered product features.',
        'portfolio_url'     => '',
        'github_url'        => 'https://github.com',
        'linkedin_url'      => 'https://linkedin.com',
        'featured'          => true,
    ],
    [
        'full_name'         => 'Ananya Reddy',
        'primary_role'      => 'mobile',
        'experience_level'  => '1-2',
        'availability'      => 'project_based',
        'location'          => 'Hyderabad, India',
        'skills'            => 'Flutter, React Native, Android, Firebase',
        'about'             => 'Mobile developer shipping cross-platform apps with performance-focused architecture.',
        'portfolio_url'     => '',
        'github_url'        => 'https://github.com',
        'linkedin_url'      => '',
        'featured'          => true,
    ],
];

$displayNetwork = !empty($networkMembers) ? $networkMembers : $featuredNetwork;
$networkIsFeatured = empty($networkMembers);

if (empty($portfolioItems) && $networkIsFeatured) {
    foreach ($featuredNetwork as $m) {
        if (!empty($m['portfolio_url']) || !empty($m['github_url']) || !empty($m['linkedin_url'])) {
            $portfolioItems[] = $m;
        }
    }
}

$skillTags = [
    'Development'   => ['Flutter', 'React', 'Next.js', 'Node.js', 'Python', 'Java', 'Spring Boot', 'Solidity', 'PHP', 'Laravel', 'REST APIs'],
    'Cloud & Ops'   => ['AWS', 'Firebase', 'Docker', 'Kubernetes', 'CI/CD', 'Linux'],
    'Databases'     => ['PostgreSQL', 'MySQL'],
    'Security'      => ['Penetration Testing', 'OWASP', 'SIEM', 'Threat Modeling', 'SOC'],
    'Design & Data' => ['Figma', 'UI/UX', 'Power BI', 'Machine Learning'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<?php renderStandardViewport(); ?>
<title>Freelancers – CYBEORCH</title>
<meta name="description" content="Join the CYBEORCH freelancer network. Explore categories, skills, portfolio work, availability, and register for project assignments.">
<?php renderPublicPageHead(); ?>
<style>
:root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-orange:#ff6b35;--cyber-card:rgba(10,22,40,0.95);}
*{box-sizing:border-box}
body{margin:0;background:var(--cyber-dark);color:var(--cyber-text);font-family:'DM Sans',sans-serif}
a{text-decoration:none}
.section{padding:5rem 0}
.section-title{font-family:'Rajdhani',sans-serif;font-size:clamp(1.75rem,4vw,2.5rem);font-weight:700;margin-bottom:.5rem}
.section-title .accent{color:var(--cyber-accent)}
.section-subtitle{color:var(--cyber-muted);max-width:640px;line-height:1.75}
.hero-title{font-family:'Rajdhani',sans-serif;font-size:clamp(2.5rem,6vw,4rem);font-weight:700;line-height:1.1;margin-bottom:1rem}
.hero-title .accent{color:var(--cyber-accent)}
.hero-copy{color:var(--cyber-muted);max-width:720px;margin:0 auto;font-size:1.05rem;line-height:1.8}
.section-pill-nav{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center;margin-top:2rem}
.section-pill-nav a{display:inline-block;padding:.45rem .9rem;border:1px solid var(--cyber-border);border-radius:999px;color:var(--cyber-muted);font-size:.82rem;font-weight:500;transition:all .2s}
.section-pill-nav a:hover,.section-pill-nav a:focus{color:var(--cyber-accent);border-color:var(--cyber-accent);background:rgba(0,212,255,.06)}
.f-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:1rem;padding:1.75rem;height:100%;transition:border-color .2s,transform .2s,box-shadow .2s}
.f-card:hover{border-color:rgba(0,212,255,.4);transform:translateY(-3px);box-shadow:0 10px 32px rgba(0,212,255,.1)}
.f-card .icon-wrap{width:2.75rem;height:2.75rem;border-radius:.65rem;background:rgba(0,212,255,.08);border:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:center;color:var(--cyber-accent);font-size:1.1rem;margin-bottom:1rem}
.f-card h3,.f-card h4{font-family:'Rajdhani',sans-serif;margin-bottom:.5rem;font-size:1.2rem}
.f-card p{color:var(--cyber-muted);font-size:.92rem;line-height:1.65;margin:0}
.f-badge{display:inline-block;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;padding:.25rem .55rem;border-radius:4px;background:rgba(0,255,136,.12);color:var(--cyber-green);border:1px solid rgba(0,255,136,.25);margin-bottom:.75rem}
.f-badge--role{background:rgba(0,212,255,.1);color:var(--cyber-accent);border-color:rgba(0,212,255,.25)}
.f-badge--featured{background:rgba(255,107,53,.15);color:var(--cyber-orange);border-color:rgba(255,107,53,.35)}
.network-avatar{width:3.25rem;height:3.25rem;border-radius:50%;background:linear-gradient(135deg,var(--cyber-accent),var(--cyber-green));color:#050b18;font-family:'Rajdhani',sans-serif;font-weight:700;font-size:1.1rem;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;flex-shrink:0}
.network-card-head{display:flex;align-items:flex-start;gap:1rem}
.network-skills{margin-top:.75rem;display:flex;flex-wrap:wrap;gap:.35rem}
.network-skill{font-size:.72rem;padding:.2rem .55rem;border-radius:4px;background:rgba(0,212,255,.06);border:1px solid var(--cyber-border);color:var(--cyber-muted)}
.skill-tag{display:inline-block;padding:.35rem .75rem;margin:.25rem;border:1px solid var(--cyber-border);border-radius:999px;font-size:.8rem;color:var(--cyber-text);background:rgba(0,212,255,.05)}
.exp-tier{display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem}
.exp-tier span{flex:1;min-width:140px;text-align:center;padding:1rem;border:1px solid var(--cyber-border);border-radius:.75rem;background:rgba(0,212,255,.04);font-family:'Rajdhani',sans-serif;font-weight:600}
.portfolio-link{display:inline-flex;align-items:center;gap:.4rem;color:var(--cyber-accent);font-size:.88rem;margin-right:1rem;margin-top:.5rem}
.portfolio-link:hover{color:var(--cyber-green)}
.btn-primary-cyber{display:inline-block;background:var(--cyber-accent);color:#050b18;padding:.85rem 1.75rem;border-radius:8px;font-weight:700;border:none;transition:all .2s}
.btn-primary-cyber:hover{background:var(--cyber-green);color:#050b18}
.btn-outline-cyber{display:inline-block;background:transparent;border:1px solid var(--cyber-border);color:var(--cyber-accent);padding:.85rem 1.75rem;border-radius:8px;font-weight:600;margin-left:.75rem;transition:all .2s}
.btn-outline-cyber:hover{border-color:var(--cyber-accent);background:rgba(0,212,255,.08)}
.empty-note{color:var(--cyber-muted);font-size:.95rem;text-align:center;padding:2rem;border:1px dashed var(--cyber-border);border-radius:1rem}
.fade-in{opacity:0;transform:translateY(20px);transition:all .6s ease}
.fade-in.visible{opacity:1;transform:translateY(0)}
@media(max-width:767px){.section{padding:3rem 0}}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="section text-center pb-0">
  <div class="container">
    <h1 class="hero-title"><span class="accent">Freelancer</span> Network</h1>
    <p class="hero-copy">Connect with CYBEORCH for client projects, product builds, cybersecurity assignments, and startup support. Browse categories, explore the network, and apply to join.</p>
    <div class="mt-3">
      <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn-primary-cyber"><i class="fas fa-user-plus me-2"></i>Register as a Freelancer</a>
    </div>
    <nav class="section-pill-nav" aria-label="Page sections">
      <a href="#categories">Categories</a>
      <a href="#network">Network</a>
      <a href="#skills">Skills &amp; Experience</a>
      <a href="#portfolio">Portfolio</a>
      <a href="#availability">Availability</a>
    </nav>
  </div>
</header>

<main>
  <!-- Freelancer Categories -->
  <section class="section pt-4" id="categories">
    <div class="container">
      <h2 class="section-title text-center">Freelancer <span class="accent">Categories</span></h2>
      <p class="section-subtitle text-center mx-auto mb-5">Choose the discipline that best matches your expertise. We route assignments by role and skill fit.</p>
      <div class="row g-4">
        <?php foreach ($roleLabels as $key => $label):
            $meta = $categoryMeta[$key] ?? ['icon' => 'fa-user', 'desc' => ''];
        ?>
        <div class="col-md-6 col-lg-3 fade-in">
          <div class="f-card">
            <div class="icon-wrap"><i class="fas <?= htmlspecialchars($meta['icon']) ?>"></i></div>
            <h3><?= htmlspecialchars($label) ?></h3>
            <p><?= htmlspecialchars($meta['desc']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Freelancer Network -->
  <section class="section" id="network" style="background:rgba(10,22,40,.5)">
    <div class="container">
      <h2 class="section-title text-center">Freelancer <span class="accent">Network</span></h2>
      <p class="section-subtitle text-center mx-auto mb-4">
        <?php if ($networkIsFeatured): ?>
        Explore our talent network across development, security, cloud, design, and data. <a href="<?= htmlspecialchars($registerUrl) ?>" style="color:var(--cyber-accent)">Register</a> to join the live roster.
        <?php else: ?>
        Shortlisted and active contributors in our talent pool.
        <?php endif; ?>
      </p>
      <?php if ($networkIsFeatured): ?>
      <p class="text-center mb-4" style="font-size:.82rem;color:var(--cyber-muted)"><i class="fas fa-info-circle me-1" style="color:var(--cyber-orange)"></i>Sample network profiles shown until approved freelancers are added from registrations.</p>
      <?php endif; ?>
      <div class="row g-4">
        <?php foreach ($displayNetwork as $m):
            $role = $roleLabels[$m['primary_role']] ?? ucfirst((string) $m['primary_role']);
            $exp = $experienceLabels[$m['experience_level']] ?? $m['experience_level'];
            $avail = $availabilityOptions[$m['availability']]['label'] ?? $m['availability'];
            $initials = '';
            foreach (preg_split('/\s+/', trim($m['full_name']), 2) as $part) {
                if ($part !== '') {
                    $initials .= strtoupper($part[0]);
                }
            }
            $initials = substr($initials, 0, 2) ?: 'F';
            $skillList = array_filter(array_map('trim', explode(',', $m['skills'] ?? '')));
            $skillPreview = array_slice($skillList, 0, 4);
            $isFeatured = !empty($m['featured']);
            $aboutText = $m['about'] ?? '';
            if (function_exists('mb_strimwidth')) {
                $aboutText = mb_strimwidth($aboutText, 0, 130, '…');
            } elseif (strlen($aboutText) > 130) {
                $aboutText = substr($aboutText, 0, 130) . '…';
            }
        ?>
        <div class="col-md-6 col-lg-4 fade-in">
          <div class="f-card">
            <div class="network-card-head">
              <div class="network-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
              <div>
                <?php if ($isFeatured): ?>
                <span class="f-badge f-badge--featured">Network</span>
                <?php else: ?>
                <span class="f-badge">Verified</span>
                <?php endif; ?>
                <span class="f-badge f-badge--role"><?= htmlspecialchars($role) ?></span>
                <h4 class="mb-1"><?= htmlspecialchars($m['full_name']) ?></h4>
                <p class="mb-0" style="font-size:.85rem"><i class="fas fa-location-dot me-1" style="color:var(--cyber-accent)"></i><?= htmlspecialchars($m['location'] ?: 'Remote') ?> &middot; <?= htmlspecialchars($exp) ?></p>
              </div>
            </div>
            <p class="mt-3 mb-2"><?= htmlspecialchars($aboutText) ?></p>
            <?php if (!empty($skillPreview)): ?>
            <div class="network-skills">
              <?php foreach ($skillPreview as $sk): ?>
              <span class="network-skill"><?= htmlspecialchars($sk) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p class="mt-3 mb-2" style="font-size:.8rem;color:var(--cyber-green)"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($avail) ?></p>
            <?php if (!empty($m['github_url']) || !empty($m['linkedin_url']) || !empty($m['portfolio_url'])): ?>
            <div class="mt-2">
              <?php if (!empty($m['portfolio_url'])): ?>
              <a class="portfolio-link" href="<?= htmlspecialchars($m['portfolio_url']) ?>" target="_blank" rel="noopener"><i class="fas fa-globe"></i></a>
              <?php endif; ?>
              <?php if (!empty($m['github_url'])): ?>
              <a class="portfolio-link" href="<?= htmlspecialchars($m['github_url']) ?>" target="_blank" rel="noopener"><i class="fab fa-github"></i></a>
              <?php endif; ?>
              <?php if (!empty($m['linkedin_url'])): ?>
              <a class="portfolio-link" href="<?= htmlspecialchars($m['linkedin_url']) ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin"></i></a>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="text-center mt-4 mb-0 fade-in">
        <a href="<?= htmlspecialchars($registerUrl) ?>" class="btn-primary-cyber"><i class="fas fa-user-plus me-2"></i>Join the Network</a>
      </p>
    </div>
  </section>

  <!-- Skills & Experience -->
  <section class="section" id="skills">
    <div class="container">
      <h2 class="section-title text-center">Skills &amp; <span class="accent">Experience</span></h2>
      <p class="section-subtitle text-center mx-auto mb-4">We match projects to your tech stack, domain knowledge, and experience tier.</p>
      <div class="row g-4 align-items-start">
        <div class="col-lg-6 fade-in">
          <div class="f-card">
            <h3>What we look for</h3>
            <p class="mb-3">Clear skill lists, honest experience levels, and examples of real work help us place you on the right assignments faster.</p>
            <div class="exp-tier">
              <?php foreach ($experienceLabels as $code => $label): ?>
              <span><?= htmlspecialchars($label) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-6 fade-in">
          <div class="f-card">
            <h3>Common skill areas</h3>
            <?php foreach ($skillTags as $group => $tags): ?>
            <p class="mb-1 mt-3" style="font-size:.8rem;color:var(--cyber-accent);font-weight:600;text-transform:uppercase;letter-spacing:.05em"><?= htmlspecialchars($group) ?></p>
            <?php foreach ($tags as $tag): ?>
            <span class="skill-tag"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Portfolio Showcase -->
  <section class="section" id="portfolio" style="background:rgba(10,22,40,.5)">
    <div class="container">
      <h2 class="section-title text-center">Portfolio <span class="accent">Showcase</span></h2>
      <p class="section-subtitle text-center mx-auto mb-5">Approved freelancers with portfolio, GitHub, or LinkedIn links. Add yours when you register.</p>
      <div class="row g-4">
        <?php if (empty($portfolioItems)): ?>
        <div class="col-md-4 fade-in">
          <div class="f-card text-center">
            <div class="icon-wrap mx-auto"><i class="fas fa-globe"></i></div>
            <h4>Portfolio site</h4>
            <p>Share your best case studies and project screenshots.</p>
          </div>
        </div>
        <div class="col-md-4 fade-in">
          <div class="f-card text-center">
            <div class="icon-wrap mx-auto"><i class="fab fa-github"></i></div>
            <h4>GitHub</h4>
            <p>Open-source repos and code samples demonstrate capability.</p>
          </div>
        </div>
        <div class="col-md-4 fade-in">
          <div class="f-card text-center">
            <div class="icon-wrap mx-auto"><i class="fab fa-linkedin"></i></div>
            <h4>LinkedIn</h4>
            <p>Professional history and endorsements from peers and clients.</p>
          </div>
        </div>
        <?php else: foreach (array_slice($portfolioItems, 0, 6) as $p):
            $role = $roleLabels[$p['primary_role']] ?? $p['primary_role'];
        ?>
        <div class="col-md-6 col-lg-4 fade-in">
          <div class="f-card">
            <span class="f-badge"><?= htmlspecialchars($role) ?></span>
            <h4><?= htmlspecialchars($p['full_name']) ?></h4>
            <p class="mb-2"><?= htmlspecialchars(mb_strimwidth($p['about'] ?? 'Contributor profile', 0, 100, '…')) ?></p>
            <?php if (!empty($p['portfolio_url'])): ?>
            <a class="portfolio-link" href="<?= htmlspecialchars($p['portfolio_url']) ?>" target="_blank" rel="noopener"><i class="fas fa-globe"></i> Portfolio</a>
            <?php endif; ?>
            <?php if (!empty($p['github_url'])): ?>
            <a class="portfolio-link" href="<?= htmlspecialchars($p['github_url']) ?>" target="_blank" rel="noopener"><i class="fab fa-github"></i> GitHub</a>
            <?php endif; ?>
            <?php if (!empty($p['linkedin_url'])): ?>
            <a class="portfolio-link" href="<?= htmlspecialchars($p['linkedin_url']) ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin"></i> LinkedIn</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </section>

  <!-- Availability -->
  <section class="section" id="availability">
    <div class="container">
      <h2 class="section-title text-center"><span class="accent">Availability</span></h2>
      <p class="section-subtitle text-center mx-auto mb-5">Tell us how you prefer to work so we can align you with the right engagement model.</p>
      <div class="row g-4">
        <?php foreach ($availabilityOptions as $key => $opt): ?>
        <div class="col-md-4 fade-in">
          <div class="f-card text-center">
            <div class="icon-wrap mx-auto"><i class="fas <?= htmlspecialchars($opt['icon']) ?>"></i></div>
            <h3><?= htmlspecialchars($opt['label']) ?></h3>
            <p><?= htmlspecialchars($opt['desc']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
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
