(function () {
  'use strict';

  var table = document.getElementById('adminTrashTable');
  if (!table) return;

  var cfg = window.CYBEORCH_ADMIN_ACTIONS || {};
  var selectAllCb = document.getElementById('adminTrashSelectAllCb');
  var selectAllBtn = document.getElementById('adminTrashSelectAllBtn');
  var deselectAllBtn = document.getElementById('adminTrashDeselectAllBtn');
  var countEl = document.getElementById('adminTrashSelectedCount');
  var restoreBtn = document.getElementById('adminTrashBulkRestoreBtn');
  var purgeBtn = document.getElementById('adminTrashBulkPurgeBtn');

  function rowCheckboxes() {
    return Array.prototype.slice.call(table.querySelectorAll('.admin-trash-row-cb'));
  }

  function selectedItems() {
    return rowCheckboxes()
      .filter(function (cb) { return cb.checked; })
      .map(function (cb) {
        return {
          entity: cb.getAttribute('data-admin-entity') || '',
          id: parseInt(cb.getAttribute('data-admin-id'), 10) || 0,
        };
      })
      .filter(function (item) { return item.entity && item.id > 0; });
  }

  function updateUi() {
    var boxes = rowCheckboxes();
    var selected = selectedItems();
    var count = selected.length;
    var total = boxes.length;

    if (countEl) {
      countEl.innerHTML = '<strong>' + count + '</strong> selected';
    }
    if (restoreBtn) restoreBtn.disabled = count === 0;
    if (purgeBtn) purgeBtn.disabled = count === 0;
    if (deselectAllBtn) deselectAllBtn.hidden = count === 0;
    if (selectAllBtn) {
      selectAllBtn.innerHTML = count === total && total > 0
        ? '<i class="fas fa-check-double me-1"></i>All selected'
        : '<i class="fas fa-check-double me-1"></i>Select all';
    }
    if (selectAllCb) {
      selectAllCb.indeterminate = count > 0 && count < total;
      selectAllCb.checked = total > 0 && count === total;
    }
  }

  function setAll(checked) {
    rowCheckboxes().forEach(function (cb) {
      cb.checked = checked;
    });
    updateUi();
  }

  function toast(message, type) {
    if (typeof window.dispatchEvent === 'function') {
      document.dispatchEvent(new CustomEvent('admin:toast', { detail: { message: message, type: type } }));
    }
    var host = document.getElementById('adminToastHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'adminToastHost';
      host.className = 'admin-toast-host';
      document.body.appendChild(host);
    }
    var el = document.createElement('div');
    el.className = 'admin-toast admin-toast-' + (type === 'error' ? 'error' : 'success');
    el.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i><span></span>';
    el.querySelector('span').textContent = message;
    host.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('show'); });
    setTimeout(function () {
      el.classList.remove('show');
      setTimeout(function () { el.remove(); }, 300);
    }, 4200);
  }

  function postBulk(action, items, btn) {
    var fd = new FormData();
    fd.append('action', action);
    fd.append('bulk', '1');
    fd.append('items', JSON.stringify(items));
    fd.append('csrf_token', cfg.csrf || '');

    if (btn) {
      btn.disabled = true;
    }

    return fetch(cfg.url, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) {
      return r.json().then(function (data) {
        return { ok: r.ok, data: data };
      });
    }).finally(function () {
      if (btn) updateUi();
    });
  }

  function removeSelectedRows(items) {
    items.forEach(function (item) {
      var row = table.querySelector(
        'tr[data-admin-entity="' + item.entity + '"][data-admin-id="' + item.id + '"]'
      );
      if (row) row.remove();
    });
    if (!table.querySelector('tbody tr')) {
      window.location.reload();
    }
    updateUi();
  }

  if (selectAllCb) {
    selectAllCb.addEventListener('change', function () {
      setAll(selectAllCb.checked);
    });
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', function () {
      var boxes = rowCheckboxes();
      var allSelected = boxes.length > 0 && boxes.every(function (cb) { return cb.checked; });
      setAll(!allSelected);
    });
  }

  if (deselectAllBtn) {
    deselectAllBtn.addEventListener('click', function () {
      setAll(false);
    });
  }

  table.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('admin-trash-row-cb')) {
      updateUi();
    }
  });

  if (restoreBtn) {
    restoreBtn.addEventListener('click', function () {
      var items = selectedItems();
      if (!items.length) return;
      if (!window.confirm('Restore ' + items.length + ' selected item(s)?')) return;

      postBulk('restore', items, restoreBtn).then(function (res) {
        var data = res.data || {};
        if (!res.ok || !data.success) {
          toast(data.message || 'Restore failed.', 'error');
          return;
        }
        toast(data.message || 'Items restored.', 'success');
        removeSelectedRows(items);
      }).catch(function () {
        toast('Network error. Please try again.', 'error');
      });
    });
  }

  if (purgeBtn) {
    purgeBtn.addEventListener('click', function () {
      var items = selectedItems();
      if (!items.length) return;
      if (!window.confirm('Permanently delete ' + items.length + ' selected item(s)? This cannot be undone.')) return;

      postBulk('purge', items, purgeBtn).then(function (res) {
        var data = res.data || {};
        if (!res.ok || !data.success) {
          toast(data.message || 'Delete failed.', 'error');
          return;
        }
        toast(data.message || 'Items deleted.', 'success');
        removeSelectedRows(items);
      }).catch(function () {
        toast('Network error. Please try again.', 'error');
      });
    });
  }

  updateUi();
})();
