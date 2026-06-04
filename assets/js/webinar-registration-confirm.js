(function () {
  'use strict';
  var btn = document.getElementById('wrcConfirmBtn');
  var alertBox = document.getElementById('wrcAlert');
  if (!btn) return;
  btn.addEventListener('click', function () {
    if (btn.getAttribute('data-disabled') === '1') return;
    btn.classList.add('is-loading');
    btn.disabled = true;
    if (alertBox) alertBox.hidden = true;
    var body = new FormData();
    body.append('csrf_token', btn.getAttribute('data-csrf') || '');
    body.append('webinar_id', btn.getAttribute('data-webinar-id') || '');
    body.append('token', btn.getAttribute('data-token') || '');
    fetch(btn.getAttribute('data-api-url') || '', {
      method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = JSON.parse(text);
        if (!res.ok && data.message) throw new Error(data.message);
        return data;
      });
    }).then(function (data) {
      if (!data.success) throw new Error(data.message || 'Registration failed.');
      if (alertBox) {
        alertBox.hidden = false;
        alertBox.className = 'wrc-alert wrc-alert-success';
        alertBox.innerHTML = '<i class="fas fa-check-circle"></i><span></span>';
        alertBox.querySelector('span').textContent = data.message;
      }
      if (data.redirect) setTimeout(function () { window.location.href = data.redirect; }, 1500);
    }).catch(function (err) {
      if (alertBox) {
        alertBox.hidden = false;
        alertBox.className = 'wrc-alert wrc-alert-error';
        alertBox.innerHTML = '<i class="fas fa-exclamation-circle"></i><span></span>';
        alertBox.querySelector('span').textContent = err.message;
      }
      btn.classList.remove('is-loading');
      btn.disabled = btn.getAttribute('data-disabled') === '1';
    });
  });
})();
