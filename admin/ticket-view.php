<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/support-tickets.php';
requireAdminLogin();
ensureSupportTicketSchema();

$id = (int) ($_GET['id'] ?? 0);
$ticket = $id > 0 ? getTicketById($id) : null;
if (!$ticket) {
    redirectWith('admin/tickets.php', 'error', 'Ticket not found.');
}

$teams = getSupportTeams();
$admins = db()->fetchAll('SELECT id, full_name, username FROM admin ORDER BY full_name');
$aid = (int) $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    if (($_POST['action'] ?? '') === 'reply') {
        $msg = trim($_POST['message'] ?? '');
        if (strlen($msg) >= 2) {
            addTicketReply($id, 'admin', $msg, null, $aid, !empty($_POST['is_internal']));
            if (empty($_POST['is_internal'])) {
                updateTicketStatus($id, 'waiting_customer');
            }
            redirectWith('admin/ticket-view.php?id=' . $id, 'success', 'Reply sent.');
        }
    }
    if (($_POST['action'] ?? '') === 'update') {
        updateTicketStatus($id, $_POST['status'] ?? $ticket['status']);
        updateTicketPriority($id, $_POST['priority'] ?? $ticket['priority']);
        assignTicket($id, (int) ($_POST['team_id'] ?? 0) ?: null, (int) ($_POST['admin_id'] ?? 0) ?: null);
        redirectWith('admin/ticket-view.php?id=' . $id, 'success', 'Updated.');
    }
}

$replies = getTicketReplies($id, true);
$ticket = getTicketById($id);

renderAdminPageStart('Ticket ' . $ticket['ticket_no'], 'tickets', 'fa-headset');
echo showFlash();
?>

<p class="mb-3"><a href="<?= adminUrl('tickets.php') ?>" class="btn-sm-link"><i class="fas fa-arrow-left me-1"></i>Back to tickets</a></p>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="section-card">
      <div class="section-card-header">Ticket details</div>
      <div class="section-card-body"<?= renderAdminRecordRowAttrs('support_ticket', (int) $ticket['id'], adminRecordIsBlocked($ticket)) ?>>
        <p class="mb-3"><?php renderAdminRecordActions('support_ticket', (int) $ticket['id'], adminRecordIsBlocked($ticket)); ?></p>
        <p class="mb-2"><?= renderStatusBadge($ticket['status']) ?> <?= renderPriorityBadge($ticket['priority']) ?></p>
        <p><strong><?= htmlspecialchars($ticket['name']) ?></strong><br><span style="color:var(--cyber-muted);font-size:0.85rem"><?= htmlspecialchars($ticket['email']) ?></span></p>
        <p style="font-size:0.88rem;margin-top:1rem"><strong>Subject:</strong><br><?= htmlspecialchars($ticket['subject']) ?></p>
        <?php if ($ticket['description']): ?>
        <p class="msg-body mt-2"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
        <?php endif; ?>

        <form method="post" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <input type="hidden" name="action" value="update">
          <label class="form-label">Status</label>
          <select name="status" class="form-select mb-2">
            <?php foreach (supportTicketStatuses() as $k => $s): ?>
            <option value="<?= $k ?>"<?= $ticket['status'] === $k ? ' selected' : '' ?>><?= htmlspecialchars($s['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label">Priority</label>
          <select name="priority" class="form-select mb-2">
            <?php foreach (supportTicketPriorities() as $k => $p): ?>
            <option value="<?= $k ?>"<?= $ticket['priority'] === $k ? ' selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label">Team</label>
          <select name="team_id" class="form-select mb-2">
            <option value="0">— Unassigned —</option>
            <?php foreach ($teams as $tm): ?>
            <option value="<?= (int) $tm['id'] ?>"<?= (int) $ticket['assigned_team_id'] === (int) $tm['id'] ? ' selected' : '' ?>><?= htmlspecialchars($tm['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label">Agent</label>
          <select name="admin_id" class="form-select mb-2">
            <option value="0">— Unassigned —</option>
            <?php foreach ($admins as $a): ?>
            <option value="<?= (int) $a['id'] ?>"<?= (int) $ticket['assigned_admin_id'] === (int) $a['id'] ? ' selected' : '' ?>><?= htmlspecialchars($a['full_name'] ?: $a['username']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-submit w-100 mt-2">Save changes</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="section-card">
      <div class="section-card-header">Conversation</div>
      <div class="section-card-body">
        <?php if (!$replies): ?>
        <p style="color:var(--cyber-muted)">No replies yet.</p>
        <?php else: foreach ($replies as $r): ?>
        <div class="msg-body mb-2" style="background:<?= $r['sender_type'] === 'admin' ? 'rgba(0,255,136,0.06)' : 'rgba(0,212,255,0.06)' ?>">
          <div style="font-size:0.75rem;color:var(--cyber-muted);margin-bottom:0.35rem">
            <?= $r['sender_type'] === 'admin' ? 'Support' : 'Customer' ?><?= $r['is_internal'] ? ' (internal)' : '' ?>
            — <?= date('d M Y, h:i A', strtotime($r['created_at'])) ?>
          </div>
          <?= nl2br(htmlspecialchars($r['message'])) ?>
        </div>
        <?php endforeach; endif; ?>

        <form method="post" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <input type="hidden" name="action" value="reply">
          <label class="form-label">Reply</label>
          <textarea name="message" class="form-control mb-2" rows="4" required></textarea>
          <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;cursor:pointer">
            <input type="checkbox" name="is_internal" value="1"> Internal note (not visible to customer)
          </label>
          <button type="submit" class="btn-submit mt-2"><i class="fas fa-reply me-1"></i>Send reply</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
