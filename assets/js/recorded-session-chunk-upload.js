(function () {
  'use strict';

  var input = document.getElementById('recordedVideoFile');
  var tokenInput = document.getElementById('videoUploadToken');
  var progressWrap = document.getElementById('recordedUploadProgress');
  var progressBar = document.getElementById('recordedUploadProgressBar');
  var progressText = document.getElementById('recordedUploadProgressText');
  var statusEl = document.getElementById('recordedUploadStatus');
  var submitBtn = document.getElementById('recordedSessionSubmitBtn');
  var form = document.getElementById('recordedSessionAdminForm');

  if (!input || !tokenInput || !form) return;

  var cfg = window.recordedSessionUploadConfig || {};
  var chunkSize = cfg.chunkSize || (8 * 1024 * 1024);
  var uploadUrl = cfg.uploadUrl || '';
  var csrf = cfg.csrf || '';
  var maxBytes = cfg.maxBytes || 0;
  var uploading = false;

  function setStatus(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.style.color = isError ? '#ff8888' : 'var(--cyber-muted)';
  }

  function setProgress(pct, text) {
    if (progressWrap) progressWrap.style.display = pct > 0 && pct < 100 ? '' : (pct >= 100 ? '' : 'none');
    if (progressBar) progressBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    if (progressText) progressText.textContent = text || '';
  }

  function postForm(data, fileField) {
    var fd = new FormData();
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (fileField) fd.append('chunk', fileField);
    return fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) {
      return r.json().then(function (j) { return { ok: r.ok, data: j }; });
    });
  }

  function uploadFile(file) {
    if (uploading) return Promise.reject(new Error('Upload already in progress.'));
    if (maxBytes > 0 && file.size > maxBytes) {
      return Promise.reject(new Error('File exceeds maximum size of ' + (cfg.maxLabel || '50 GB') + '.'));
    }

    uploading = true;
    tokenInput.value = '';
    setStatus('Preparing upload…', false);
    setProgress(1, '0%');

    return postForm({
      action: 'init',
      csrf_token: csrf,
      original_name: file.name,
      total_size: String(file.size)
    }).then(function (res) {
      if (!res.ok || !res.data.ok) throw new Error(res.data.error || 'Could not start upload.');
      var token = res.data.upload_token;
      var offset = 0;

      function sendNext() {
        if (offset >= file.size) {
          return postForm({ action: 'complete', csrf_token: csrf, upload_token: token }).then(function (fin) {
            if (!fin.ok || !fin.data.ok) throw new Error(fin.data.error || 'Could not finalize upload.');
            tokenInput.value = token;
            setProgress(100, '100% — Upload complete');
            setStatus('Video ready: ' + file.name + ' (' + formatBytes(file.size) + ')', false);
            uploading = false;
          });
        }

        var end = Math.min(offset + chunkSize, file.size);
        var blob = file.slice(offset, end);
        var pct = Math.round((offset / file.size) * 100);
        setProgress(pct, pct + '% — ' + formatBytes(offset) + ' / ' + formatBytes(file.size));

        return postForm({
          action: 'chunk',
          csrf_token: csrf,
          upload_token: token,
          offset: String(offset)
        }, blob).then(function (chunkRes) {
          if (!chunkRes.ok || !chunkRes.data.ok) throw new Error(chunkRes.data.error || 'Chunk upload failed.');
          offset = end;
          return sendNext();
        });
      }

      return sendNext();
    }).catch(function (err) {
      uploading = false;
      setProgress(0, '');
      setStatus(err.message || 'Upload failed.', true);
      throw err;
    });
  }

  function formatBytes(n) {
    if (n >= 1073741824) return (n / 1073741824).toFixed(2) + ' GB';
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB';
    if (n >= 1024) return Math.round(n / 1024) + ' KB';
    return n + ' B';
  }

  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (!file) {
      tokenInput.value = '';
      setStatus('', false);
      setProgress(0, '');
      return;
    }
    uploadFile(file).catch(function () {
      input.value = '';
      tokenInput.value = '';
    });
  });

  form.addEventListener('submit', function (e) {
    var requireVideo = form.getAttribute('data-require-video') === '1';
    if (requireVideo && !tokenInput.value && !(input.files && input.files[0])) {
      e.preventDefault();
      setStatus('Please select and upload a video file first.', true);
      return;
    }
    if (uploading) {
      e.preventDefault();
      setStatus('Please wait for the video upload to finish.', true);
      return;
    }
    if (requireVideo && !tokenInput.value) {
      e.preventDefault();
      setStatus('Video upload is still processing. Please wait.', true);
    }
  });
})();
