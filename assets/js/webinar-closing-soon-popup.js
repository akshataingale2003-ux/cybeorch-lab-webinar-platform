/**
 * CYBEORCH — Webinar registration closing soon popup (once per browser session).
 */
(function () {
  'use strict';

  console.log('Closing Soon popup loaded');

  var cfg = window.CYBEORCH_WEBINAR_CLOSING;
  if (!cfg) {
    console.warn('[WCS] No popup config — popup may be disabled or markup was not rendered.');
    return;
  }

  var storageKey = cfg.storageKey || 'cybeorch_wcs_dismiss_session';
  var params = new URLSearchParams(window.location.search);
  var forceShow = params.has('wcs_force') || params.get('wcs_test') === '1';
  var resetDismiss = params.has('wcs_reset');

  if (resetDismiss) {
    try {
      sessionStorage.removeItem(storageKey);
      console.log('[WCS] Cleared session dismiss flag:', storageKey);
    } catch (e) {
      console.warn('[WCS] Could not clear sessionStorage', e);
    }
  }

  function wasDismissedThisSession() {
    if (forceShow) {
      return false;
    }
    try {
      return sessionStorage.getItem(storageKey) === '1';
    } catch (e) {
      return false;
    }
  }

  function markDismissed() {
    try {
      sessionStorage.setItem(storageKey, '1');
    } catch (e) {
      // ignore
    }
  }

  function formatDeadline(iso) {
    if (!iso) {
      return '';
    }
    var d = new Date(iso);
    if (isNaN(d.getTime())) {
      return '';
    }
    return 'Registration closes ' + d.toLocaleString(undefined, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit'
    });
  }

  function findModal() {
    return document.getElementById('webinarClosingSoonModal')
      || document.getElementById('closingSoonModal');
  }

  function openModal(modal) {
    modal.removeAttribute('hidden');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    modal.style.display = 'flex';
    document.body.classList.add('wcs-modal-open');
    var closeBtn = modal.querySelector('.wcs-modal__close');
    if (closeBtn) {
      closeBtn.focus();
    }
    console.log('[WCS] Popup opened');
  }

  function closeModal(modal) {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('hidden', '');
    modal.style.display = 'none';
    document.body.classList.remove('wcs-modal-open');
    markDismissed();
    console.log('[WCS] Popup closed');
  }

  function mandatoryAuthBlocksView() {
    var auth = document.getElementById('authRegModal');
    return !!(auth && auth.classList.contains('auth-reg-modal-overlay--mandatory') && !auth.hidden);
  }

  function scheduleOpen(modal, delayMs) {
    window.setTimeout(function () {
      if (!forceShow && wasDismissedThisSession()) {
        console.log('[WCS] Skipped — already dismissed this session');
        return;
      }
      if (!modal || !modal.isConnected) {
        console.warn('[WCS] Modal element missing at trigger time');
        return;
      }
      console.log('Popup trigger executed');
      openModal(modal);
    }, delayMs);
  }

  function init() {
    console.log('[WCS] Init', cfg);

    var modal = findModal();
    if (!modal) {
      console.warn('[WCS] Modal #webinarClosingSoonModal not found in DOM');
      return;
    }

    if (!forceShow && wasDismissedThisSession()) {
      console.log('[WCS] Skipped — dismissed flag present:', storageKey);
      return;
    }

    var modalTitleEl = modal.querySelector('[data-wcs-modal-title]');
    var titleEl = modal.querySelector('[data-wcs-webinar-title]');
    var messageEl = modal.querySelector('[data-wcs-message]');
    var deadlineEl = modal.querySelector('[data-wcs-deadline]');
    var registerBtn = modal.querySelector('[data-wcs-register]');

    if (modalTitleEl) {
      modalTitleEl.textContent = cfg.modalTitle || '\u26A0 Registration Closing Soon';
    }
    if (titleEl) {
      if (cfg.title) {
        titleEl.textContent = cfg.title;
        titleEl.hidden = false;
      } else {
        titleEl.textContent = '';
        titleEl.hidden = true;
      }
    }
    if (messageEl) {
      messageEl.textContent = cfg.message || '';
    }

    var deadlineText = formatDeadline(cfg.closesAt);
    if (deadlineEl) {
      if (deadlineText) {
        deadlineEl.textContent = deadlineText;
        deadlineEl.hidden = false;
      } else {
        deadlineEl.hidden = true;
      }
    }

    if (registerBtn) {
      registerBtn.href = cfg.registerUrl || '#';
      registerBtn.textContent = cfg.registerLabel || 'Register Now';
      registerBtn.addEventListener('click', function () {
        markDismissed();
      });
    }

    modal.querySelectorAll('[data-wcs-close]').forEach(function (el) {
      el.addEventListener('click', function () {
        closeModal(modal);
      });
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal(modal);
      }
    });

    var delay = mandatoryAuthBlocksView() ? 1200 : 700;
    scheduleOpen(modal, delay);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.CYBEORCH_WEBINAR_CLOSING_OPEN = function () {
    var modal = findModal();
    if (modal) {
      openModal(modal);
    }
  };
})();

