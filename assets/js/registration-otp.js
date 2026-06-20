/**
 * CYBEORCH — Registration with Email OTP (re-initializable for dynamic popup).
 */
window.initCybeorchRegistrationOtp = function initCybeorchRegistrationOtp() {
  'use strict';

  var form = document.getElementById('registerForm') || document.getElementById('cybeorchRegForm');
  if (!form) return;
  if (form.dataset.otpBound === '1') return;
  form.dataset.otpBound = '1';

  var sendUrl = form.getAttribute('data-send-url');
  var verifyUrl = form.getAttribute('data-verify-url');
  var registerUrl = form.getAttribute('data-register-url');
  if (!sendUrl || !verifyUrl || !registerUrl) {
    console.error('Registration form missing API URLs.');
    return;
  }

  var overlay = document.getElementById('authRegModal') || document.getElementById('cybeorchRegOverlay');
  var authRegModal = document.getElementById('authRegModal');
  var useQuickRedirect = document.body.getAttribute('data-registration-inline') === '1'
    || (document.body.classList.contains('auth-page') && !authRegModal)
    || !!authRegModal;
  var alertBox = document.getElementById('cybeorchRegAjaxAlert');
  var csrf = (form.querySelector('[name="csrf_token"]') || {}).value || '';

  var successUrl = form.getAttribute('data-success-url') || 'index.php?registered=1';
  var submitMode = form.getAttribute('data-submit-mode') || (registerUrl ? 'ajax' : 'post');

  var nameEl = document.getElementById('regFullName');
  var emailEl = document.getElementById('regEmail');
  var mobileEl = document.getElementById('regMobile');
  var otpEl = document.getElementById('regOtp');
  var passwordEl = document.getElementById('regPassword') || document.getElementById('password');
  var confirmEl = document.getElementById('regConfirmPassword') || document.getElementById('confirm_pass');
  var afterOtpWrap = document.getElementById('registerAfterOtp');
  var btnSend = document.getElementById('cybeorchBtnSendOtp');
  var btnVerify = document.getElementById('cybeorchBtnVerifyOtp');
  var btnResend = document.getElementById('cybeorchBtnResendOtp');
  var btnRegister = document.getElementById('cybeorchRegSubmit');
  var timerEl = document.getElementById('cybeorchOtpTimer');
  var referralEl = document.getElementById('regReferralCode');
  var referralHint = document.getElementById('regReferralHint');
  var validateReferralUrl = (window.CYBEORCH_REG_POPUP && window.CYBEORCH_REG_POPUP.validateReferralUrl) || 'api/validate-referral.php';

  var otpVerified = false;
  var expiresAt = 0;
  var timerId = null;

  function setRegStep(step) {
    if (window.CybeorchAuthReg && typeof window.CybeorchAuthReg.setStep === 'function') {
      window.CybeorchAuthReg.setStep(step);
    }
  }

  function setAlert(type, message) {
    if (!alertBox) return;
    alertBox.hidden = false;
    if (document.body.classList.contains('auth-page') || alertBox.closest('.auth-reg-modal-overlay')) {
      alertBox.className = 'alert alert-' + type;
    } else {
      alertBox.className = 'cybeorch-reg-alert cybeorch-reg-alert-' + type;
    }
    alertBox.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle') + '"></i><span></span>';
    var span = alertBox.querySelector('span');
    if (span) span.textContent = message;
  }

  function clearAlert() {
    if (alertBox) alertBox.hidden = true;
  }

  function isSignupRegistrationComplete() {
    return form.getAttribute('data-registration-complete') === '1';
  }

  function bindSignupLegalLinks() {
    var footer = document.getElementById('authSignupLegalFooter')
      || form.closest('.auth-split-main') && form.closest('.auth-split-main').querySelector('.auth-card-footer');
    if (!footer || footer.dataset.legalBound === '1') return;
    footer.dataset.legalBound = '1';

    function blockSignupLegalAccess(e) {
      var btn = e.target.closest('[data-auth-legal-signup-locked]');
      if (!btn) return;
      e.preventDefault();
      e.stopPropagation();
      if (isSignupRegistrationComplete()) return;
      setAlert('info', 'Please complete registration before viewing the Terms & Conditions or Privacy Policy.');
    }

    footer.addEventListener('click', blockSignupLegalAccess);
    footer.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      blockSignupLegalAccess(e);
    });
  }

  function contactValue() {
    return (mobileEl && mobileEl.value) || '';
  }

  function formFields() {
    return {
      csrf_token: csrf,
      company_url: '',
      full_name: (nameEl && nameEl.value) || '',
      email: (emailEl && emailEl.value) || '',
      mobile: contactValue()
    };
  }

  function setLoading(btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-loading', on);
    btn.disabled = on;
  }

  function postJson(url, data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) {
      if (data[k] !== undefined && data[k] !== null) body.append(k, data[k]);
    });
    return fetch(url, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.text().then(function (text) {
        var json;
        try {
          json = JSON.parse(text);
        } catch (e) {
          throw new Error('Invalid server response. Check SMTP/PHP errors.');
        }
        if (!res.ok && json && json.message) {
          throw new Error(json.message);
        }
        return json;
      });
    });
  }

  function normalizeReferralInput(el) {
    if (!el) return '';
    var v = (el.value || '').toUpperCase().replace(/\s+/g, '').replace(/[^A-Z0-9]/g, '');
    el.value = v.slice(0, 20);
    return el.value;
  }

  function setReferralHint(state, message) {
    if (!referralHint) return;
    referralHint.classList.remove('is-valid', 'is-invalid');
    if (state) referralHint.classList.add(state);
    if (message) referralHint.textContent = message;
  }

  function validateReferralCodeAsync() {
    if (!referralEl) return Promise.resolve(true);
    var code = normalizeReferralInput(referralEl);
    if (!code) {
      referralEl.classList.remove('is-valid', 'is-invalid');
      setReferralHint('', "Enter a friend's code — they earn NxL when you join.");
      return Promise.resolve(true);
    }
    return postJson(validateReferralUrl, {
      csrf_token: csrf,
      referral_code: code,
      email: (emailEl && emailEl.value.trim()) || ''
    }).then(function (data) {
      if (data.valid) {
        referralEl.classList.add('is-valid');
        referralEl.classList.remove('is-invalid');
        setReferralHint('is-valid', data.message || 'Referral code looks good.');
        return true;
      }
      referralEl.classList.add('is-invalid');
      referralEl.classList.remove('is-valid');
      setReferralHint('is-invalid', data.message || 'Invalid referral code.');
      return false;
    }).catch(function () {
      return true;
    });
  }

  function bindReferralField() {
    if (!referralEl || referralEl.dataset.bound === '1') return;
    referralEl.dataset.bound = '1';
    referralEl.addEventListener('input', function () {
      normalizeReferralInput(referralEl);
      referralEl.classList.remove('is-valid', 'is-invalid');
      setReferralHint('', '');
    });
    referralEl.addEventListener('blur', function () {
      validateReferralCodeAsync();
    });
  }

  function setRegisterSubmitEnabled(on) {
    if (!btnRegister) return;
    btnRegister.disabled = !on;
    if (on) {
      btnRegister.removeAttribute('aria-disabled');
    } else {
      btnRegister.setAttribute('aria-disabled', 'true');
    }
  }

  function setAfterOtpEnabled(on) {
    if (afterOtpWrap) {
      afterOtpWrap.classList.toggle('is-ready', on);
    }
    [passwordEl, confirmEl].forEach(function (el) {
      if (!el) return;
      el.disabled = !on;
      if (on) {
        el.setAttribute('required', 'required');
      } else {
        el.removeAttribute('required');
      }
    });
    setRegisterSubmitEnabled(on);
    var terms = document.getElementById('terms');
    if (terms) terms.disabled = !on;
    if (on) setRegStep('password');
  }

  function updateRegisterState() {
    setAfterOtpEnabled(otpVerified);
  }

  function resetVerification() {
    otpVerified = false;
    updateRegisterState();
    if (btnVerify) btnVerify.disabled = false;
    setRegStep('profile');
  }

  function startTimer(ts) {
    expiresAt = ts || 0;
    if (timerId) clearInterval(timerId);
    if (!timerEl) return;
    function tick() {
      var left = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
      if (left <= 0) {
        timerEl.textContent = 'OTP expired — resend to get a new code.';
        clearInterval(timerId);
        return;
      }
      var m = Math.floor(left / 60);
      var s = left % 60;
      timerEl.textContent = 'Expires in ' + m + ':' + (s < 10 ? '0' : '') + s;
    }
    tick();
    timerId = setInterval(tick, 1000);
  }

  function passwordsValid() {
    var pass = (passwordEl && passwordEl.value) || '';
    var confirm = (confirmEl && confirmEl.value) || '';
    if (pass.length < 8) {
      setAlert('error', 'Password must be at least 8 characters.');
      return false;
    }
    if (!/[A-Z]/.test(pass) || !/[0-9]/.test(pass)) {
      setAlert('error', 'Password needs at least one uppercase letter and one number.');
      return false;
    }
    if (pass !== confirm) {
      setAlert('error', 'Passwords do not match.');
      return false;
    }
    return true;
  }

  function onSendOtp(resend) {
    clearAlert();
    var name = (nameEl && nameEl.value.trim()) || '';
    var email = (emailEl && emailEl.value.trim()) || '';
    var mobile = contactValue().trim();
    if (name.length < 2) {
      setAlert('error', 'Please enter your full name.');
      return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setAlert('error', 'Please enter a valid email address.');
      return;
    }
    if (!mobile) {
      setAlert('error', 'Please enter your contact number.');
      return;
    }
    resetVerification();
    setLoading(btnSend, true);
    if (btnResend) btnResend.disabled = true;

    var payload = formFields();
    if (resend) payload.resend = '1';

    postJson(sendUrl, payload)
      .then(function (data) {
        if (!data.success || !data.email_sent) {
          setAlert('error', data.message || 'Failed to send OTP email. Please try again.');
          return;
        }
        setAlert('success', data.message || 'OTP Sent Successfully');
        setRegStep('otp');
        if (data.expires_at) startTimer(data.expires_at);
        if (timerEl) timerEl.textContent = 'Check your inbox and Spam/Junk folder.';
        if (otpEl) {
          otpEl.focus();
          otpEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        if (btnResend) {
          setTimeout(function () { btnResend.disabled = false; }, 30000);
        }
      })
      .catch(function (err) {
        setAlert('error', err.message || 'Network error.');
      })
      .finally(function () {
        setLoading(btnSend, false);
      });
  }

  function onVerifyOtp() {
    clearAlert();
    var otp = (otpEl && otpEl.value) || '';
    if (!/^\d{6}$/.test(otp.replace(/\D/g, ''))) {
      setAlert('error', 'Enter the 6-digit OTP.');
      return;
    }
    setLoading(btnVerify, true);

    postJson(verifyUrl, Object.assign(formFields(), { otp: otp }))
      .then(function (data) {
        if (!data.success) {
          setAlert('error', data.message || 'Invalid OTP');
          otpVerified = false;
          updateRegisterState();
          return;
        }
        otpVerified = true;
        updateRegisterState();
        setAlert('success', data.message || 'Email verified successfully.');
        if (btnVerify) btnVerify.disabled = true;
        if (passwordEl) passwordEl.focus();
      })
      .catch(function (err) {
        setAlert('error', err.message || 'Verification failed.');
      })
      .finally(function () {
        setLoading(btnVerify, false);
        if (otpVerified) setRegisterSubmitEnabled(true);
      });
  }

  function onRegisterAjax(e) {
    e.preventDefault();
    if (!otpVerified) {
      setAlert('error', 'Please verify OTP before registering.');
      return;
    }
    if (!passwordsValid()) return;

    clearAlert();
    setLoading(btnRegister, true);

    validateReferralCodeAsync().then(function (refOk) {
      if (!refOk) {
        setLoading(btnRegister, false);
        setAlert('error', 'Please fix the referral code or leave it blank.');
        return;
      }

      var payload = Object.assign(formFields(), {
        password: (passwordEl && passwordEl.value) || '',
        confirm_password: (confirmEl && confirmEl.value) || '',
        referral_code: normalizeReferralInput(referralEl),
        agree_terms: '1'
      });

      postJson(registerUrl, payload)
        .then(function (data) {
          if (!data.success) {
            setAlert('error', data.message || 'Registration failed.');
            return;
          }
          form.setAttribute('data-registration-complete', '1');
          setAlert('success', data.message || 'Registration successful.');
          if (useQuickRedirect) {
            window.location.href = data.redirect || successUrl;
            return;
          }
          if (overlay) overlay.classList.add('auth-reg-modal-closing');
          document.body.classList.remove('cybeorch-registration-locked', 'auth-reg-locked');
          setTimeout(function () {
            if (overlay) overlay.remove();
            window.location.href = data.redirect || successUrl;
          }, 1200);
        })
        .catch(function (err) {
          setAlert('error', err.message || 'Registration failed.');
        })
        .finally(function () {
          setLoading(btnRegister, false);
        });
    });
  }

  function onRegisterPost(e) {
    if (!otpVerified) {
      e.preventDefault();
      setAlert('error', 'Please verify your email with OTP before signing up.');
      return;
    }
    if (!passwordsValid()) {
      e.preventDefault();
      return;
    }
    var terms = document.getElementById('terms');
    if (terms && !terms.checked) {
      e.preventDefault();
      setAlert('error', 'Please agree to the Terms of Service and Privacy Policy.');
    }
  }

  if (btnSend) btnSend.addEventListener('click', function () { onSendOtp(false); });
  if (btnResend) btnResend.addEventListener('click', function () { onSendOtp(true); });
  if (btnVerify) btnVerify.addEventListener('click', onVerifyOtp);

  form.addEventListener('submit', submitMode === 'post' ? onRegisterPost : onRegisterAjax);

  [nameEl, emailEl, mobileEl].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', function () {
      if (otpVerified) resetVerification();
    });
  });
  if (emailEl) {
    emailEl.addEventListener('input', function () {
      if (timerId) clearInterval(timerId);
      if (timerEl) timerEl.textContent = '';
    });
  }

  bindSignupLegalLinks();
  bindReferralField();

  setRegStep('profile');
  updateRegisterState();
};
