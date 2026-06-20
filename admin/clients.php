<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';
requireAdminLogin();

ensureInvoiceAmcSchema();

$flash = '';
$flashType = 'success';
$viewId = (int) ($_GET['view'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);
$searchQ = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $postAction = sanitize($_POST['action'] ?? '');
    $clientId = (int) ($_POST['client_id'] ?? 0);
    if ($postAction === 'update_client' && $clientId > 0) {
        $result = invoiceAmcUpdateClient($clientId, $_POST);
        if ($result['ok']) {
            header('Location: ' . adminUrl('clients.php?view=' . $clientId . '&saved=1'));
            exit;
        }
        $flash = $result['error'] ?? 'Could not update client.';
        $flashType = 'danger';
        $editId = $clientId;
    }
}

$clients = invoiceAmcListClients($searchQ);
$viewRow = $viewId > 0 ? invoiceAmcGetClient($viewId) : null;
$editRow = $editId > 0 ? invoiceAmcGetClient($editId) : null;
$viewInvoices = $viewRow ? invoiceAmcListClientInvoices($viewId) : [];
$viewAmc = $viewRow ? invoiceAmcListClientAmc($viewId) : [];

if (!empty($_GET['saved'])) {
    $flash = 'Client updated.';
}

renderAdminPageStart('Client Management', 'clients', 'fa-building');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType === 'danger' ? 'danger' : 'success' ?>" style="margin-bottom:1rem"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<?php if ($viewRow && empty($viewRow['deleted_at'])): ?>
<?php $viewBlocked = adminRecordIsBlocked($viewRow); ?>
<div class="form-card mb-4">
  <div class="form-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View Client — <?= htmlspecialchars($viewRow['client_code']) ?></span>
    <div class="d-flex flex-wrap gap-1 align-items-center">
      <a href="<?= adminUrl('clients.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i>Edit</a>
      <a href="<?= adminUrl('clients.php') ?>" class="btn-cancel">Back to list</a>
    </div>
  </div>
  <div class="form-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Client ID</label><div style="font-family:monospace"><?= htmlspecialchars($viewRow['client_code']) ?></div></div>
      <div class="detail-item"><label>Name</label><div><?= htmlspecialchars($viewRow['name']) ?></div></div>
      <div class="detail-item"><label>Email</label><div><?= htmlspecialchars($viewRow['email']) ?></div></div>
      <div class="detail-item"><label>Phone</label><div><?= htmlspecialchars($viewRow['phone'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Company</label><div><?= htmlspecialchars($viewRow['company'] ?: '—') ?></div></div>
      <div class="detail-item"><label>GST Number</label><div><?= htmlspecialchars($viewRow['gst_number'] ?: '—') ?></div></div>
      <div class="detail-item"><label>Status</label><div>
        <?php if ($viewBlocked): ?>
        <span class="badge-status badge-blocked">BLOCKED</span>
        <?php else: ?>
        <span class="badge-status badge-paid">ACTIVE</span>
        <?php endif; ?>
      </div></div>
      <div class="detail-item"><label>Created</label><div><?= date('d M Y', strtotime($viewRow['created_at'])) ?></div></div>
    </div>
    <?php if (!empty($viewRow['address'])): ?>
    <div class="detail-item mt-3"><label>Address</label><div><?= nl2br(htmlspecialchars($viewRow['address'])) ?></div></div>
    <?php endif; ?>
    <?php if (!empty($viewRow['notes'])): ?>
    <div class="detail-item mt-3"><label>Notes</label><div><?= nl2br(htmlspecialchars($viewRow['notes'])) ?></div></div>
    <?php endif; ?>

    <h4 style="font-family:'Rajdhani',sans-serif;margin:1.5rem 0 0.75rem;font-size:1rem;color:var(--cyber-accent)">Invoices (<?= count($viewInvoices) ?>)</h4>
    <?php if (!$viewInvoices): ?>
    <p style="color:var(--cyber-muted);font-size:0.85rem">No invoices for this client yet.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Invoice No</th><th>Date</th><th>Project</th><th>Total</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($viewInvoices as $inv): ?>
          <tr>
            <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($inv['invoice_no']) ?></td>
            <td><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
            <td><?= htmlspecialchars($inv['project_name']) ?></td>
            <td><?= invoiceAmcFormatMoney((float) $inv['total_amount'], (string) $inv['currency']) ?></td>
            <td><span class="badge-status badge-<?= $inv['status'] === 'paid' ? 'paid' : 'pending' ?>"><?= strtoupper($inv['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('invoices.php?view=' . (int) $inv['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-eye"></i><span>View</span></a>
              <?php if (!empty($inv['pdf_path'])): ?>
              <a href="<?= url('api/invoice-pdf.php?id=' . (int) $inv['id']) ?>" target="_blank" rel="noopener" class="btn-sm-cyber btn-edit"><i class="fas fa-file-pdf"></i><span>PDF</span></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <h4 style="font-family:'Rajdhani',sans-serif;margin:1.5rem 0 0.75rem;font-size:1rem;color:var(--cyber-accent)">AMC Contracts (<?= count($viewAmc) ?>)</h4>
    <?php if (!$viewAmc): ?>
    <p style="color:var(--cyber-muted);font-size:0.85rem">No AMC contracts for this client yet.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>AMC No</th><th>Project</th><th>End Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($viewAmc as $a): ?>
          <tr>
            <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($a['amc_no']) ?></td>
            <td><?= htmlspecialchars($a['project_name']) ?></td>
            <td><?= date('d M Y', strtotime($a['end_date'])) ?></td>
            <td><?= invoiceAmcFormatMoney((float) $a['amount'], (string) $a['currency']) ?></td>
            <td><span class="badge-status badge-<?= $a['status'] === 'active' ? 'paid' : 'pending' ?>"><?= strtoupper($a['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('amc.php?view=' . (int) $a['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-eye"></i><span>View</span></a>
              <?php if (!empty($a['pdf_path'])): ?>
              <a href="<?= url('api/amc-pdf.php?id=' . (int) $a['id']) ?>" target="_blank" rel="noopener" class="btn-sm-cyber btn-edit"><i class="fas fa-file-pdf"></i><span>PDF</span></a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($editRow && empty($editRow['deleted_at']) && !$viewId): ?>
<div class="form-card mb-4">
  <div class="form-card-header"><i class="fas fa-pen" style="color:var(--cyber-accent)"></i> Edit Client</div>
  <div class="form-card-body">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="update_client">
      <input type="hidden" name="client_id" value="<?= (int) $editRow['id'] ?>">
      <div class="detail-grid mb-3">
        <div><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($editRow['name']) ?>" required></div>
        <div><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editRow['email']) ?>" required></div>
        <div><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editRow['phone'] ?? '') ?>"></div>
        <div><label class="form-label">Company</label><input type="text" name="company" class="form-control" value="<?= htmlspecialchars($editRow['company'] ?? '') ?>"></div>
        <div><label class="form-label">GST Number</label><input type="text" name="gst_number" class="form-control" value="<?= htmlspecialchars($editRow['gst_number'] ?? '') ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($editRow['address'] ?? '') ?></textarea></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea></div>
      <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Update Client</button>
      <a href="<?= adminUrl('clients.php?view=' . (int) $editRow['id']) ?>" class="btn-cancel">Cancel</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!$viewId && !$editId): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-list" style="color:var(--cyber-accent)"></i>All Clients (<?= count($clients) ?>)</div>
  <div class="form-card-body">
    <form method="get" class="mb-3" style="display:flex;gap:0.5rem;flex-wrap:wrap">
      <input type="text" name="q" class="form-control" placeholder="Search clients…" value="<?= htmlspecialchars($searchQ) ?>" style="max-width:280px">
      <button type="submit" class="btn-sm-cyber btn-edit">Search</button>
    </form>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Client ID</th><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (!$clients): ?>
          <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--cyber-muted)">No clients yet. Create one when issuing an invoice or AMC.</td></tr>
          <?php else: foreach ($clients as $c):
            $blocked = adminRecordIsBlocked($c);
          ?>
          <tr<?= renderAdminRecordRowAttrs('client', (int) $c['id'], $blocked) ?>>
            <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($c['client_code']) ?></td>
            <td><?= htmlspecialchars($c['name']) ?></td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td><?= htmlspecialchars($c['company'] ?: '—') ?></td>
            <td>
              <?php if ($blocked): ?>
              <span class="badge-status badge-blocked">BLOCKED</span>
              <?php else: ?>
              <span class="badge-status badge-paid">ACTIVE</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <?php renderAdminEntityRowActions(
                  'client',
                  (int) $c['id'],
                  adminUrl('clients.php?view=' . (int) $c['id']),
                  adminUrl('clients.php?edit=' . (int) $c['id']),
                  $blocked
              ); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>
<?php renderAdminPageEnd(); ?>
