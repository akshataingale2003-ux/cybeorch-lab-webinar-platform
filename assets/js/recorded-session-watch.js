(function () {
  'use strict';

  var video = document.getElementById('recordedSessionVideo');
  if (!video || video.getAttribute('data-track-watch') !== '1') return;

  var watchUrl = video.getAttribute('data-watch-url') || '';
  var csrf = video.getAttribute('data-csrf') || '';
  var sessionId = video.getAttribute('data-session-id') || '';
  var notice = document.getElementById('recordedWatchNotice');
  var lastProgressAt = 0;
  var completionSent = false;

  function showNotice(msg, isError) {
    if (!notice) return;
    notice.style.display = msg ? '' : 'none';
    notice.textContent = msg || '';
    notice.style.color = isError ? '#ff8888' : 'var(--cyber-green)';
  }

  function postWatch(action, position, duration) {
    var fd = new FormData();
    fd.append('csrf_token', csrf);
    fd.append('session_id', sessionId);
    fd.append('action', action);
    fd.append('position', String(position));
    fd.append('duration', String(duration));
    return fetch(watchUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  video.addEventListener('timeupdate', function () {
    var now = Date.now();
    if (now - lastProgressAt < 4000) return;
    lastProgressAt = now;
    var dur = video.duration;
    if (!isFinite(dur) || dur <= 0) return;
    postWatch('progress', video.currentTime, dur).catch(function () {});
  });

  function onComplete() {
    if (completionSent) return;
    completionSent = true;
    var dur = video.duration;
    if (!isFinite(dur) || dur <= 0) return;
    postWatch('complete', video.currentTime, dur).then(function (res) {
      if (res.counted) {
        showNotice(res.message || 'Full view recorded.', false);
      } else if (res.message) {
        showNotice(res.message, !res.ok);
      }
    }).catch(function () {
      showNotice('Could not record view. Please try again.', true);
      completionSent = false;
    });
  }

  video.addEventListener('ended', onComplete);
})();
