<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/chatbot.php';
requireAdminLogin();

ensureChatbotSchema();

$flash = getFlash();
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$total = (int) adminDb(fn () => countChatHistory($search), 0);
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        adminDb(fn () => deleteChatHistoryEntry($id));
        adminFlashRedirect('chat_history.php' . ($search !== '' ? '?q=' . rawurlencode($search) : ''), 'success', 'Chat entry deleted.');
    }
    if ($action === 'clear_all') {
        $deleted = (int) adminDb(fn () => clearAllChatHistory(), 0);
        adminFlashRedirect('chat_history.php', 'success', 'Cleared ' . number_format($deleted) . ' chat history entries.');
    }
}

$rows = adminDb(fn () => listChatHistory($page, $perPage, $search), []);

renderAdminPageStart('Chat History', 'chat_history', 'fa-comments');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="stat-cards-row">
  <div class="stat-card">
    <div class="stat-value"><?= number_format($total) ?></div>
    <div class="stat-label">Total conversations logged</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format((int) adminDb(fn () => (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM chat_history WHERE source='qa'")['c'] ?? 0), 0)) ?></div>
    <div class="stat-label">Answered from Q&A</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= number_format((int) adminDb(fn () => (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM chat_history WHERE source='ai'")['c'] ?? 0), 0)) ?></div>
    <div class="stat-label">Answered by AI</div>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <span>All chat history</span>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
      <a href="<?= adminUrl('manage_qa.php') ?>" class="btn-primary-cyber btn-sm-cyber"><i class="fas fa-list"></i> Manage Q&amp;A</a>
      <a href="<?= adminUrl('chatbot.php') ?>" class="btn-primary-cyber btn-sm-cyber"><i class="fas fa-robot"></i> Test chatbot</a>
      <?php if ($total > 0): ?>
      <form method="post" onsubmit="return confirm('Delete ALL chat history? This cannot be undone.');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
        <input type="hidden" name="action" value="clear_all">
        <button type="submit" class="btn-sm-cyber btn-delete"><i class="fas fa-trash"></i> Clear all</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="section-card-body">
    <form method="get" class="admin-search-form" style="max-width:100%;margin-bottom:1rem">
      <input type="search" name="q" class="form-control" placeholder="Search question, answer, session, user…" value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
      <?php if ($search !== ''): ?>
      <a href="<?= adminUrl('chat_history.php') ?>" class="btn-cancel" style="padding:0.5rem 0.75rem;font-size:0.8rem">Clear</a>
      <?php endif; ?>
    </form>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Question</th>
            <th>Answer</th>
            <th>Source</th>
            <th>Session</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No chat history yet. Test the widget from the homepage or Chatbot page.</td></tr>
          <?php else: ?>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td style="white-space:nowrap;font-size:0.78rem"><?= htmlspecialchars((string) $row['created_at']) ?></td>
            <td style="font-size:0.82rem">
              <?php if (!empty($row['full_name'])): ?>
              <?= htmlspecialchars((string) $row['full_name']) ?><br>
              <span style="color:var(--cyber-muted)"><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></span>
              <?php else: ?>
              <span style="color:var(--cyber-muted)">Guest</span>
              <?php endif; ?>
            </td>
            <td style="max-width:220px"><?= htmlspecialchars(mb_strimwidth((string) $row['question'], 0, 180, '…')) ?></td>
            <td style="max-width:260px"><?= htmlspecialchars(mb_strimwidth((string) $row['answer'], 0, 220, '…')) ?></td>
            <td>
              <?php
              $source = (string) ($row['source'] ?? 'offline');
              $badge = match ($source) {
                  'qa' => 'badge-paid',
                  'ai' => 'badge-refunded',
                  default => 'badge-pending',
              };
              ?>
              <span class="badge-status <?= $badge ?>"><?= htmlspecialchars(strtoupper($source)) ?></span>
            </td>
            <td><code style="font-size:0.72rem"><?= htmlspecialchars(substr((string) $row['session_id'], 0, 10)) ?>…</code></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this entry?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <button type="submit" class="btn-sm-cyber btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php renderAdminPagination($page, $totalPages, $total, 'chat_history.php', $search !== '' ? ['q' => $search] : []); ?>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
