<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';
require_once __DIR__ . '/../includes/invoice-pdf.php';
require_once __DIR__ . '/../includes/invoice-mailer.php';
requireAdminLogin();

ensureInvoiceAmcSchema();
invoiceAmcProcessRenewalReminders();

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$flash = '';
$flashType = 'success';
$action = sanitize($_GET['action'] ?? '');
$viewId = (int) ($_GET['view'] ?? 0);
$editId = (int) ($_GET['edit'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? 'all');
$searchQ = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $postAction = sanitize($_POST['action'] ?? '');
    if ($postAction === 'create_invoice') {
        $result = invoiceAmcCreateInvoice($_POST, $adminId);
        if ($result['ok']) {
            $flash = invoiceAmcInvoiceCreateFlashMessage($result);
            setFlash($flash['type'], $flash['message']);
            header('Location: ' . adminUrl('invoices.php?view=' . (int) $result['id']));
            exit;
        }
        $flash = $result['error'] ?? 'Could not create invoice.';
        $flashType = 'danger';
        $action = 'create';
    } elseif ($postAction === 'mark_paid') {
        $invId = (int) ($_POST['invoice_id'] ?? 0);
        $result = invoiceAmcMarkInvoicePaid($invId, $_POST);
        if ($result['ok']) {
            header('Location: ' . adminUrl('invoices.php?view=' . $invId . '&paid=1'));
            exit;
        }
        $flash = $result['error'] ?? 'Could not mark as paid.';
        $flashType = 'danger';
    } elseif ($postAction === 'resend_email') {
        $invId = (int) ($_POST['invoice_id'] ?? 0);
        $inv = invoiceAmcGetInvoice($invId);
        if (!$inv) {
            $flash = 'Invoice not found.';
            $flashType = 'danger';
        } else {
            $pdfResult = invoiceAmcEnsureInvoicePdf($invId);
            if (empty($pdfResult['ok']) || empty($pdfResult['path'])) {
                $flash = 'PDF not found. ' . ($pdfResult['error'] ?? 'Could not generate PDF.');
                $flashType = 'danger';
            } else {
                $client = invoiceAmcGetClient((int) $inv['client_id']);
                $r = invoiceMailerSendInvoiceCreated($invId, $client ?? [], (string) $pdfResult['path']);
                $flash = $r['ok'] ? 'Invoice email sent.' : ($r['error'] ?? 'Email failed.');
                $flashType = $r['ok'] ? 'success' : 'danger';
            }
        }
    } elseif ($postAction === 'update_invoice') {
        $invId = (int) ($_POST['invoice_id'] ?? 0);
        $result = invoiceAmcUpdateInvoice($invId, $_POST);
        if ($result['ok']) {
            header('Location: ' . adminUrl('invoices.php?view=' . $invId . '&saved=1'));
            exit;
        }
        $flash = $result['error'] ?? 'Could not update invoice.';
        $flashType = 'danger';
        $editId = $invId;
    }
}

$viewRow = $viewId > 0 ? invoiceAmcGetInvoice($viewId) : null;
$editRow = $editId > 0 ? invoiceAmcGetInvoice($editId) : null;
$viewItems = $viewRow ? invoiceAmcGetInvoiceItems($viewId) : [];
$invoices = invoiceAmcListInvoices(['status' => $statusFilter, 'q' => $searchQ]);
$clients = array_values(array_filter(invoiceAmcListClients(), static fn ($c) => !adminRecordIsBlocked($c)));
$stats = invoiceAmcDashboardStats();
$defaultItems = invoiceAmcDefaultLineItems();

$sessionFlash = getFlash();
if ($sessionFlash) {
    $flash = (string) $sessionFlash['message'];
    $flashType = match ($sessionFlash['type']) {
        'error', 'danger' => 'danger',
        'warning'         => 'warning',
        default           => 'success',
    };
}

if (!empty($_GET['paid'])) {
    $flash = 'Invoice marked as paid. Confirmation email sent.';
}
if (!empty($_GET['saved'])) {
    $flash = 'Invoice updated.';
}

renderAdminPageStart('Invoice Management', 'invoices', 'fa-file-invoice-dollar');
?>
<style>
.invoice-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}
.line-item-row{display:grid;grid-template-columns:1fr 140px 40px;gap:0.5rem;align-items:center;margin-bottom:0.5rem}
.totals-box{background:rgba(0,212,255,0.06);border:1px solid var(--cyber-border);border-radius:8px;padding:1rem;max-width:360px;margin-left:auto}
.client-mode-toggle{display:flex;gap:0.75rem;margin-bottom:1rem}
.client-mode-toggle label{display:flex;align-items:center;gap:0.35rem;cursor:pointer;font-size:0.85rem}
#new-client-fields{display:none}
</style>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType === 'danger' ? 'danger' : ($flashType === 'warning' ? 'warning' : 'success') ?>" style="margin-bottom:1rem"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card blue"><div class="stat-value" style="font-size:1.6rem"><?= $stats['total_clients'] ?></div><div class="stat-label">Total Clients</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card green"><div class="stat-value" style="font-size:1.6rem"><?= $stats['total_invoices'] ?></div><div class="stat-label">Total Invoices</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card orange"><div class="stat-value" style="font-size:1.6rem"><?= $stats['pending_invoices'] ?></div><div class="stat-label">Pending</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card green"><div class="stat-value" style="font-size:1.4rem"><?= invoiceAmcFormatMoney($stats['total_revenue'], 'USD') ?></div><div class="stat-label">Paid Revenue</div></div></div>
</div>

<?php if ($viewRow): ?>
<div class="section-card mb-4">
  <div class="section-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View Invoice — <?= htmlspecialchars($viewRow['invoice_no']) ?></span>
    <div class="d-flex flex-wrap gap-1 align-items-center">
      <span class="badge-status badge-<?= $viewRow['status'] === 'paid' ? 'paid' : 'pending' ?>"><?= strtoupper($viewRow['status']) ?></span>
      <a href="<?= adminUrl('invoices.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i>Edit</a>
      <a href="<?= url('api/invoice-pdf.php?id=' . (int) $viewRow['id']) ?>" target="_blank" rel="noopener" class="btn-sm-cyber btn-edit"><i class="fas fa-file-pdf"></i><span>PDF</span></a>
      <a href="<?= adminUrl('invoices.php') ?>" class="btn-cancel">Back to list</a>
    </div>
  </div>
  <div class="section-card-body" style="padding:1.25rem">
    <div class="detail-grid">
      <div class="detail-item"><label>Client</label><span><?= htmlspecialchars($viewRow['client_name']) ?> (<?= htmlspecialchars($viewRow['client_code']) ?>)</span></div>
      <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($viewRow['client_email']) ?></span></div>
      <div class="detail-item"><label>Project</label><span><?= htmlspecialchars($viewRow['project_name']) ?></span></div>
      <div class="detail-item"><label>Type</label><span><?= htmlspecialchars($viewRow['project_type']) ?></span></div>
      <div class="detail-item"><label>Invoice Date</label><span><?= date('d M Y', strtotime($viewRow['invoice_date'])) ?></span></div>
      <div class="detail-item"><label>Currency</label><span><?= htmlspecialchars($viewRow['currency']) ?></span></div>
      <div class="detail-item"><label>Total</label><span style="font-weight:700;color:var(--cyber-green)"><?= invoiceAmcFormatMoney((float) $viewRow['total_amount'], (string) $viewRow['currency']) ?></span></div>
      <?php if (!empty($viewRow['paid_at'])): ?>
      <div class="detail-item"><label>Paid At</label><span><?= date('d M Y H:i', strtotime($viewRow['paid_at'])) ?></span></div>
      <?php endif; ?>
      <div class="detail-item"><label>Payment Terms</label><span><?= htmlspecialchars($viewRow['payment_terms'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Validity</label><span><?= (int) $viewRow['validity_days'] ?> days</span></div>
      <div class="detail-item"><label>Payment Methods</label><span><?= htmlspecialchars($viewRow['payment_methods'] ?: '—') ?></span></div>
    </div>
    <?php if (!empty($viewRow['notes'])): ?>
    <div class="detail-item mt-3"><label>Notes</label><div><?= nl2br(htmlspecialchars($viewRow['notes'])) ?></div></div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($editRow && empty($editRow['deleted_at']) && !$viewId): ?>
<div class="section-card mb-4">
  <div class="section-card-header"><i class="fas fa-pen" style="color:var(--cyber-accent)"></i> Edit Invoice — <?= htmlspecialchars($editRow['invoice_no']) ?></div>
  <div class="section-card-body" style="padding:1.25rem">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="update_invoice">
      <input type="hidden" name="invoice_id" value="<?= (int) $editRow['id'] ?>">
      <div class="invoice-form-grid mb-3">
        <div><label class="form-label">Project Name *</label><input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($editRow['project_name']) ?>" required></div>
        <div><label class="form-label">Project Type</label>
          <select name="project_type" class="form-control">
            <?php foreach (invoiceProjectTypeOptions() as $k => $v): ?>
            <option value="<?= htmlspecialchars($k) ?>"<?= $editRow['project_type'] === $k ? ' selected' : '' ?>><?= htmlspecialchars($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">Status</label>
          <select name="status" class="form-control"<?= $editRow['status'] === 'paid' ? ' disabled' : '' ?>>
            <?php foreach (['pending', 'paid', 'cancelled'] as $st): ?>
            <option value="<?= $st ?>"<?= $editRow['status'] === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($editRow['status'] === 'paid'): ?><input type="hidden" name="status" value="paid"><?php endif; ?>
        </div>
        <div><label class="form-label">Validity (days)</label><input type="number" name="validity_days" class="form-control" min="1" value="<?= (int) $editRow['validity_days'] ?>"></div>
      </div>
      <div class="mb-3"><label class="form-label">Payment Terms</label><input type="text" name="payment_terms" class="form-control" value="<?= htmlspecialchars($editRow['payment_terms'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Payment Methods</label><input type="text" name="payment_methods" class="form-control" value="<?= htmlspecialchars($editRow['payment_methods'] ?? '') ?>"></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea></div>
      <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Update Invoice</button>
      <a href="<?= adminUrl('invoices.php?view=' . (int) $editRow['id']) ?>" class="btn-cancel">Cancel</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'create' && !$viewId && !$editId): ?>
<div class="section-card mb-4">
  <div class="section-card-header">Create Invoice</div>
  <div class="section-card-body" style="padding:1.25rem">
    <form method="post" id="invoice-create-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="create_invoice">

      <div class="client-mode-toggle">
        <label><input type="radio" name="client_mode" value="existing" checked> Existing Client</label>
        <label><input type="radio" name="client_mode" value="new"> New Client</label>
      </div>

      <div id="existing-client-fields" class="mb-3">
        <label class="form-label">Select Client</label>
        <select name="client_id" id="client-select" class="form-control">
          <option value="">— Select —</option>
          <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>"
            data-name="<?= htmlspecialchars($c['name']) ?>"
            data-email="<?= htmlspecialchars($c['email']) ?>"
            data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>"
            data-company="<?= htmlspecialchars($c['company'] ?? '') ?>">
            <?= htmlspecialchars($c['client_code'] . ' — ' . $c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div id="client-preview" style="font-size:0.82rem;color:var(--cyber-muted);margin-top:0.5rem"></div>
      </div>

      <div id="new-client-fields">
        <div class="invoice-form-grid mb-3">
          <div><label class="form-label">Name *</label><input type="text" name="name" class="form-control"></div>
          <div><label class="form-label">Email *</label><input type="email" name="email" class="form-control"></div>
          <div><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
          <div><label class="form-label">Company</label><input type="text" name="company" class="form-control"></div>
        </div>
      </div>

      <div class="invoice-form-grid mb-3">
        <div><label class="form-label">Invoice Date</label><input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div><label class="form-label">Project Type</label>
          <select name="project_type" class="form-control">
            <?php foreach (invoiceProjectTypeOptions() as $k => $v): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">Project Name *</label><input type="text" name="project_name" class="form-control" required></div>
        <div><label class="form-label">Currency</label>
          <select name="currency" id="currency-select" class="form-control">
            <?php foreach (invoiceCurrencyOptions() as $k => $v): ?>
            <option value="<?= $k ?>"<?= $k === 'USD' ? ' selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label class="form-label">Line Items</label>
      <div id="line-items">
        <?php foreach ($defaultItems as $item): ?>
        <div class="line-item-row">
          <input type="text" name="item_description[]" class="form-control" value="<?= htmlspecialchars($item) ?>">
          <input type="number" name="item_amount[]" class="form-control item-amount" step="0.01" min="0" value="0">
          <button type="button" class="btn btn-sm btn-outline-danger remove-line" title="Remove">&times;</button>
        </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="btn btn-sm btn-outline-info mb-3" id="add-line-item">+ Add Line Item</button>

      <div class="invoice-form-grid mb-3">
        <div><label class="form-label">Discount</label><input type="number" name="discount" id="discount" class="form-control" step="0.01" min="0" value="0"></div>
        <div><label class="form-label">Tax Rate (%)</label><input type="number" name="tax_rate" id="tax-rate" class="form-control" step="0.01" min="0" value="0"></div>
      </div>

      <div class="totals-box mb-3">
        <div style="display:flex;justify-content:space-between;margin-bottom:0.35rem"><span>Subtotal</span><strong id="calc-subtotal">$0.00</strong></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.35rem"><span>Discount</span><strong id="calc-discount">$0.00</strong></div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.35rem"><span>Tax</span><strong id="calc-tax">$0.00</strong></div>
        <div style="display:flex;justify-content:space-between;font-size:1.1rem;color:var(--cyber-accent)"><span>Total</span><strong id="calc-total">$0.00</strong></div>
      </div>

      <div class="invoice-form-grid mb-3">
        <div><label class="form-label">Payment Terms</label><input type="text" name="payment_terms" class="form-control" value="50% Advance | 30% Milestone | 20% On Delivery"></div>
        <div><label class="form-label">Validity (days)</label><input type="number" name="validity_days" class="form-control" value="30" min="1"></div>
      </div>
      <div class="mb-3"><label class="form-label">Payment Methods</label><input type="text" name="payment_methods" class="form-control" value="Bank / Crypto / USDT / Wire Transfer (As per agreement)"></div>

      <button type="submit" class="btn-primary-cyber"><i class="fas fa-file-invoice"></i> Create Invoice</button>
      <a href="<?= adminUrl('invoices.php') ?>" class="btn-cancel ms-2">Cancel</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($viewRow): ?>
<div class="section-card mb-4">
  <div class="section-card-header">Line Items</div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($viewItems as $it): ?>
        <tr>
          <td><?= htmlspecialchars($it['description']) ?></td>
          <td style="text-align:right"><?= invoiceAmcFormatMoney((float) $it['amount'], (string) $viewRow['currency']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td style="text-align:right;font-weight:700">Subtotal</td><td style="text-align:right"><?= invoiceAmcFormatMoney((float) $viewRow['subtotal'], (string) $viewRow['currency']) ?></td></tr>
        <?php if ((float) $viewRow['discount'] > 0): ?>
        <tr><td style="text-align:right;font-weight:700">Discount</td><td style="text-align:right">-<?= invoiceAmcFormatMoney((float) $viewRow['discount'], (string) $viewRow['currency']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float) $viewRow['tax_amount'] > 0): ?>
        <tr><td style="text-align:right;font-weight:700">Tax (<?= number_format((float) $viewRow['tax_rate'], 2) ?>%)</td><td style="text-align:right"><?= invoiceAmcFormatMoney((float) $viewRow['tax_amount'], (string) $viewRow['currency']) ?></td></tr>
        <?php endif; ?>
        <tr><td style="text-align:right;font-weight:700;color:var(--cyber-accent)">Total</td><td style="text-align:right;font-weight:700;color:var(--cyber-green)"><?= invoiceAmcFormatMoney((float) $viewRow['total_amount'], (string) $viewRow['currency']) ?></td></tr>
      </tbody>
    </table>
  </div>
  <div style="padding:1rem 1.25rem;display:flex;flex-wrap:wrap;gap:0.5rem">
    <a href="<?= url('api/invoice-pdf.php?id=' . (int) $viewRow['id']) ?>" target="_blank" rel="noopener" class="btn-sm-cyber btn-edit"><i class="fas fa-file-pdf"></i><span>PDF</span></a>
    <?php if ($viewRow['status'] !== 'paid'): ?>
    <form method="post" class="d-inline" onsubmit="return confirm('Mark this invoice as paid?')">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="mark_paid">
      <input type="hidden" name="invoice_id" value="<?= (int) $viewRow['id'] ?>">
      <button type="submit" class="btn-sm-cyber btn-edit"><i class="fas fa-check"></i><span>Mark Paid</span></button>
    </form>
    <?php endif; ?>
    <form method="post" class="d-inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="resend_email">
      <input type="hidden" name="invoice_id" value="<?= (int) $viewRow['id'] ?>">
      <button type="submit" class="btn-sm-cyber btn-edit"><i class="fas fa-envelope"></i><span>Resend Email</span></button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!$viewId && !$editId): ?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;justify-content:space-between;align-items:center">
    <span>All Invoices (<?= count($invoices) ?>)</span>
    <?php if ($action !== 'create' && !$viewId && !$editId): ?>
    <a href="<?= adminUrl('invoices.php?action=create') ?>" class="btn-primary-cyber btn-sm-cyber"><i class="fas fa-plus"></i> Create Invoice</a>
    <?php endif; ?>
  </div>
  <div class="section-card-body">
    <form method="get" class="mb-3" style="display:flex;gap:0.5rem;flex-wrap:wrap">
      <select name="status" class="form-control" style="max-width:160px">
        <option value="all"<?= $statusFilter === 'all' ? ' selected' : '' ?>>All statuses</option>
        <option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending</option>
        <option value="paid"<?= $statusFilter === 'paid' ? ' selected' : '' ?>>Paid</option>
        <option value="cancelled"<?= $statusFilter === 'cancelled' ? ' selected' : '' ?>>Cancelled</option>
      </select>
      <input type="text" name="q" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($searchQ) ?>" style="max-width:220px">
      <button type="submit" class="btn-sm-cyber btn-edit">Filter</button>
    </form>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Invoice No</th><th>Date</th><th>Client</th><th>Project</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (!$invoices): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No invoices yet. Click <strong>Create Invoice</strong> to generate one automatically.</td></tr>
          <?php else: foreach ($invoices as $inv):
            $blocked = adminRecordIsBlocked($inv);
          ?>
          <tr<?= renderAdminRecordRowAttrs('invoice', (int) $inv['id'], $blocked) ?>>
            <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($inv['invoice_no']) ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
            <td><?= htmlspecialchars($inv['client_name']) ?></td>
            <td><?= htmlspecialchars($inv['project_name']) ?></td>
            <td style="white-space:nowrap"><?= invoiceAmcFormatMoney((float) $inv['total_amount'], (string) $inv['currency']) ?></td>
            <td><span class="badge-status badge-<?= $inv['status'] === 'paid' ? 'paid' : 'pending' ?>"><?= strtoupper($inv['status']) ?></span></td>
            <td style="white-space:nowrap">
              <?php renderAdminEntityRowActions(
                  'invoice',
                  (int) $inv['id'],
                  adminUrl('invoices.php?view=' . (int) $inv['id']),
                  adminUrl('invoices.php?edit=' . (int) $inv['id']),
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

<script>
(function(){
  const sym = {USD:'$', INR:'₹', EUR:'€'};
  function currencySymbol(){ return sym[document.getElementById('currency-select')?.value] || '$'; }
  function fmt(n){ return currencySymbol() + Number(n).toFixed(2); }
  function recalc(){
    let sub = 0;
    document.querySelectorAll('.item-amount').forEach(el => { sub += parseFloat(el.value) || 0; });
    const disc = parseFloat(document.getElementById('discount')?.value) || 0;
    const taxRate = parseFloat(document.getElementById('tax-rate')?.value) || 0;
    const taxable = Math.max(0, sub - disc);
    const tax = taxable * (taxRate / 100);
    const total = taxable + tax;
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = fmt(v); };
    set('calc-subtotal', sub); set('calc-discount', disc); set('calc-tax', tax); set('calc-total', total);
  }
  document.getElementById('line-items')?.addEventListener('input', recalc);
  document.getElementById('discount')?.addEventListener('input', recalc);
  document.getElementById('tax-rate')?.addEventListener('input', recalc);
  document.getElementById('currency-select')?.addEventListener('change', recalc);
  document.getElementById('add-line-item')?.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'line-item-row';
    row.innerHTML = '<input type="text" name="item_description[]" class="form-control" placeholder="Description"><input type="number" name="item_amount[]" class="form-control item-amount" step="0.01" min="0" value="0"><button type="button" class="btn btn-sm btn-outline-danger remove-line">&times;</button>';
    document.getElementById('line-items').appendChild(row);
    row.querySelector('.item-amount').addEventListener('input', recalc);
    row.querySelector('.remove-line').addEventListener('click', () => { row.remove(); recalc(); });
  });
  document.querySelectorAll('.remove-line').forEach(btn => btn.addEventListener('click', function(){ this.closest('.line-item-row')?.remove(); recalc(); }));
  document.querySelectorAll('input[name="client_mode"]').forEach(r => r.addEventListener('change', function(){
    const isNew = this.value === 'new';
    document.getElementById('existing-client-fields').style.display = isNew ? 'none' : 'block';
    document.getElementById('new-client-fields').style.display = isNew ? 'block' : 'none';
  }));
  document.getElementById('client-select')?.addEventListener('change', function(){
    const opt = this.selectedOptions[0];
    const prev = document.getElementById('client-preview');
    if (!opt || !opt.value) { prev.textContent = ''; return; }
    prev.innerHTML = '<strong>' + opt.dataset.name + '</strong> · ' + opt.dataset.email + (opt.dataset.company ? ' · ' + opt.dataset.company : '');
  });
  recalc();
})();
</script>
<?php renderAdminPageEnd(); ?>
