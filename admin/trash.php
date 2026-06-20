<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$sort = adminParseListSortParam();
$trashItems = adminDb(fn () => adminListTrashedItems(100, $sort), []);

renderAdminPageStart('Trash', 'trash', 'fa-trash-restore');
?>

<div class="section-card">
  <div class="section-card-header">
    Recycle bin (<?= count($trashItems) ?>)
    <span style="font-size:0.78rem;color:var(--cyber-muted);font-weight:400;margin-left:0.5rem">Restore items or delete them permanently</span>
  </div>
  <div class="section-card-body">
    <?php renderAdminSortBar('admin/trash.php', $sort); ?>

    <?php if ($trashItems): ?>
    <div class="admin-trash-bulk-bar" id="adminTrashBulkBar">
      <button type="button" class="btn-search" id="adminTrashSelectAllBtn">
        <i class="fas fa-check-double me-1"></i>Select all
      </button>
      <button type="button" class="btn-cancel" id="adminTrashDeselectAllBtn" style="padding:0.45rem 0.85rem;font-size:0.82rem" hidden>
        Deselect all
      </button>
      <span class="bulk-count" id="adminTrashSelectedCount"><strong>0</strong> selected</span>
      <button type="button" class="btn-sm-cyber btn-restore" id="adminTrashBulkRestoreBtn" disabled>
        <i class="fas fa-undo me-1"></i>Restore selected
      </button>
      <button type="button" class="btn-sm-cyber btn-delete" id="adminTrashBulkPurgeBtn" disabled>
        <i class="fas fa-trash me-1"></i>Delete forever
      </button>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="data-table" id="adminTrashTable">
        <thead>
          <tr>
            <?php if ($trashItems): ?>
            <th style="width:42px">
              <input type="checkbox" class="admin-trash-select-cb" id="adminTrashSelectAllCb" aria-label="Select all items" title="Select all">
            </th>
            <?php endif; ?>
            <th>Deleted</th><th>Type</th><th>Item</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$trashItems): ?>
          <tr><td colspan="4" style="color:var(--cyber-muted);text-align:center;padding:2rem">Trash is empty.</td></tr>
          <?php else: foreach ($trashItems as $item): ?>
          <tr data-admin-row="1" data-admin-entity="<?= htmlspecialchars($item['entity']) ?>" data-admin-id="<?= (int) $item['id'] ?>" data-admin-trash="1">
            <td>
              <input type="checkbox" class="admin-trash-select-cb admin-trash-row-cb"
                data-admin-entity="<?= htmlspecialchars($item['entity']) ?>"
                data-admin-id="<?= (int) $item['id'] ?>"
                aria-label="Select <?= htmlspecialchars($item['title']) ?>">
            </td>
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

<?php renderAdminPageEnd(['assets/js/admin-trash-bulk.js']); ?>
