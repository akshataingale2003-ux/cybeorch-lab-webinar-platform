<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/closing-soon-settings.php';
require_once __DIR__ . '/../includes/chatbot.php';
requireAdminLogin();

ensureClosingSoonSettingsSchema();
ensureChatbotSchema();
$closingSoonSettings = getClosingSoonSettings();
$chatbotSettings = adminDb(fn () => getChatbotSettings(), chatbotSettingsDefaults());
$pageFlash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_closing_soon' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $result = saveClosingSoonSettings($_POST);
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    header('Location: ' . adminUrl('admin/settings.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_chatbot' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $result = adminDb(fn () => saveChatbotSettings($_POST), ['success' => false, 'message' => 'Database error.']);
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    header('Location: ' . adminUrl('settings.php') . '#chatbot-settings');
    exit;
}

$adminUser = adminDb(
    fn () => db()->fetchOne('SELECT username, email, full_name, role, last_login, created_at FROM admin WHERE id = ?', [(int) ($_SESSION['admin_id'] ?? 0)]),
    null
);

$dbStatus = getDatabaseConnectionStatus();
$tableStats = $dbStatus['connected'] ? getSiteTableStats() : [];
$formLinks = getPublicFormAdminLinks();

renderAdminPageStart('Settings & Database', 'settings', 'fa-cog');
?>

<?php if ($pageFlash): ?>
<div class="alert alert-<?= $pageFlash['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom:1rem">
  <?= htmlspecialchars($pageFlash['message']) ?>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">Database connection</div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Site name</label><span><?= htmlspecialchars(SITE_NAME) ?></span></div>
      <div class="detail-item"><label>Site URL</label><span><?= htmlspecialchars(SITE_URL) ?></span></div>
      <div class="detail-item"><label>Database</label><span><?= htmlspecialchars(DB_NAME) ?> @ <?= htmlspecialchars(DB_HOST) ?></span></div>
      <div class="detail-item"><label>Status</label>
        <span><?= $dbStatus['connected']
            ? '<span class="badge-status badge-paid">Connected</span>'
            : '<span class="badge-status badge-failed">Offline</span>' ?></span>
      </div>
    </div>
    <p style="margin-top:1rem;font-size:.88rem;color:var(--cyber-muted)"><?= htmlspecialchars($dbStatus['message']) ?></p>
    <?php if (!$dbStatus['connected']): ?>
    <p style="margin-top:.75rem;font-size:.85rem;color:#ff8888">
      Start <strong>MySQL</strong> in XAMPP, then import <code>database.sql</code> into phpMyAdmin (database: <code><?= htmlspecialchars(DB_NAME) ?></code>).
    </p>
    <?php endif; ?>
  </div>
</div>

<?php if ($dbStatus['connected'] && $tableStats): ?>
<div class="section-card">
  <div class="section-card-header">Website data in database (admin pages)</div>
  <div class="section-card-body">
    <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      Every row below is stored in MySQL and managed from the linked admin screen.
    </p>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Data</th><th>Table</th><th>Records</th><th>Admin page</th></tr>
        </thead>
        <tbody>
          <?php foreach ($tableStats as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['label']) ?></td>
            <td><code><?= htmlspecialchars($row['table']) ?></code></td>
            <td><?= (int) $row['count'] ?></td>
            <td><a href="<?= adminUrl($row['admin_page']) ?>" class="btn-sm-link">Open</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">Public forms → Admin</div>
  <div class="section-card-body">
    <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:1rem">
      Visitor forms on the website save to the database. View submissions under <strong>Messages</strong> or the linked admin page.
    </p>
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Website form</th><th>Page</th><th>Stored as</th><th>View in admin</th></tr>
        </thead>
        <tbody>
          <?php foreach ($formLinks as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['form']) ?></td>
            <td><a href="<?= url($f['page']) ?>" class="btn-sm-link" target="_blank" rel="noopener"><?= htmlspecialchars($f['page']) ?></a></td>
            <td style="font-size:.82rem;color:var(--cyber-muted)"><?= htmlspecialchars($f['subject']) ?></td>
            <td>
              <?php if (str_contains($f['admin_filter'], 'admin/')): ?>
              <a href="<?= adminUrl($f['admin_filter']) ?>" class="btn-sm-link">Open</a>
              <?php else: ?>
              <a href="<?= adminUrl('admin/messages.php?subject=' . rawurlencode($f['admin_filter'])) ?>" class="btn-sm-link"><?= htmlspecialchars($f['admin_filter']) ?></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card" id="chatbot-settings">
  <div class="section-card-header"><i class="fas fa-robot" style="color:var(--cyber-accent)"></i> AI Chatbot</div>
  <div class="section-card-body">
    <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:1.25rem;line-height:1.6">
      Controls the floating chat widget on all public pages. Q&amp;A pairs are managed under
      <a href="<?= adminUrl('manage_qa.php') ?>" class="btn-sm-link">Manage Q&amp;A</a>.
      View conversations in <a href="<?= adminUrl('chat_history.php') ?>" class="btn-sm-link">Chat History</a>.
    </p>
    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="save_chatbot">
      <div class="col-md-6">
        <label style="display:flex;align-items:flex-start;gap:.55rem;cursor:pointer">
          <input type="checkbox" name="chatbot_enabled" value="1" style="accent-color:var(--cyber-accent);margin-top:.2rem" <?= ($chatbotSettings['chatbot_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span style="font-size:.88rem;color:var(--cyber-text);line-height:1.45">Enable chatbot widget on website</span>
        </label>
      </div>
      <div class="col-md-6">
        <label style="display:flex;align-items:flex-start;gap:.55rem;cursor:pointer">
          <input type="checkbox" name="chatbot_ai_enabled" value="1" style="accent-color:var(--cyber-accent);margin-top:.2rem" <?= ($chatbotSettings['chatbot_ai_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span style="font-size:.88rem;color:var(--cyber-text);line-height:1.45">Enable AI fallback (Gemini / OpenAI)</span>
        </label>
      </div>
      <div class="col-md-4">
        <label class="form-label">Bot name</label>
        <input type="text" name="chatbot_bot_name" class="form-control" required maxlength="80" value="<?= htmlspecialchars($chatbotSettings['chatbot_bot_name'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">AI provider</label>
        <select name="chatbot_api_provider" class="form-control">
          <option value="gemini" <?= ($chatbotSettings['chatbot_api_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
          <option value="openai" <?= ($chatbotSettings['chatbot_api_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">API key</label>
        <input type="password" name="chatbot_api_key" class="form-control" maxlength="500" autocomplete="new-password"
               placeholder="<?= trim((string) ($chatbotSettings['chatbot_api_key'] ?? '')) !== '' ? '•••••••• (leave blank to keep current)' : 'Paste your API key' ?>"
               value="">
        <?php if (trim((string) ($chatbotSettings['chatbot_api_key'] ?? '')) !== ''): ?>
        <p class="password-hint">A key is saved. Submit a new value only to replace it.</p>
        <?php endif; ?>
      </div>
      <div class="col-12">
        <label class="form-label">Welcome message</label>
        <textarea name="chatbot_welcome_message" class="form-control" rows="2" required><?= htmlspecialchars($chatbotSettings['chatbot_welcome_message'] ?? '') ?></textarea>
      </div>
      <div class="col-12">
        <label class="form-label">Offline / fallback message (when AI is disabled or unavailable)</label>
        <textarea name="chatbot_offline_message" class="form-control" rows="2" required><?= htmlspecialchars($chatbotSettings['chatbot_offline_message'] ?? '') ?></textarea>
      </div>
      <div class="col-12">
        <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Save chatbot settings</button>
        <a href="<?= adminUrl('chatbot.php') ?>" class="btn-cancel" style="margin-left:0.5rem">Test chatbot</a>
      </div>
    </form>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header"><i class="fas fa-bullhorn" style="color:var(--cyber-accent)"></i> Registration Closing Soon Popup</div>
  <div class="section-card-body">
    <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:1.25rem;line-height:1.6">
      Controls the welcome announcement shown on the <strong>Dashboard</strong> and <strong>Homepage</strong> after users sign in.
      It never appears on the login or sign-up pages.
    </p>
    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="save_closing_soon">
      <div class="col-12">
        <label style="display:flex;align-items:flex-start;gap:.55rem;cursor:pointer">
          <input type="checkbox" name="closing_soon_enabled" value="1" style="accent-color:var(--cyber-accent);margin-top:.2rem" <?= ($closingSoonSettings['closing_soon_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span style="font-size:.88rem;color:var(--cyber-text);line-height:1.45">
            Enable popup on Dashboard / Homepage for signed-in users
          </span>
        </label>
      </div>
      <div class="col-md-6">
        <label class="form-label">Popup title</label>
        <input type="text" name="closing_soon_title" class="form-control" required maxlength="200" value="<?= htmlspecialchars($closingSoonSettings['closing_soon_title'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Registration deadline (optional)</label>
        <?php
        $deadlineValue = '';
        $deadlineRaw = trim((string) ($closingSoonSettings['closing_soon_deadline'] ?? ''));
        if ($deadlineRaw !== '' && $deadlineRaw !== '0000-00-00 00:00:00') {
            try {
                $deadlineValue = (new DateTimeImmutable($deadlineRaw))->format('Y-m-d\TH:i');
            } catch (Throwable $e) {
                $deadlineValue = '';
            }
        }
        ?>
        <input type="datetime-local" name="closing_soon_deadline" class="form-control" value="<?= htmlspecialchars($deadlineValue) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Popup message</label>
        <textarea name="closing_soon_message" class="form-control" rows="4" required><?= htmlspecialchars($closingSoonSettings['closing_soon_message'] ?? '') ?></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label">Button text</label>
        <input type="text" name="closing_soon_button_text" class="form-control" required maxlength="80" value="<?= htmlspecialchars($closingSoonSettings['closing_soon_button_text'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Button link (site path)</label>
        <input type="text" name="closing_soon_button_url" class="form-control" required maxlength="255" placeholder="webinars.php" value="<?= htmlspecialchars($closingSoonSettings['closing_soon_button_url'] ?? 'webinars.php') ?>">
      </div>
      <div class="col-12">
        <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Save popup settings</button>
      </div>
    </form>
  </div>
</div>

<?php if ($adminUser): ?>
<div class="section-card">
  <div class="section-card-header">Your admin account</div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Username</label><span><?= htmlspecialchars($adminUser['username']) ?></span></div>
      <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($adminUser['email']) ?></span></div>
      <div class="detail-item"><label>Name</label><span><?= htmlspecialchars($adminUser['full_name'] ?? '') ?></span></div>
      <div class="detail-item"><label>Role</label><span><?= htmlspecialchars($adminUser['role']) ?></span></div>
      <div class="detail-item"><label>Last login</label><span><?= $adminUser['last_login'] ? date('d M Y H:i', strtotime($adminUser['last_login'])) : '—' ?></span></div>
    </div>
    <p style="margin-top:1rem;font-size:0.85rem;color:var(--cyber-muted)">Admin login: <a href="<?= adminUrl('admin/login.php') ?>" class="btn-sm-link">admin/login.php</a></p>
  </div>
</div>
<?php endif; ?>

<?php renderAdminPageEnd(); ?>
