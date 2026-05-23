(function () {
  'use strict';

  var cfg = window.CYBEORCH_ADMIN_ACTIONS;
  if (!cfg || !cfg.url) {
    return;
  }

  function toast(message, type) {
    var host = document.getElementById('adminToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'adminToastHost';
      host.className = 'admin-toast-host';
      document.body.appendChild(host);
    }
    var el = document.createElement('div');
    el.className = 'admin-toast admin-toast-' + (type === 'error' ? 'error' : 'success');
    el.setAttribute('role', 'alert');
    el.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i><span></span>';
    el.querySelector('span').textContent = message;
    host.appendChild(el);
    requestAnimationFrame(function () {
      el.classList.add('show');
    });
    setTimeout(function () {
      el.classList.remove('show');
      setTimeout(function () {
        el.remove();
      }, 300);
    }, 4200);
  }

  function findRow(btn) {
    return btn.closest('[data-admin-row]') || btn.closest('tr') || btn.closest('.section-card-body') || btn.closest('.form-card-body');
  }

  function setLoading(btn, on) {
    if (!btn) {
      return;
    }
    btn.disabled = on;
    if (on) {
      btn.dataset.adminPrevHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    } else if (btn.dataset.adminPrevHtml) {
      btn.innerHTML = btn.dataset.adminPrevHtml;
      delete btn.dataset.adminPrevHtml;
    }
  }

  function updateBlockUi(row, entity, id, blocked) {
    if (!row) {
      return;
    }
    row.classList.toggle('admin-row-blocked', !!blocked);
    row.querySelectorAll('[data-admin-action="block"], [data-admin-action="unblock"]').forEach(function (b) {
      b.remove();
    });
    var wrap = row.querySelector('.admin-action-btns');
    if (!wrap) {
      return;
    }
    var blockBtn = document.createElement('button');
    blockBtn.type = 'button';
    blockBtn.className = 'btn-sm-cyber ' + (blocked ? 'btn-unblock' : 'btn-block');
    blockBtn.setAttribute('data-admin-action', blocked ? 'unblock' : 'block');
    blockBtn.setAttribute('data-admin-entity', entity);
    blockBtn.setAttribute('data-admin-id', String(id));
    if (!blocked) {
      blockBtn.setAttribute('data-admin-confirm', 'Block this record?');
    }
    blockBtn.innerHTML = blocked
      ? '<i class="fas fa-unlock"></i><span>Unblock</span>'
      : '<i class="fas fa-ban"></i><span>Block</span>';
    var deleteBtn = wrap.querySelector('[data-admin-action="delete"], [data-admin-action="purge"]');
    wrap.insertBefore(blockBtn, deleteBtn || null);
  }

  function postAction(action, entity, id, btn) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('entity', entity);
    fd.append('id', String(id));
    fd.append('csrf_token', cfg.csrf);

    setLoading(btn, true);
    return fetch(cfg.url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (r) {
        return r.json().then(function (data) {
          return { ok: r.ok, data: data };
        });
      })
      .finally(function () {
        setLoading(btn, false);
      });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-admin-action]');
    if (!btn || btn.disabled) {
      return;
    }

    var action = btn.getAttribute('data-admin-action');
    var entity = btn.getAttribute('data-admin-entity');
    var id = parseInt(btn.getAttribute('data-admin-id'), 10);
    if (!action || !entity || !id) {
      return;
    }

    var confirmMsg = btn.getAttribute('data-admin-confirm');
    if (confirmMsg && !window.confirm(confirmMsg)) {
      return;
    }

    e.preventDefault();
    var row = findRow(btn);

    postAction(action, entity, id, btn).then(function (res) {
      var data = res.data || {};
      if (!res.ok || !data.success) {
        toast(data.message || 'Action failed.', 'error');
        return;
      }
      toast(data.message || 'Done.', 'success');

      if (data.removed && row) {
        row.style.transition = 'opacity 0.25s ease';
        row.style.opacity = '0';
        setTimeout(function () {
          row.remove();
          var tbody = document.querySelector('.data-table tbody');
          if (tbody && !tbody.querySelector('tr')) {
            location.reload();
          }
        }, 280);
        return;
      }

      if (typeof data.blocked === 'boolean') {
        updateBlockUi(row, entity, id, data.blocked);
      }

      if (data.restored && row) {
        row.remove();
      }
    }).catch(function () {
      toast('Network error. Please try again.', 'error');
    });
  });
})();
