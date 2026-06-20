<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/responsive.php';

startSession();

$local = cybeorchIsLocalDev();
if (!$local && smtpIsConfigured()) {
    http_response_code(403);
    exit('SMTP setup is restricted to local development or when SMTP is not configured.');
}

$preset    = cybeorchSmtpPreset();
$message   = '';
$messageType = 'info';
$envExists = is_file(cybeorchEnvPath());
$envUser   = cybeorchEnv('EMAIL_USER', defined('SMTP_USER') ? (string) SMTP_USER : $preset['user']);
$envHost   = cybeorchEnv('SMTP_HOST', defined('SMTP_HOST') ? (string) SMTP_HOST : $preset['host']);
$envPort   = cybeorchEnv('SMTP_PORT', defined('SMTP_PORT') ? (string) SMTP_PORT : (string) $preset['port']);
$envSecure = cybeorchEnv('SMTP_SECURE', defined('SMTP_SECURE') ? (string) SMTP_SECURE : $preset['secure']);
$isGmail   = str_contains(strtolower($envHost), 'gmail');

// Auto: sync config.local.php → .env on local machine when SMTP is ready but .env is missing
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && $local
    && !$envExists
    && smtpIsConfigured()
) {
    $sync = cybeorchWriteDotEnvFromConstants();
    if ($sync['ok']) {
        $envExists = true;
        $send = sendSmtpTestEmail($envUser);
        if ($send['ok']) {
            $message = 'Auto-setup: synced config.local.php to .env and sent a test email to '
                . htmlspecialchars($envUser, ENT_QUOTES, 'UTF-8')
                . '. <a href="' . htmlspecialchars(url('index.php')) . '">Open home</a>';
            $messageType = 'success';
        } else {
            $message = 'Auto-setup: .env created from config.local.php, but test email failed: '
                . htmlspecialchars($send['error'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
            $messageType = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'sync_local') {
        $sync = cybeorchWriteDotEnvFromConstants();
        if (!$sync['ok']) {
            $message = $sync['message'];
            $messageType = 'error';
        } else {
            $envExists = true;
            $testTo = filter_var(trim((string) ($_POST['test_email'] ?? '')), FILTER_VALIDATE_EMAIL)
                ? trim((string) $_POST['test_email']) : $envUser;
            $send = sendSmtpTestEmail($testTo);
            if ($send['ok']) {
                $message = 'Synced config.local.php to .env and test email sent to '
                    . htmlspecialchars($testTo, ENT_QUOTES, 'UTF-8')
                    . '. <a href="' . htmlspecialchars(url('index.php')) . '">Open home</a>';
                $messageType = 'success';
            } else {
                $message = '.env synced but test failed: ' . htmlspecialchars($send['error'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                $messageType = 'error';
            }
        }
    } else {
        $email  = strtolower(trim((string) ($_POST['smtp_user'] ?? '')));
        $pass   = trim((string) ($_POST['smtp_pass'] ?? ''));
        $name   = trim((string) ($_POST['smtp_from_name'] ?? 'CYBEORCH LABS'));
        $hostIn = trim((string) ($_POST['smtp_host'] ?? ''));
        $portIn = (int) ($_POST['smtp_port'] ?? 0);
        $secIn  = trim((string) ($_POST['smtp_secure'] ?? ''));

        if ($isGmail || str_contains(strtolower($email), '@gmail.com')) {
            $pass = preg_replace('/\s+/', '', $pass) ?? '';
        }

        $write = cybeorchWriteDotEnv($email, $pass, $name, $hostIn !== '' ? $hostIn : null, $portIn > 0 ? $portIn : null, $secIn !== '' ? $secIn : null);
        if (!$write['ok']) {
            $message = $write['message'];
            $messageType = 'error';
        } else {
            $envExists = true;
            $testTo = filter_var(trim((string) ($_POST['test_email'] ?? '')), FILTER_VALIDATE_EMAIL)
                ? trim((string) $_POST['test_email']) : $email;
            $send = sendSmtpTestEmail($testTo);
            if ($send['ok']) {
                $message = 'Saved .env and test email sent to ' . htmlspecialchars($testTo, ENT_QUOTES, 'UTF-8')
                    . '. Reload the site and use Send OTP. <a href="' . htmlspecialchars(url('index.php')) . '">Open home</a>';
                $messageType = 'success';
            } else {
                $message = 'Saved .env but test failed: ' . htmlspecialchars($send['error'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
                $messageType = 'error';
            }
        }
    }
}

$csrf = generateCSRF();
$home = url('index.php');
$setup = url('setup-smtp.php');
$hasLocalConfig = is_file(__DIR__ . '/includes/config.local.php');
$localConfigured = smtpIsConfigured() && ($hasLocalConfig || $envExists);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CYBEORCH — SMTP Setup</title>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/style.css')) ?>">
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;background:#050b18;font-family:'DM Sans',sans-serif;color:#e0e8f0}
.wrap{max-width:600px;width:100%}
.card{padding:1.5rem;border-radius:16px;border:1px solid rgba(0,212,255,.35);background:rgba(5,11,24,.92);margin-bottom:1rem}
label{display:block;font-size:.82rem;color:#7a8fa6;margin:.65rem 0 .25rem}
input{width:100%;box-sizing:border-box;padding:.65rem .9rem;border-radius:8px;border:1px solid rgba(0,212,255,.25);background:rgba(255,255,255,.06);color:#fff}
select{width:100%;box-sizing:border-box;padding:.65rem .9rem;border-radius:8px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
button,.btn-link{width:100%;margin-top:1rem;padding:.8rem;border:0;border-radius:8px;background:linear-gradient(135deg,#00d4ff,#00ff88);font-weight:700;cursor:pointer;color:#050b18;text-align:center;text-decoration:none;display:block;box-sizing:border-box}
.btn-secondary{background:rgba(0,212,255,.15);color:#00d4ff;border:1px solid rgba(0,212,255,.35)}
.msg{padding:.65rem;border-radius:8px;margin-bottom:1rem;font-size:.88rem}
.msg-ok{background:rgba(0,255,136,.1);color:#00ff88}
.msg-err{background:rgba(255,68,68,.12);color:#ff8888}
.steps{font-size:.82rem;color:#7a8fa6;line-height:1.55;margin:0;padding-left:1.2rem}
.steps li{margin:.35rem 0}
a{color:#00d4ff}
code{color:#ffd166;font-size:.8rem}
pre{background:rgba(0,0,0,.35);padding:.75rem;border-radius:8px;font-size:.75rem;overflow-x:auto;color:#a8d4ff}
</style>
<?php renderFormSelectStyles(); ?>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1 style="color:#00d4ff;margin:0 0 .5rem;font-family:Rajdhani,sans-serif">CYBEORCH SMTP Setup</h1>
    <p style="color:#7a8fa6;font-size:.88rem;margin:0">Default: <code><?= htmlspecialchars($preset['host']) ?>:<?= (int) $preset['port'] ?> <?= htmlspecialchars($preset['secure']) ?></code> — <code><?= htmlspecialchars($preset['user']) ?></code></p>
    <?php if ($message !== ''): ?>
    <div class="msg <?= $messageType === 'success' ? 'msg-ok' : 'msg-err' ?>"><?= $message ?></div>
    <?php elseif ($envExists): ?>
    <div class="msg msg-ok">.env exists. Submit again to update credentials.</div>
    <?php elseif ($local && !$hasLocalConfig): ?>
    <div class="msg msg-err" style="color:#a8d4ff;background:rgba(0,212,255,.08)">No .env yet. Save the form below, or copy <code>includes/config.local.php.example</code> → <code>config.local.php</code> with your SMTP password and reload (auto-sync).</div>
    <?php endif; ?>

    <?php if ($hasLocalConfig && $localConfigured): ?>
    <form method="POST" action="<?= htmlspecialchars($setup) ?>" style="margin-bottom:1rem">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="sync_local">
      <button type="submit" class="btn-secondary">Sync config.local.php → .env &amp; test</button>
    </form>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($setup) ?>" id="smtp-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="save">
      <label>EMAIL_USER</label>
      <input type="email" name="smtp_user" required placeholder="<?= htmlspecialchars($preset['user']) ?>" value="<?= htmlspecialchars($envUser) ?>">
      <label>EMAIL_PASS</label>
      <input type="password" name="smtp_pass" required minlength="8" placeholder="Mailbox password" autocomplete="off">
      <div class="row">
        <div>
          <label>SMTP_HOST</label>
          <input type="text" name="smtp_host" id="smtp_host" value="<?= htmlspecialchars($envHost) ?>">
        </div>
        <div>
          <label>SMTP_PORT</label>
          <input type="number" name="smtp_port" id="smtp_port" value="<?= htmlspecialchars($envPort) ?>">
        </div>
      </div>
      <label>SMTP_SECURE</label>
      <select name="smtp_secure" id="smtp_secure">
        <option value="ssl"<?= strtolower($envSecure) === 'ssl' ? ' selected' : '' ?>>ssl (465)</option>
        <option value="tls"<?= strtolower($envSecure) === 'tls' ? ' selected' : '' ?>>tls (587)</option>
      </select>
      <label>SMTP_FROM_NAME</label>
      <input type="text" name="smtp_from_name" value="<?= htmlspecialchars(cybeorchEnv('SMTP_FROM_NAME', $preset['from_name'])) ?>">
      <label>Test email (optional)</label>
      <input type="email" name="test_email" placeholder="Same as EMAIL_USER">
      <button type="submit">Save .env & Send Test Email</button>
    </form>
    <p style="text-align:center;margin-top:1rem;font-size:.8rem"><a href="<?= htmlspecialchars($home) ?>">← Home</a></p>
  </div>

  <div class="card">
    <p style="margin:0 0 .5rem;color:#7a8fa6;font-size:.85rem"><strong>CYBEORCH .env</strong> (project root):</p>
    <pre>EMAIL_USER=<?= htmlspecialchars($preset['user']) ?>

EMAIL_PASS=your_smtp_password
SMTP_HOST=<?= htmlspecialchars($preset['host']) ?>

SMTP_PORT=<?= (int) $preset['port'] ?>

SMTP_SECURE=<?= htmlspecialchars($preset['secure']) ?>

SMTP_FROM_NAME=CYBEORCH LABS</pre>
  <p style="margin:.75rem 0 0;font-size:.8rem;color:#5a6d82">Or copy <code>includes/config.local.php.example</code> to <code>config.local.php</code>, set <code>SMTP_PASS</code>, open this page locally — it auto-writes <code>.env</code>.</p>
  </div>

  <div class="card">
    <p style="margin:0 0 .5rem;color:#7a8fa6;font-size:.85rem"><strong>Stackmail (registration OTP)</strong></p>
    <ol class="steps">
      <li><strong>EMAIL_USER:</strong> <code>info@xyz.com</code> (full mailbox address)</li>
      <li><strong>EMAIL_PASS:</strong> nexus@369 (same as webmail login)</li>
      <li><strong>Outgoing:</strong> <code>smtp.stackmail.com</code>, port <code>465</code>, secure <code>ssl</code></li>
      <li>Authentication required — leave SMTP auth enabled (handled automatically)</li>
      <li>Incoming mail (IMAP) is not used by this app; only SMTP is needed for OTP</li>
    </ol>
    <p style="margin:.75rem 0 0;font-size:.78rem;color:#5a6d82">IMAP (optional): <code>imap.stackmail.com</code> port 993 SSL · POP3: port 995</p>
  </div>

  <div class="card">
    <p style="margin:0 0 .5rem;color:#7a8fa6;font-size:.85rem"><strong>Gmail (optional)</strong></p>
    <ol class="steps">
      <li><a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App passwords</a> → 16 letters, no spaces</li>
      <li>Use <code>smtp.gmail.com</code>, port <code>587</code>, secure <code>tls</code></li>
    </ol>
  </div>
</div>
<script>
(function () {
  var preset = <?= json_encode($preset, JSON_THROW_ON_ERROR) ?>;
  var user = document.querySelector('input[name="smtp_user"]');
  var host = document.getElementById('smtp_host');
  var port = document.getElementById('smtp_port');
  var sec = document.getElementById('smtp_secure');
  function applyPreset() {
    var e = (user && user.value || '').toLowerCase();
    if (e.indexOf('@cybeorch.com') !== -1) {
      host.value = preset.host;
      port.value = String(preset.port);
      sec.value = preset.secure;
    } else if (e.indexOf('@gmail.com') !== -1) {
      host.value = 'smtp.gmail.com';
      port.value = '587';
      sec.value = 'tls';
    }
  }
  if (user) user.addEventListener('change', applyPreset);
  if (user) user.addEventListener('blur', applyPreset);
})();
</script>
</body>
</html>
