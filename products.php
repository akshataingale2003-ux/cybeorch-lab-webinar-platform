<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/training-public.php';
require_once __DIR__ . '/includes/projects-data.php';

startSession();

$products = getStudioProducts();
$pageTitle = 'Products';
$navActive = 'products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($pageTitle) ?> – CYBEORCH LAB</title>
<meta name="description" content="CYBEORCH Studio products — fintech, EdTech, retail, and security platforms built for scale.">
<?php renderPublicPageHead(); renderTrainingPublicStyles(); ?>
<style>
  /* Demo modal + toast (matches CYBEORCH theme) */
  .demo-toast-wrap{position:fixed;top:18px;right:18px;z-index:10860;display:flex;flex-direction:column;gap:.6rem}
  .demo-toast{min-width:min(360px,92vw);background:rgba(15,52,96,0.92);border:1px solid rgba(0,212,255,0.28);color:#e0e8f0;border-radius:14px;padding:.85rem 1rem;box-shadow:0 16px 44px rgba(0,0,0,0.45);backdrop-filter:blur(10px);transform:translateY(-6px);opacity:0;transition:all .22s ease}
  .demo-toast.is-show{transform:translateY(0);opacity:1}
  .demo-toast.success{border-color:rgba(0,255,136,0.35)}
  .demo-toast.error{border-color:rgba(255,107,53,0.42)}
  .demo-toast .t-title{font-weight:800;margin-bottom:.2rem}
  .demo-toast .t-msg{color:var(--cyber-muted);font-size:.9rem;line-height:1.5}

  .modal.demo-modal .modal-content{background:rgba(15,52,96,0.55);border:1px solid rgba(0,212,255,0.25);border-radius:16px;backdrop-filter:blur(10px)}
  .modal.demo-modal .modal-header{border-bottom:1px solid rgba(0,212,255,0.16);background:linear-gradient(135deg,rgba(0,212,255,0.10),rgba(0,255,136,0.06))}
  .modal.demo-modal .modal-title{font-family:'Rajdhani',sans-serif;font-weight:800}
  .modal.demo-modal .btn-close{filter:invert(1)}
  .modal.demo-modal .form-label{color:var(--cyber-muted);font-size:.85rem;margin-bottom:.35rem}
  .modal.demo-modal .form-control,.modal.demo-modal .form-select{background:rgba(255,255,255,0.05)!important;border:1px solid rgba(0,212,255,0.25)!important;color:var(--cyber-text)!important;border-radius:10px!important;padding:.8rem 1rem!important}
  .modal.demo-modal .form-control:focus,.modal.demo-modal .form-select:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.10)!important}
  .demo-subtitle{color:var(--cyber-muted);margin-top:.25rem}

  .btn-loading{position:relative;pointer-events:none;opacity:.9}
  .btn-loading .btn-text{opacity:.0}
  .btn-loading .btn-spinner{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
  .btn-spinner .spinner-border{width:1.15rem;height:1.15rem;border-width:.2em}

  .products-premium-container{
    position:relative;
    border-radius:22px;
    padding:clamp(1.25rem,3.5vw,2.25rem);
    background:
      radial-gradient(1000px 520px at 20% 0%,rgba(0,212,255,0.14),transparent 60%),
      radial-gradient(900px 520px at 85% 15%,rgba(0,255,136,0.10),transparent 55%),
      linear-gradient(180deg,rgba(15,52,96,0.55),rgba(10,22,40,0.60));
    border:1px solid rgba(0,212,255,0.20);
    box-shadow:0 22px 80px rgba(0,0,0,0.55);
    backdrop-filter:blur(14px);
    overflow:hidden;
    transition:transform .25s ease,border-color .25s ease,box-shadow .25s ease;
  }
  .products-premium-container::before{
    content:"";
    position:absolute;
    inset:-2px;
    background:linear-gradient(120deg,rgba(0,212,255,0.22),rgba(0,255,136,0.14),rgba(255,107,53,0.10));
    opacity:.18;
    pointer-events:none;
    filter:blur(18px);
  }
  .products-premium-container > *{position:relative;z-index:1}
  .products-premium-container:hover{
    transform:translateY(-2px);
    border-color:rgba(0,212,255,0.42);
    box-shadow:0 26px 92px rgba(0,212,255,0.10),0 22px 80px rgba(0,0,0,0.55);
  }
  .products-premium-head{margin-bottom:clamp(1rem,2.5vw,1.6rem)}
  .products-premium-footer{
    margin-top:clamp(1.25rem,3vw,2rem);
    padding-top:clamp(1rem,2.5vw,1.35rem);
    border-top:1px solid rgba(0,212,255,0.14);
  }
</style>
</head>
<body>

<?php require __DIR__ . '/includes/public-navbar.php'; ?>

<header class="page-hero">
  <div class="container">
    <h1><span class="brand-mark"><?= brandMark() ?></span> <span class="accent">Products</span></h1>
    <p>Ready-to-deploy platforms from CYBEORCH Studio — wallets, LMS, POS, marketplaces, and security tools for startups and enterprises.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <div class="products-premium-container">
      <div class="text-center products-premium-head">
        <h2 class="section-title">Studio <span class="accent">Portfolio</span></h2>
        <div class="divider"></div>
      </div>
      <div class="row g-4">
      <?php foreach ($products as $prod): ?>
      <div class="col-md-6 col-lg-4">
        <div class="project-card">
          <div class="icon-wrap"><i class="fas fa-cube"></i></div>
          <span class="webinar-badge <?= projectStatusBadgeClass($prod['status']) ?>"><?= htmlspecialchars($prod['status']) ?></span>
          <?php if (!empty($prod['category'])): ?>
          <span class="webinar-badge badge-paid ms-1"><?= htmlspecialchars($prod['category']) ?></span>
          <?php endif; ?>
          <h3 class="webinar-title mt-2"><?= htmlspecialchars($prod['title']) ?></h3>
          <p class="webinar-desc"><?= htmlspecialchars($prod['description']) ?></p>
          <?php if (!empty($prod['features'])): ?>
          <ul class="feature-list">
            <?php foreach ($prod['features'] as $feature): ?>
            <li><?= htmlspecialchars($feature) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <button type="button"
                  class="btn-primary-cyber demo-open-btn"
                  data-bs-toggle="modal"
                  data-bs-target="#demoRequestModal"
                  data-product-name="<?= htmlspecialchars((string) $prod['title']) ?>"
                  style="width:auto;padding:.6rem 1.25rem">
            <i class="fas fa-paper-plane me-2"></i>Request Demo
          </button>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
      <div class="text-center products-premium-footer">
        <p style="color:var(--cyber-muted);max-width:560px;margin:0 auto 1.5rem">
          Need a custom build? We white-label, integrate APIs, and deploy on your infrastructure.
        </p>
        <a href="<?= url('services.php') ?>" class="btn-primary-cyber me-2" style="width:auto;display:inline-block;padding:.85rem 2rem">
          View Services
        </a>
        <a href="<?= url('contact.php') ?>" class="btn-primary-cyber" style="width:auto;display:inline-block;padding:.85rem 2rem;background:transparent;border:1px solid var(--cyber-accent);color:var(--cyber-accent)">
          Contact Sales
        </a>
      </div>
    </div>
  </div>
</section>

<!-- Toast container -->
<div class="demo-toast-wrap" id="demoToastWrap" aria-live="polite" aria-atomic="true"></div>

<!-- Request Demo Modal -->
<div class="modal fade demo-modal" id="demoRequestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="modal-title h5 mb-0">Request a <span class="accent">Product Demo</span></div>
          <div class="demo-subtitle">Schedule a personalized demo and explore product features in detail.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="demoRequestForm">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
          <input type="hidden" name="source_page" value="products">
          <input type="hidden" name="product_name" id="demoProductName" value="">
          <div style="display:none">
            <label>Website</label>
            <input type="text" name="website" value="">
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name *</label>
              <input class="form-control" name="full_name" required placeholder="Your full name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email Address *</label>
              <input class="form-control" type="email" name="email" required placeholder="you@example.com">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number *</label>
              <input class="form-control" name="phone" required placeholder="+91 XXXXX XXXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label">Company/Organization Name *</label>
              <input class="form-control" name="org_name" required placeholder="Company / College">
            </div>

            <div class="col-md-6">
              <label class="form-label">Product Name *</label>
              <input class="form-control" id="demoProductNameDisplay" readonly placeholder="Selected product">
              <div class="form-text">Auto-filled from the product card.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Interested Service/Technology *</label>
              <input class="form-control" name="interested_technology" required placeholder="e.g. Wallet, LMS, POS, APIs">
            </div>

            <div class="col-md-6">
              <label class="form-label">Preferred Demo Date</label>
              <input class="form-control" type="date" name="preferred_date">
            </div>
            <div class="col-md-6">
              <label class="form-label">Preferred Demo Time</label>
              <input class="form-control" type="time" name="preferred_time">
            </div>

            <div class="col-12">
              <label class="form-label">Message / Requirements *</label>
              <textarea class="form-control" name="message" rows="4" required placeholder="Tell us what you want to see in the demo (scope, users, integrations, timeline)."></textarea>
            </div>

            <div class="col-12 d-flex gap-2 justify-content-end">
              <button type="button" class="btn-outline-cyber" data-bs-dismiss="modal" style="padding:.7rem 1.1rem">
                Cancel
              </button>
              <button type="submit" class="btn-primary-cyber" id="demoSubmitBtn" style="width:auto;padding:.7rem 1.3rem">
                <span class="btn-text">Submit Demo Request</span>
                <span class="btn-spinner d-none"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php renderTrainingPublicFooter(); ?>
<?php renderPublicNavbarScript(); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const modalEl = document.getElementById('demoRequestModal');
  const form = document.getElementById('demoRequestForm');
  const productHidden = document.getElementById('demoProductName');
  const productDisplay = document.getElementById('demoProductNameDisplay');
  const toastWrap = document.getElementById('demoToastWrap');
  const submitBtn = document.getElementById('demoSubmitBtn');
  const spinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;

  function toast(type, title, msg){
    if(!toastWrap) return;
    const el = document.createElement('div');
    el.className = 'demo-toast ' + (type || '');
    el.innerHTML = '<div class="t-title">'+title+'</div><div class="t-msg">'+msg+'</div>';
    toastWrap.appendChild(el);
    requestAnimationFrame(()=> el.classList.add('is-show'));
    setTimeout(()=>{ el.classList.remove('is-show'); setTimeout(()=> el.remove(), 250); }, 3400);
  }

  function setLoading(on){
    if(!submitBtn) return;
    submitBtn.disabled = !!on;
    if(spinner) spinner.classList.toggle('d-none', !on);
    submitBtn.classList.toggle('btn-loading', !!on);
  }

  document.querySelectorAll('.demo-open-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const name = btn.getAttribute('data-product-name') || '';
      productHidden.value = name;
      productDisplay.value = name;
    });
  });

  if(!form) return;
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    // light client validation
    const required = ['full_name','email','phone','org_name','interested_technology','message'];
    for (const k of required){
      const v = String(fd.get(k) || '').trim();
      if(!v){ toast('error','Missing fields','Please fill all required fields.'); return; }
    }
    if(!String(fd.get('product_name')||'').trim()){
      toast('error','Select product','Please open the form from a product card.');
      return;
    }

    setLoading(true);
    try{
      const res = await fetch('<?= url('api/demo-request.php') ?>', { method:'POST', body: fd, credentials:'same-origin' });
      const data = await res.json().catch(()=>({ok:false,message:'Unexpected response.'}));
      if(res.ok && data.ok){
        toast('success','Submitted', data.message || 'Demo request sent.');
        form.reset();
        productHidden.value = '';
        productDisplay.value = '';
        const bsModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        setTimeout(()=> bsModal.hide(), 650);
      }else{
        toast('error','Failed', data.message || 'Could not submit request.');
      }
    }catch(err){
      toast('error','Network error','Please try again.');
    }finally{
      setLoading(false);
    }
  });
})();
</script>
</body>
</html>
