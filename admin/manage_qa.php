<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/chatbot.php';
requireAdminLogin();

ensureChatbotSchema();

$flash = getFlash();
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$editId = (int) ($_GET['edit'] ?? 0);
$perPage = 25;
$total = (int) adminDb(fn () => countChatbotQaPairs($search), 0);
$totalPages = max(1, (int) ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$editRow = null;
if ($editId > 0) {
    $editRow = adminDb(fn () => db()->fetchOne('SELECT * FROM chatbot_data WHERE id = ? LIMIT 1', [$editId]), null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_qa') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = adminDb(
            fn () => saveChatbotQaPair($_POST, $id > 0 ? $id : null),
            ['success' => false, 'message' => 'Database error.']
        );
        adminFlashRedirect('manage_qa.php', $result['success'] ? 'success' : 'error', $result['message']);
    }

    if ($action === 'delete' && isset($_POST['id'])) {
        adminDb(fn () => deleteChatbotQaPair((int) $_POST['id']));
        adminFlashRedirect('manage_qa.php' . ($search !== '' ? '?q=' . rawurlencode($search) : ''), 'success', 'Q&A pair deleted.');
    }
}

$rows = adminDb(fn () => listChatbotQaPairs($page, $perPage, $search), []);

renderAdminPageStart('Manage Q&A', 'manage_qa', 'fa-question-circle');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="form-card">
  <div class="form-card-header">
    <i class="fas fa-<?= $editRow ? 'edit' : 'plus-circle' ?>" style="color:var(--cyber-accent)"></i>
    <?= $editRow ? 'Edit Q&A pair' : 'Add Q&A pair' ?>
  </div>
  <div class="form-card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="save_qa">
      <input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
      <div class="col-12">
        <label class="form-label">Question</label>
        <input type="text" name="question" class="form-control" required maxlength="500"
               value="<?= htmlspecialchars((string) ($editRow['question'] ?? '')) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Answer</label>
        <textarea name="answer" class="form-control" required rows="4"><?= htmlspecialchars((string) ($editRow['answer'] ?? '')) ?></textarea>
      </div>
      <div class="col-md-8">
        <label class="form-label">Keywords (comma-separated, optional)</label>
        <input type="text" name="keywords" class="form-control" maxlength="500"
               placeholder="webinar, register, payment"
               value="<?= htmlspecialchars((string) ($editRow['keywords'] ?? '')) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Sort order</label>
        <input type="number" name="sort_order" class="form-control" min="0" max="9999"
               value="<?= (int) ($editRow['sort_order'] ?? 0) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Active</label>
        <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.65rem;cursor:pointer">
          <input type="checkbox" name="is_active" value="1" style="accent-color:var(--cyber-accent)"
            <?= !isset($editRow['is_active']) || (int) ($editRow['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
          <span style="font-size:0.85rem">Enabled</span>
        </label>
      </div>
      <div class="col-12" style="display:flex;gap:0.75rem;flex-wrap:wrap">
        <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i> Save</button>
        <?php if ($editRow): ?>
        <a href="<?= adminUrl('manage_qa.php') ?>" class="btn-cancel">Cancel edit</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="section-card">
  <div class="section-card-header" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
    <span>Q&amp;A library (<?= number_format($total) ?>)</span>
    <a href="<?= adminUrl('chat_history.php') ?>" class="btn-sm-link">View chat history →</a>
  </div>
  <div class="section-card-body">
    <form method="get" class="admin-search-form" style="max-width:100%;margin-bottom:1rem">
      <input type="search" name="q" class="form-control" placeholder="Search questions, answers, keywords…" value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
      <?php if ($search !== ''): ?>
      <a href="<?= adminUrl('manage_qa.php') ?>" class="btn-cancel" style="padding:0.5rem 0.75rem;font-size:0.8rem">Clear</a>
      <?php endif; ?>
    </form>

    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Question</th>
            <th>Answer</th>
            <th>Keywords</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []): ?>
          <tr><td colspan="6" style="color:var(--cyber-muted)">No Q&amp;A pairs yet. Add your first question above.</td></tr>
          <?php else: ?>
          <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= (int) $row['id'] ?></td>
            <td style="max-width:220px"><?= htmlspecialchars((string) $row['question']) ?></td>
            <td style="max-width:280px"><?= htmlspecialchars(mb_strimwidth((string) $row['answer'], 0, 160, '…')) ?></td>
            <td style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars((string) ($row['keywords'] ?? '—')) ?></td>
            <td>
              <?php if ((int) ($row['is_active'] ?? 0) === 1): ?>
              <span class="badge-status badge-paid">Active</span>
              <?php else: ?>
              <span class="badge-status badge-blocked">Inactive</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="admin-action-btns" style="display:flex">
                <a href="<?= adminUrl('manage_qa.php?edit=' . (int) $row['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-edit"></i></a>
                <form method="post" onsubmit="return confirm('Delete this Q&A pair?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                  <button type="submit" class="btn-sm-cyber btn-delete"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php renderAdminPagination($page, $totalPages, $total, 'manage_qa.php', $search !== '' ? ['q' => $search] : []); ?>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
