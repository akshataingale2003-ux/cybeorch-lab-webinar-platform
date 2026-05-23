<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/support-tickets.php';
requireAdminLogin();
ensureSupportTicketSchema();

$tickets = db()->fetchAll(
    "SELECT t.*, c.name category_name, tm.name team_name
     FROM support_tickets t
     JOIN support_ticket_categories c ON c.id = t.category_id
     LEFT JOIN support_teams tm ON tm.id = t.assigned_team_id
     WHERE " . adminSqlActive('t') . "
     ORDER BY t.updated_at DESC LIMIT 100"
);

renderAdminPageStart('Support Tickets', 'tickets', 'fa-headset');
echo showFlash();
?>

<div class="section-card">
  <div class="section-card-header">All tickets (<?= count($tickets) ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Ticket</th><th>Customer</th><th>Category</th><th>Priority</th><th>Status</th><th>Team</th><th>Updated</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$tickets): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No support tickets yet.</td></tr>
          <?php else: foreach ($tickets as $t):
            $blocked = adminRecordIsBlocked($t);
          ?>
          <tr<?= renderAdminRecordRowAttrs('support_ticket', (int) $t['id'], $blocked) ?>>
            <td><strong style="color:var(--cyber-accent)"><?= htmlspecialchars($t['ticket_no']) ?></strong></td>
            <td><?= htmlspecialchars($t['name']) ?><br><span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($t['email']) ?></span></td>
            <td><?= htmlspecialchars($t['category_name']) ?></td>
            <td><?= renderPriorityBadge($t['priority']) ?></td>
            <td><?= renderStatusBadge($t['status']) ?></td>
            <td><?= htmlspecialchars($t['team_name'] ?? '—') ?></td>
            <td style="font-size:0.8rem;color:var(--cyber-muted)"><?= date('d M Y H:i', strtotime($t['updated_at'])) ?></td>
            <td>
              <a href="<?= url('admin/ticket-view.php?id=' . (int) $t['id']) ?>" class="btn-sm-link">Manage</a>
              <?php renderAdminRecordActions('support_ticket', (int) $t['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
