/**
 * Strict Gmail-only validation (@gmail.com). Shared by registration/enquiry forms.
 */
(function (global) {
  var GMAIL_MSG = 'Please enter a valid Gmail address';
  var GMAIL_PATTERN = /^[a-z0-9](?:[a-z0-9._+-]*[a-z0-9])?@gmail\.com$/;

  function isValidGmailAddress(email) {
    email = String(email || '').trim().toLowerCase();
    if (!email || email.length > 254) return false;
    if (!GMAIL_PATTERN.test(email)) return false;
    var local = email.split('@')[0];
    if (local.indexOf('..') !== -1) return false;
    return true;
  }

  /**
   * @param {HTMLInputElement} input
   * @param {HTMLElement} errorEl
   * @param {HTMLButtonElement|null} submitBtn
   */
  function bindGmailEmailField(input, errorEl, submitBtn) {
    if (!input || !errorEl) return;

    function showError(show) {
      errorEl.hidden = !show;
      input.setAttribute('aria-invalid', show ? 'true' : 'false');
      input.classList.toggle('is-invalid', show);
    }

    function syncSubmit(enabled) {
      if (!submitBtn) return;
      submitBtn.disabled = !enabled;
    }

    function validate() {
      var value = input.value.trim();
      if (!value) {
        showError(false);
        syncSubmit(false);
        return false;
      }
      var ok = isValidGmailAddress(value);
      showError(!ok);
      syncSubmit(ok);
      return ok;
    }

    input.addEventListener('input', validate);
    input.addEventListener('blur', validate);
    validate();

    return { validate: validate, isValidGmailAddress: isValidGmailAddress };
  }

  global.CybeorchGmailValidation = {
    message: GMAIL_MSG,
    pattern: GMAIL_PATTERN,
    isValidGmailAddress: isValidGmailAddress,
    bindGmailEmailField: bindGmailEmailField
  };
})(typeof window !== 'undefined' ? window : this);
