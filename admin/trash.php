<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$trashItems = adminDb(fn () => adminListTrashedItems(100), []);

renderAdminPageStart('Trash', 'trash', 'fa-trash-restore');
?>

<div class="section-card">
  <div class="section-card-header">
    Recycle bin (<?= count($trashItems) ?>)
    <span style="font-size:0.78rem;color:var(--cyber-muted);font-weight:400;margin-left:0.5rem">Restore items or delete them permanently</span>
  </div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Deleted</th><th>Type</th><th>Item</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$trashItems): ?>
          <tr><td colspan="4" style="color:var(--cyber-muted);text-align:center;padding:2rem">Trash is empty.</td></tr>
          <?php else: foreach ($trashItems as $item): ?>
          <tr data-admin-row="1" data-admin-entity="<?= htmlspecialchars($item['entity']) ?>" data-admin-id="<?= (int) $item['id'] ?>" data-admin-trash="1">
            <td style="font-size:0.8rem;white-space:nowrap"><?= date('d M Y, h:i A', strtotime($item['deleted_at'])) ?></td>
            <td><span class="badge-status badge-pending"><?= htmlspecialchars($item['type_label']) ?></span></td>
            <td><?= htmlspecialchars($item['title']) ?></td>
            <td><?php renderAdminRecordActions($item['entity'], (int) $item['id'], false, true); ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
