(function () {
  'use strict';

  function bootChatbotWidget() {
    var root = document.getElementById('cybeorch-chatbot-root');
    if (!root) {
      return;
    }

  var toggleBtn = document.getElementById('cybChatbotToggle');
  var panel = document.getElementById('cybChatbotPanel');
  var closeBtn = document.getElementById('cybChatbotClose');
  var form = document.getElementById('cybChatbotForm');
  var input = document.getElementById('cybChatbotInput');
  var sendBtn = document.getElementById('cybChatbotSend');
  var messages = document.getElementById('cybChatbotMessages');
  var tooltip = document.getElementById('cybChatbotTooltip');
  var badge = document.getElementById('cybChatbotBadge');

  var initUrl = root.getAttribute('data-init-url') || '';
  var sendUrl = root.getAttribute('data-send-url') || '';
  var sessionId = root.getAttribute('data-session-id') || '';
  var welcome = root.getAttribute('data-welcome') || 'Hello! How can I help you today?';
  var botName = root.getAttribute('data-bot-name') || 'Assistant';

  var STORAGE_TOOLTIP = 'chatbotTooltipShown';
  var ATTENTION_MIN_MS = 8000;
  var ATTENTION_MAX_MS = 10000;
  var TOOLTIP_SHOW_MS = 5000;
  var INACTIVITY_MS = 25000;

  var isOpen = false;
  var isSending = false;
  var booted = false;
  var attentionTimer = null;
  var tooltipTimer = null;
  var inactivityTimer = null;

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
  }

  function appendMessage(role, text, extraClass) {
    var bubble = document.createElement('div');
    bubble.className = 'cyb-chatbot-msg ' + role + (extraClass ? ' ' + extraClass : '');
    bubble.innerHTML = escapeHtml(text);
    messages.appendChild(bubble);
    scrollToBottom();
    return bubble;
  }

  function clearTimer(id) {
    if (id) {
      clearTimeout(id);
    }
    return null;
  }

  function randomAttentionDelay() {
    return ATTENTION_MIN_MS + Math.floor(Math.random() * (ATTENTION_MAX_MS - ATTENTION_MIN_MS + 1));
  }

  function triggerAttentionPulse() {
    if (isOpen) return;
    toggleBtn.classList.remove('is-attention');
    void toggleBtn.offsetWidth;
    toggleBtn.classList.add('is-attention');
    window.setTimeout(function () {
      toggleBtn.classList.remove('is-attention');
    }, 820);
  }

  function scheduleAttentionPulse() {
    attentionTimer = clearTimer(attentionTimer);
    if (isOpen) return;
    attentionTimer = window.setTimeout(function () {
      triggerAttentionPulse();
      scheduleAttentionPulse();
    }, randomAttentionDelay());
  }

  function pauseAttentionAnimations() {
    attentionTimer = clearTimer(attentionTimer);
    toggleBtn.classList.remove('is-attention');
  }

  function resumeAttentionAnimations() {
    if (!isOpen) {
      scheduleAttentionPulse();
    }
  }

  function hideTooltip() {
    if (!tooltip) return;
    tooltip.classList.remove('is-visible');
    tooltipTimer = clearTimer(tooltipTimer);
    window.setTimeout(function () {
      tooltip.hidden = true;
      tooltip.setAttribute('aria-hidden', 'true');
    }, 300);
  }

  function showFirstVisitTooltip() {
    if (!tooltip || isOpen) return;
    try {
      if (window.localStorage.getItem(STORAGE_TOOLTIP) === 'true') return;
      window.localStorage.setItem(STORAGE_TOOLTIP, 'true');
    } catch (err) {
      return;
    }

    tooltip.hidden = false;
    tooltip.setAttribute('aria-hidden', 'false');
    window.requestAnimationFrame(function () {
      tooltip.classList.add('is-visible');
    });

    tooltipTimer = window.setTimeout(hideTooltip, TOOLTIP_SHOW_MS);
  }

  function hideInactivityBadge() {
    if (!badge) return;
    badge.classList.remove('is-pulsing');
    badge.hidden = true;
    badge.setAttribute('aria-hidden', 'true');
  }

  function showInactivityBadge() {
    if (!badge || isOpen) return;
    badge.hidden = false;
    badge.setAttribute('aria-hidden', 'false');
    badge.classList.add('is-pulsing');
  }

  function scheduleInactivityReminder() {
    inactivityTimer = clearTimer(inactivityTimer);
    if (isOpen) return;
    inactivityTimer = window.setTimeout(showInactivityBadge, INACTIVITY_MS);
  }

  function dismissAttentionEffects() {
    hideTooltip();
    hideInactivityBadge();
    inactivityTimer = clearTimer(inactivityTimer);
    pauseAttentionAnimations();
  }

  function setOpen(open) {
    isOpen = open;
    root.classList.toggle('is-open', open);
    panel.hidden = !open;
    toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (open) {
      dismissAttentionEffects();
      if (!booted) {
        booted = true;
        appendMessage('bot', welcome);
      }
      input.focus();
    } else {
      resumeAttentionAnimations();
      scheduleInactivityReminder();
    }
  }

  function autoResizeInput() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  }

  async function sendMessage(text) {
    if (!text.trim() || isSending) return;
    isSending = true;
    sendBtn.disabled = true;

    appendMessage('user', text.trim());
    input.value = '';
    autoResizeInput();

    var typing = appendMessage('bot', botName + ' is typing…', 'typing');

    try {
      var response = await fetch(sendUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
          message: text.trim(),
          session_id: sessionId
        })
      });

      var data = await response.json();
      typing.remove();

      if (!response.ok || !data.success) {
        appendMessage('bot', data.message || data.answer || 'Sorry, something went wrong. Please try again.');
      } else {
        appendMessage('bot', data.answer || 'No response received.');
      }
    } catch (err) {
      typing.remove();
      appendMessage('bot', 'Network error. Please check your connection and try again.');
    } finally {
      isSending = false;
      sendBtn.disabled = false;
      input.focus();
    }
  }

  toggleBtn.addEventListener('click', function () {
    if (!isOpen) {
      dismissAttentionEffects();
    }
    setOpen(!isOpen);
  });

  closeBtn.addEventListener('click', function () {
    setOpen(false);
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    sendMessage(input.value);
  });

  input.addEventListener('input', autoResizeInput);

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage(input.value);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && isOpen) {
      setOpen(false);
    }
  });

  window.setTimeout(showFirstVisitTooltip, 600);
  scheduleInactivityReminder();
  scheduleAttentionPulse();

  if (initUrl) {
    fetch(initUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).catch(function () {});
  }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootChatbotWidget);
  } else {
    bootChatbotWidget();
  }
})();
