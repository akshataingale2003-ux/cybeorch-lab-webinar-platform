/**
 * CYBEORCH — Dynamic registration popup (load via API, open from any page).
 */
(function () {
  'use strict';

  var boot = window.CYBEORCH_REG_POPUP || {};
  if (boot.enabled === false) {
    return;
  }

  var loading = false;

  function lockPageScroll() {
    var scrollY = window.scrollY || window.pageYOffset || 0;
    document.documentElement.classList.add('auth-modal-open');
    document.body.classList.add('auth-modal-open');
    document.body.style.top = '-' + scrollY + 'px';
    document.body.dataset.authRegScrollY = String(scrollY);
  }

  function unlockPageScroll() {
    var scrollY = parseInt(document.body.dataset.authRegScrollY || '0', 10) || 0;
    document.documentElement.classList.remove('auth-modal-open');
    document.body.classList.remove('auth-modal-open');
    document.body.style.top = '';
    delete document.body.dataset.authRegScrollY;
    window.scrollTo(0, scrollY);
  }

  function getModal() {
    return document.getElementById('authRegModal');
  }

  function fitAuthRegToViewport() {
    var overlay = getModal() || document.querySelector('.auth-reg-modal-overlay--mandatory');
    if (!overlay || !overlay.classList.contains('auth-reg-fit-window')) return;
    var host = overlay.querySelector('.auth-reg-card-host');
    var card = overlay.querySelector('.auth-reg-scale-target');
    if (!host || !card) return;
    card.style.transform = '';
    card.style.zoom = '';
    requestAnimationFrame(function () {
      var hostH = host.clientHeight;
      var cardH = card.offsetHeight;
      if (hostH < 16 || cardH < 16) return;
      var scale = Math.min(1, (hostH - 4) / cardH);
      if (scale >= 0.995) return;
      if (typeof card.style.zoom !== 'undefined') {
        card.style.zoom = String(scale);
      } else {
        card.style.transform = 'scale(' + scale + ')';
        card.style.transformOrigin = 'center center';
      }
    });
  }

  window.fitAuthRegToViewport = fitAuthRegToViewport;

  function bindCloseHandlers(modal) {
    if (!modal || modal.dataset.closeBound === '1' || modal.classList.contains('auth-reg-modal-overlay--mandatory')) {
      return;
    }
    modal.dataset.closeBound = '1';
    var closeBtn = document.getElementById('authRegModalClose');
    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        window.CybeorchAuthReg.close();
      });
    }
    modal.addEventListener('click', function (e) {
      if (e.target === modal) window.CybeorchAuthReg.close();
    });
  }

  function bindRegistrationForm() {
    if (typeof window.initCybeorchRegistrationOtp === 'function') {
      window.initCybeorchRegistrationOtp();
      return Promise.resolve();
    }
    return new Promise(function (resolve, reject) {
      var src = boot.otpScriptUrl || 'assets/js/registration-otp.js';
      var existing = document.querySelector('script[src*="registration-otp.js"]');
      if (existing) {
        var n = 0;
        (function wait() {
          if (window.initCybeorchRegistrationOtp) {
            window.initCybeorchRegistrationOtp();
            resolve();
            return;
          }
          if (++n > 80) {
            reject(new Error('Registration scripts failed to load.'));
            return;
          }
          setTimeout(wait, 50);
        })();
        return;
      }
      var s = document.createElement('script');
      s.src = src;
      s.onload = function () {
        if (window.initCybeorchRegistrationOtp) {
          window.initCybeorchRegistrationOtp();
          resolve();
        } else {
          reject(new Error('OTP handler missing.'));
        }
      };
      s.onerror = function () { reject(new Error('Could not load registration-otp.js')); };
      document.body.appendChild(s);
    });
  }

  function injectPopup(html) {
    var mount = document.getElementById('authRegModalMount');
    var existing = getModal();
    if (existing) existing.remove();
    if (mount) {
      mount.innerHTML = html;
    } else {
      var wrap = document.createElement('div');
      wrap.id = 'authRegModalMount';
      wrap.innerHTML = html;
      document.body.appendChild(wrap);
    }
    bindCloseHandlers(getModal());
    return bindRegistrationForm().then(function () {
      fitAuthRegToViewport();
      return getModal();
    });
  }

  function buildApiUrl(opts) {
    var base = boot.popupApiUrl || boot.apiUrl || 'api/registration-popup.php';
    var url = new URL(base, window.location.href);
    if (opts.ref) url.searchParams.set('ref', opts.ref);
    if (opts.redirect) url.searchParams.set('redirect', opts.redirect);
    if (opts.tagline) url.searchParams.set('tagline', opts.tagline);
    if (opts.registerRequired) url.searchParams.set('register_required', '1');
    return url.toString();
  }

  function loadPopup(opts) {
    if (getModal()) {
      return bindRegistrationForm().then(function () {
        fitAuthRegToViewport();
        return getModal();
      });
    }
    if (loading) return Promise.resolve(null);
    loading = true;
    return fetch(buildApiUrl(opts || {}), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success || !data.html) {
          throw new Error(data.message || 'Could not load registration form.');
        }
        if (data.config) {
          window.CYBEORCH_REG_POPUP = Object.assign({}, boot, data.config);
          boot = window.CYBEORCH_REG_POPUP;
        }
        return injectPopup(data.html);
      })
      .finally(function () {
        loading = false;
      });
  }

  function setRegStep(step) {
    var form = document.getElementById('cybeorchRegForm');
    if (!form) return;
    var grid = form.querySelector('.auth-reg-form-grid');
    if (grid) grid.setAttribute('data-reg-current-step', step);
    var order = { profile: 0, otp: 1, password: 2 };
    form.querySelectorAll('.auth-reg-step').forEach(function (el) {
      var s = el.getAttribute('data-step');
      var idx = order[s] !== undefined ? order[s] : -1;
      var cur = order[step] !== undefined ? order[step] : 0;
      el.classList.toggle('is-active', s === step);
      el.classList.toggle('is-done', idx >= 0 && idx < cur);
    });
    var subtitles = {
      profile: 'Enter your details, then send an OTP to your email.',
      otp: 'Enter the 6-digit code sent to your email.',
      password: 'Choose a password and complete registration.'
    };
    var sub = form.querySelector('[data-reg-dynamic-subtitle]');
    if (sub && subtitles[step]) sub.textContent = subtitles[step];
  }

  window.CybeorchAuthReg = {
    open: function (opts) {
      opts = opts || {};
      var openNow = function () {
        var modal = getModal();
        if (!modal) return;
        if (!modal.classList.contains('auth-reg-modal-overlay--mandatory')) {
          modal.hidden = false;
          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          lockPageScroll();
        }
        setRegStep('profile');
        fitAuthRegToViewport();
        var first = document.getElementById('regFullName');
        if (first && opts.focus !== false) setTimeout(function () { first.focus(); }, 80);
      };
      if (getModal()) {
        return bindRegistrationForm().then(function () {
          openNow();
          return getModal();
        });
      }
      return loadPopup(opts).then(function () {
        openNow();
        return getModal();
      }).catch(function () {
        window.location.href = (boot.loginUrl || 'login.php') + '?signup=1';
      });
    },
    close: function () {
      var modal = getModal();
      if (!modal || modal.classList.contains('auth-reg-modal-overlay--mandatory')) return;
      modal.hidden = true;
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      unlockPageScroll();
    },
    setStep: setRegStep
  };

  window.openAuthRegModal = function () { window.CybeorchAuthReg.open(); };

  function parseOptsFromLink(el) {
    var opts = {};
    try {
      var href = el.getAttribute('href') || '';
      if (href) {
        var u = new URL(href, window.location.href);
        if (u.searchParams.get('ref')) opts.ref = u.searchParams.get('ref');
        if (u.searchParams.get('redirect')) opts.redirect = u.searchParams.get('redirect');
        if (u.searchParams.has('register_required')) opts.registerRequired = true;
      }
    } catch (e) { /* ignore */ }
    return opts;
  }

  function shouldIntercept(el) {
    if (!el || el.hasAttribute('data-auth-reg-no-popup')) return false;
    if (el.hasAttribute('data-auth-reg-open') || el.hasAttribute('data-open-reg-popup')) return true;
    var href = (el.getAttribute('href') || '').toLowerCase();
    return href.indexOf('signup.php') !== -1
      || href.indexOf('signup=1') !== -1
      || href.indexOf('register_required=1') !== -1;
  }

  document.addEventListener('click', function (e) {
    var el = e.target.closest('a,button');
    if (!el || !shouldIntercept(el)) return;
    if (getModal() && getModal().classList.contains('auth-reg-modal-overlay--mandatory')) return;
    e.preventDefault();
    window.CybeorchAuthReg.open(parseOptsFromLink(el));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var modal = getModal();
    if (modal && !modal.hidden && !modal.classList.contains('auth-reg-modal-overlay--mandatory')) {
      window.CybeorchAuthReg.close();
    }
  });

  var openBtn = document.getElementById('openRegModal');
  if (openBtn) {
    openBtn.addEventListener('click', function (e) {
      e.preventDefault();
      window.CybeorchAuthReg.open();
    });
  }

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(fitAuthRegToViewport, 80);
  });

  if (getModal()) {
    bindCloseHandlers(getModal());
    bindRegistrationForm().then(function () {
      setRegStep('profile');
      fitAuthRegToViewport();
    });
  }
})();
