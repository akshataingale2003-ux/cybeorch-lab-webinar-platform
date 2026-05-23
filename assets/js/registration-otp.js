/**
 * CYBEORCH — Registration with Email OTP (popup AJAX or register page POST)
 */
(function () {
  'use strict';

  var form = document.getElementById('registerForm') || document.getElementById('cybeorchRegForm');
  if (!form) return;

  var overlay = document.getElementById('cybeorchRegOverlay');
  var authRegModal = document.getElementById('authRegModal');
  var useQuickRedirect = document.body.getAttribute('data-registration-inline') === '1'
    || (document.body.classList.contains('auth-page') && !authRegModal)
    || !!authRegModal;
  var alertBox = document.getElementById('cybeorchRegAjaxAlert');
  var csrf = (form.querySelector('[name="csrf_token"]') || {}).value || '';

  var sendUrl = form.getAttribute('data-send-url') || 'send_otp.php';
  var verifyUrl = form.getAttribute('data-verify-url') || 'verify_otp.php';
  var registerUrl = form.getAttribute('data-register-url') || '';
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

  var otpVerified = false;
  var expiresAt = 0;
  var timerId = null;

  function setAlert(type, message) {
    if (!alertBox) return;
    alertBox.hidden = false;
    if (document.body.classList.contains('auth-page')) {
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

  function setAfterOtpEnabled(on) {
    if (afterOtpWrap) {
      afterOtpWrap.classList.toggle('is-ready', on);
    }
    [passwordEl, confirmEl, btnRegister].forEach(function (el) {
      if (!el) return;
      el.disabled = !on;
      if (el === passwordEl || el === confirmEl) {
        if (on) el.setAttribute('required', 'required');
        else el.removeAttribute('required');
      }
    });
    var terms = document.getElementById('terms');
    var referral = document.getElementById('referral_code');
    if (terms) terms.disabled = !on;
    if (referral) referral.disabled = !on;
  }

  function updateRegisterState() {
    setAfterOtpEnabled(otpVerified);
    if (!btnRegister) return;
    btnRegister.disabled = !otpVerified;
    btnRegister.setAttribute('aria-disabled', otpVerified ? 'false' : 'true');
  }

  function resetVerification() {
    otpVerified = false;
    updateRegisterState();
    if (btnVerify) btnVerify.disabled = false;
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
    if (name.length < 3) {
      setAlert('error', 'Please enter your full name (at least 3 characters).');
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
        if (!data.success) {
          setAlert('error', data.message || 'Could not send OTP.');
          return;
        }
        setAlert('success', data.message || 'OTP sent to your email.');
        if (data.expires_at) startTimer(data.expires_at);
        if (timerEl && !data.expires_at) {
          timerEl.textContent = 'Check your email inbox and Spam/Junk folder.';
        }
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

    var payload = Object.assign(formFields(), {
      password: (passwordEl && passwordEl.value) || '',
      confirm_password: (confirmEl && confirmEl.value) || '',
      referral_code: ((form.querySelector('[name="referral_code"]') || {}).value || ''),
      agree_terms: '1'
    });

    postJson(registerUrl, payload)
      .then(function (data) {
        if (!data.success) {
          setAlert('error', data.message || 'Registration failed.');
          return;
        }
        setAlert('success', data.message || 'Registration successful.');
        if (useQuickRedirect) {
          window.location.href = data.redirect || successUrl;
          return;
        }
        if (overlay) overlay.classList.add('cybeorch-reg-closing');
        document.body.classList.remove('cybeorch-registration-locked');
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

  updateRegisterState();
})();
