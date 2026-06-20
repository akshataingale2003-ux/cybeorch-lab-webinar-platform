<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/chatbot.php';
requireAdminLogin();

ensureChatbotSchema();
$settings = adminDb(fn () => getChatbotSettings(), chatbotSettingsDefaults());
$flash = getFlash();

$qaCount = (int) adminDb(fn () => countChatbotQaPairs(), 0);
$historyCount = (int) adminDb(fn () => countChatHistory(), 0);
$todayCount = (int) adminDb(
    fn () => (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM chat_history WHERE DATE(created_at) = CURDATE()')['c'] ?? 0),
    0
);

renderAdminPageStart('AI Chatbot', 'chatbot', 'fa-robot');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="stat-cards-row">
  <div class="stat-card">
    <div class="stat-value"><?= ($settings['chatbot_enabled'] ?? '0') === '1' ? 'ON' : 'OFF' ?></div>
    <div class="stat-label">Widget status</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= ($settings['chatbot_ai_enabled'] ?? '0') === '1' ? 'ON' : 'OFF' ?></div>
    <div class="stat-label">AI responses</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format($qaCount) ?></div>
    <div class="stat-label">Q&amp;A pairs</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format($todayCount) ?></div>
    <div class="stat-label">Chats today</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format($historyCount) ?></div>
    <div class="stat-label">Total chats logged</div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">Quick actions</div>
  <div class="section-card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:0.75rem">
      <a href="<?= adminUrl('manage_qa.php') ?>" class="quick-action">
        <i class="fas fa-question-circle" style="font-size:1.4rem;color:var(--cyber-accent)"></i>
        <span>Manage Q&amp;A</span>
      </a>
      <a href="<?= adminUrl('chat_history.php') ?>" class="quick-action">
        <i class="fas fa-comments" style="font-size:1.4rem;color:var(--cyber-accent)"></i>
        <span>Chat history</span>
      </a>
      <a href="<?= adminUrl('settings.php') ?>#chatbot-settings" class="quick-action">
        <i class="fas fa-cog" style="font-size:1.4rem;color:var(--cyber-accent)"></i>
        <span>Chatbot settings</span>
      </a>
      <a href="<?= url('index.php') ?>" target="_blank" rel="noopener" class="quick-action">
        <i class="fas fa-external-link-alt" style="font-size:1.4rem;color:var(--cyber-accent)"></i>
        <span>View on site</span>
      </a>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header">Test the chatbot</div>
  <div class="section-card-body">
    <p style="font-size:0.88rem;color:var(--cyber-muted);margin-bottom:1rem;line-height:1.6">
      Use the floating chat button at the bottom-right of this page to test responses.
      Messages are saved to <a href="<?= adminUrl('chat_history.php') ?>" class="btn-sm-link">Chat History</a>.
      Configure the API key and enable/disable AI under
      <a href="<?= adminUrl('settings.php') ?>#chatbot-settings" class="btn-sm-link">Settings</a>.
    </p>
    <ul style="font-size:0.85rem;color:var(--cyber-muted);line-height:1.7;padding-left:1.2rem">
      <li>Provider: <strong style="color:var(--cyber-text)"><?= htmlspecialchars(strtoupper((string) ($settings['chatbot_api_provider'] ?? 'gemini'))) ?></strong></li>
      <li>API key: <strong style="color:var(--cyber-text)"><?= trim((string) ($settings['chatbot_api_key'] ?? '')) !== '' ? 'Configured' : 'Not set' ?></strong></li>
      <li>Try a seeded question like <em>“What is CYBEORCH?”</em> or <em>“How do I register for a webinar?”</em></li>
    </ul>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
