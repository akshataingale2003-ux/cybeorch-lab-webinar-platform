(function () {
  'use strict';

  var form = document.querySelector('.webinar-register-form');
  if (!form) return;

  var alertBox = form.querySelector('.wr-form-alert');
  var submitBtn = form.querySelector('.wr-submit');

  function showAlert(message) {
    if (!alertBox) return;
    alertBox.hidden = false;
    alertBox.textContent = message;
  }

  function clearFieldErrors() {
    form.querySelectorAll('.wr-field.has-error').forEach(function (field) {
      field.classList.remove('has-error');
      var err = field.querySelector('.wr-field-error');
      if (err) err.textContent = '';
    });
  }

  function setFieldError(fieldName, message) {
    var field = form.querySelector('.wr-field[data-field="' + fieldName + '"]');
    if (!field) return;
    field.classList.add('has-error');
    var input = field.querySelector('.form-control');
    if (input) input.classList.add('is-invalid');
    var err = field.querySelector('.wr-field-error');
    if (err) err.textContent = message;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    clearFieldErrors();
    if (alertBox) alertBox.hidden = true;
    if (submitBtn) submitBtn.disabled = true;

    var body = new FormData(form);
    body.append('csrf_token', form.getAttribute('data-csrf') || '');

    fetch(form.getAttribute('data-api-url') || '', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = JSON.parse(text);
        if (!res.ok && data.message) throw data;
        return data;
      });
    }).then(function (data) {
      if (!data.success) {
        if (data.field) setFieldError(data.field, data.message || 'Invalid value.');
        throw new Error(data.message || 'Could not save your details.');
      }
      if (data.redirect) {
        window.location.href = data.redirect;
        return;
      }
      window.location.reload();
    }).catch(function (err) {
      if (err && err.field) {
        setFieldError(err.field, err.message || 'Invalid value.');
      } else {
        showAlert((err && err.message) || (err instanceof Error ? err.message : 'Something went wrong.'));
      }
      if (submitBtn) submitBtn.disabled = false;
    });
  });
})();
