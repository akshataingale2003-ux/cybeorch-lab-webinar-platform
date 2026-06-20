<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';
require_once __DIR__ . '/../includes/amc-pdf.php';
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
    if ($postAction === 'create_amc') {
        $result = invoiceAmcCreateAmc($_POST, $adminId);
        if ($result['ok']) {
            $flash = invoiceAmcAmcCreateFlashMessage($result);
            setFlash($flash['type'], $flash['message']);
            header('Location: ' . adminUrl('amc.php?view=' . (int) $result['id']));
            exit;
        }
        $flash = $result['error'] ?? 'Could not create AMC contract.';
        $flashType = 'danger';
        $action = 'create';
    } elseif ($postAction === 'renew_amc') {
        $amcId = (int) ($_POST['amc_id'] ?? 0);
        $result = invoiceAmcRenewAmc($amcId, $adminId);
        if ($result['ok']) {
            $flash = invoiceAmcAmcRenewFlashMessage($result);
            setFlash($flash['type'], $flash['message']);
            header('Location: ' . adminUrl('amc.php?view=' . (int) $result['id']));
            exit;
        }
        $flash = $result['error'] ?? 'Could not renew AMC.';
        $flashType = 'danger';
    } elseif ($postAction === 'resend_email') {
        $amcId = (int) ($_POST['amc_id'] ?? 0);
        $amc = invoiceAmcGetAmc($amcId);
        if (!$amc) {
            $flash = 'AMC contract not found.';
            $flashType = 'danger';
        } else {
            $pdfResult = invoiceAmcEnsureAmcPdf($amcId);
            if (empty($pdfResult['ok']) || empty($pdfResult['path'])) {
                $flash = 'PDF not found. ' . ($pdfResult['error'] ?? 'Could not generate PDF.');
                $flashType = 'danger';
            } else {
                $client = invoiceAmcGetClient((int) $amc['client_id']);
                $r = invoiceMailerSendAmcCreated($amcId, $client ?? [], (string) $pdfResult['path']);
                $flash = $r['ok'] ? 'AMC email sent.' : ($r['error'] ?? 'Email failed.');
                $flashType = $r['ok'] ? 'success' : 'danger';
            }
        }
    } elseif ($postAction === 'run_reminders') {
        $result = invoiceAmcProcessRenewalReminders();
        $flash = 'Renewal reminders sent: ' . (int) $result['sent'];
        if (!empty($result['errors'])) {
            $flash .= '. Errors: ' . implode('; ', $result['errors']);
            $flashType = 'warning';
        }
    } elseif ($postAction === 'update_amc') {
        $amcId = (int) ($_POST['amc_id'] ?? 0);
        $result = invoiceAmcUpdateAmc($amcId, $_POST);
        if ($result['ok']) {
            header('Location: ' . adminUrl('amc.php?view=' . $amcId . '&saved=1'));
            exit;
        }
        $flash = $result['error'] ?? 'Could not update AMC contract.';
        $flashType = 'danger';
        $editId = $amcId;
    }
}

$viewRow = $viewId > 0 ? invoiceAmcGetAmc($viewId) : null;
$editRow = $editId > 0 ? invoiceAmcGetAmc($editId) : null;
$contracts = invoiceAmcListAmc(['status' => $statusFilter, 'q' => $searchQ]);
$clients = array_values(array_filter(invoiceAmcListClients(), static fn ($c) => !adminRecordIsBlocked($c)));
$stats = invoiceAmcDashboardStats();

$sessionFlash = getFlash();
if ($sessionFlash) {
    $flash = (string) $sessionFlash['message'];
    $flashType = match ($sessionFlash['type']) {
        'error', 'danger' => 'danger',
        'warning'         => 'warning',
        default           => 'success',
    };
}

if (!empty($_GET['saved'])) {
    $flash = 'AMC contract updated.';
}

renderAdminPageStart('AMC Management', 'amc', 'fa-file-contract');
?>
<style>
.amc-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}
.client-mode-toggle{display:flex;gap:0.75rem;margin-bottom:1rem}
.client-mode-toggle label{display:flex;align-items:center;gap:0.35rem;cursor:pointer;font-size:0.85rem}
#new-client-fields{display:none}
#custom-months-wrap{display:none}
</style>

<?php if ($flash): ?>
<div class="alert alert-<?= $flashType === 'danger' ? 'danger' : ($flashType === 'warning' ? 'warning' : 'success') ?>" style="margin-bottom:1rem"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card blue"><div class="stat-value" style="font-size:1.6rem"><?= $stats['total_amc'] ?></div><div class="stat-label">Total AMC</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card green"><div class="stat-value" style="font-size:1.6rem"><?= $stats['active_amc'] ?></div><div class="stat-label">Active</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card orange"><div class="stat-value" style="font-size:1.6rem"><?= $stats['expiring_amc'] ?></div><div class="stat-label">Expiring Soon</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card red"><div class="stat-value" style="font-size:1.6rem"><?= $stats['expired_amc'] ?></div><div class="stat-label">Expired</div></div></div>
</div>

<?php if ($viewRow): ?>
<div class="section-card mb-4">
  <div class="section-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-eye" style="color:var(--cyber-accent)"></i> View AMC — <?= htmlspecialchars($viewRow['amc_no']) ?></span>
    <div class="d-flex flex-wrap gap-1 align-items-center">
      <span class="badge-status badge-<?= in_array($viewRow['status'], ['active','renewed'], true) ? 'paid' : ($viewRow['status'] === 'expiring' ? 'pending' : 'completed') ?>"><?= strtoupper($viewRow['status']) ?></span>
      <a href="<?= adminUrl('amc.php?edit=' . (int) $viewRow['id']) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i>Edit</a>
      <a href="<?= url('api/amc-pdf.php?id=' . (int) $viewRow['id']) ?>" target="_blank" rel="noopener" class="btn-sm-cyber btn-edit"><i class="fas fa-file-pdf"></i><span>PDF</span></a>
      <a href="<?= adminUrl('amc.php') ?>" class="btn-cancel">Back to list</a>
    </div>
  </div>
  <div class="section-card-body" style="padding:1.25rem">
    <div class="detail-grid">
      <div class="detail-item"><label>Client</label><span><?= htmlspecialchars($viewRow['client_name']) ?> (<?= htmlspecialchars($viewRow['client_code']) ?>)</span></div>
      <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($viewRow['client_email']) ?></span></div>
      <div class="detail-item"><label>Project</label><span><?= htmlspecialchars($viewRow['project_name']) ?></span></div>
      <div class="detail-item"><label>Duration</label><span><?= htmlspecialchars($viewRow['duration_label']) ?> (<?= (int) $viewRow['duration_months'] ?> months)</span></div>
      <div class="detail-item"><label>Start Date</label><span><?= date('d M Y', strtotime($viewRow['start_date'])) ?></span></div>
      <div class="detail-item"><label>End Date</label><span><?= date('d M Y', strtotime($viewRow['end_date'])) ?></span></div>
      <div class="detail-item"><label>Renewal Date</label><span><?= date('d M Y', strtotime($viewRow['renewal_date'])) ?></span></div>
      <div class="detail-item"><label>Contract Value</label><span style="font-weight:700;color:var(--cyber-green)"><?= invoiceAmcFormatMoney((float) $viewRow['amount'], (string) $viewRow['currency']) ?></span></div>
      <div class="detail-item"><label>Created</label><span><?= date('d M Y', strtotime($viewRow['created_at'])) ?></span></div>
    </div>
    <?php if (!empty($viewRow['description'])): ?>
    <div class="detail-item mt-3"><label>Description / Scope</label><div><?= nl2br(htmlspecialchars($viewRow['description'])) ?></div></div>
    <?php endif; ?>
    <?php if (!empty($viewRow['notes'])): ?>
    <div class="detail-item mt-3"><label>Notes</label><div><?= nl2br(htmlspecialchars($viewRow['notes'])) ?></div></div>
    <?php endif; ?>
    <div style="margin-top:1.25rem;display:flex;flex-wrap:wrap;gap:0.5rem">
      <?php if (in_array($viewRow['status'], ['active','expiring','expired'], true)): ?>
      <form method="post" class="d-inline" onsubmit="return confirm('Renew this AMC contract?')">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
        <input type="hidden" name="action" value="renew_amc">
        <input type="hidden" name="amc_id" value="<?= (int) $viewRow['id'] ?>">
        <button type="submit" class="btn-sm-cyber btn-edit"><i class="fas fa-redo"></i><span>Renew AMC</span></button>
      </form>
      <?php endif; ?>
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
        <input type="hidden" name="action" value="resend_email">
        <input type="hidden" name="amc_id" value="<?= (int) $viewRow['id'] ?>">
        <button type="submit" class="btn-sm-cyber btn-edit"><i class="fas fa-envelope"></i><span>Resend Email</span></button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($editRow && empty($editRow['deleted_at']) && !$viewId): ?>
<div class="section-card mb-4">
  <div class="section-card-header"><i class="fas fa-pen" style="color:var(--cyber-accent)"></i> Edit AMC — <?= htmlspecialchars($editRow['amc_no']) ?></div>
  <div class="section-card-body" style="padding:1.25rem">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="update_amc">
      <input type="hidden" name="amc_id" value="<?= (int) $editRow['id'] ?>">
      <div class="amc-form-grid mb-3">
        <div><label class="form-label">Project Name *</label><input type="text" name="project_name" class="form-control" value="<?= htmlspecialchars($editRow['project_name']) ?>" required></div>
        <div><label class="form-label">Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars((string) $editRow['amount']) ?>"></div>
        <div><label class="form-label">Currency</label>
          <select name="currency" class="form-control">
            <?php foreach (invoiceCurrencyOptions() as $k => $v): ?>
            <option value="<?= $k ?>"<?= $editRow['currency'] === $k ? ' selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach (['active', 'expiring', 'expired', 'renewed', 'cancelled'] as $st): ?>
            <option value="<?= $st ?>"<?= $editRow['status'] === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3"><label class="form-label">Description / Scope</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editRow['description'] ?? '') ?></textarea></div>
      <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editRow['notes'] ?? '') ?></textarea></div>
      <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Update AMC</button>
      <a href="<?= adminUrl('amc.php?view=' . (int) $editRow['id']) ?>" class="btn-cancel">Cancel</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($action === 'create' && !$viewId && !$editId): ?>
<div class="section-card mb-4">
  <div class="section-card-header">Create AMC Contract</div>
  <div class="section-card-body" style="padding:1.25rem">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="create_amc">

      <div class="client-mode-toggle">
        <label><input type="radio" name="client_mode" value="existing" checked> Existing Client</label>
        <label><input type="radio" name="client_mode" value="new"> New Client</label>
      </div>

      <div id="existing-client-fields" class="mb-3">
        <label class="form-label">Select Client</label>
        <select name="client_id" class="form-control">
          <option value="">— Select —</option>
          <?php foreach ($clients as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['client_code'] . ' — ' . $c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div id="new-client-fields">
        <div class="amc-form-grid mb-3">
          <div><label class="form-label">Name *</label><input type="text" name="name" class="form-control"></div>
          <div><label class="form-label">Email *</label><input type="email" name="email" class="form-control"></div>
          <div><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
          <div><label class="form-label">Company</label><input type="text" name="company" class="form-control"></div>
        </div>
      </div>

      <div class="amc-form-grid mb-3">
        <div><label class="form-label">Project Name *</label><input type="text" name="project_name" class="form-control" required></div>
        <div><label class="form-label">Start Date</label><input type="date" name="start_date" id="start-date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div><label class="form-label">Duration</label>
          <select name="duration" id="duration-select" class="form-control">
            <?php foreach (invoiceAmcDurationOptions() as $k => $v): ?>
            <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="custom-months-wrap"><label class="form-label">Custom Months</label><input type="number" name="custom_months" class="form-control" min="1" value="12"></div>
        <div><label class="form-label">Contract Amount</label><input type="number" name="amount" class="form-control" step="0.01" min="0" value="0"></div>
        <div><label class="form-label">Currency</label>
          <select name="currency" class="form-control">
            <?php foreach (invoiceCurrencyOptions() as $k => $v): ?>
            <option value="<?= $k ?>"<?= $k === 'USD' ? ' selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">End Date (auto-calculated)</label>
        <input type="text" id="end-date-preview" class="form-control" readonly style="opacity:0.85">
      </div>
      <div class="mb-3"><label class="form-label">Description / Scope</label><textarea name="description" class="form-control" rows="3" placeholder="Maintenance scope, SLA, support hours…"></textarea></div>

      <button type="submit" class="btn-primary-cyber"><i class="fas fa-file-contract"></i> Create AMC Contract</button>
      <a href="<?= adminUrl('amc.php') ?>" class="btn-cancel ms-2">Cancel</a>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!$viewId && !$editId): ?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
    <span>All AMC Contracts (<?= count($contracts) ?>)</span>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
      <form method="post" style="display:inline">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
        <input type="hidden" name="action" value="run_reminders">
        <button type="submit" class="btn-sm-cyber btn-edit"><i class="fas fa-bell"></i> Run Reminders</button>
      </form>
      <?php if ($action !== 'create'): ?>
      <a href="<?= adminUrl('amc.php?action=create') ?>" class="btn-primary-cyber btn-sm-cyber"><i class="fas fa-plus"></i> Create AMC</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="section-card-body">
    <form method="get" class="mb-3" style="display:flex;gap:0.5rem;flex-wrap:wrap">
      <select name="status" class="form-control" style="max-width:160px">
        <option value="all">All statuses</option>
        <?php foreach (['active','expiring','expired','renewed','cancelled'] as $st): ?>
        <option value="<?= $st ?>"<?= $statusFilter === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="q" class="form-control" placeholder="Search…" value="<?= htmlspecialchars($searchQ) ?>" style="max-width:220px">
      <button type="submit" class="btn-sm-cyber btn-edit">Filter</button>
    </form>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>AMC No</th><th>Client</th><th>Project</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (!$contracts): ?>
          <tr><td colspan="7" style="color:var(--cyber-muted)">No AMC contracts yet.</td></tr>
          <?php else: foreach ($contracts as $a):
            $blocked = adminRecordIsBlocked($a);
          ?>
          <tr<?= renderAdminRecordRowAttrs('amc_contract', (int) $a['id'], $blocked) ?>>
            <td style="font-family:monospace;font-size:0.82rem"><?= htmlspecialchars($a['amc_no']) ?></td>
            <td><?= htmlspecialchars($a['client_name']) ?></td>
            <td><?= htmlspecialchars($a['project_name']) ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($a['start_date'])) ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($a['end_date'])) ?></td>
            <td><span class="badge-status badge-<?= $a['status'] === 'active' ? 'paid' : ($a['status'] === 'expiring' ? 'pending' : 'completed') ?>"><?= strtoupper($a['status']) ?></span></td>
            <td style="white-space:nowrap">
              <?php renderAdminEntityRowActions(
                  'amc_contract',
                  (int) $a['id'],
                  adminUrl('amc.php?view=' . (int) $a['id']),
                  adminUrl('amc.php?edit=' . (int) $a['id']),
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
  const durationMap = {6:6, 12:12, 24:24, custom: null};
  function calcEnd(){
    const start = document.getElementById('start-date')?.value;
    const dur = document.getElementById('duration-select')?.value;
    const custom = parseInt(document.querySelector('[name="custom_months"]')?.value) || 12;
    if (!start) return;
    let months = durationMap[dur] ?? custom;
    if (dur === 'custom') months = custom;
    const d = new Date(start + 'T00:00:00');
    d.setMonth(d.getMonth() + months);
    d.setDate(d.getDate() - 1);
    const el = document.getElementById('end-date-preview');
    if (el) el.value = d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
  }
  document.getElementById('start-date')?.addEventListener('change', calcEnd);
  document.getElementById('duration-select')?.addEventListener('change', function(){
    document.getElementById('custom-months-wrap').style.display = this.value === 'custom' ? 'block' : 'none';
    calcEnd();
  });
  document.querySelector('[name="custom_months"]')?.addEventListener('input', calcEnd);
  document.querySelectorAll('input[name="client_mode"]').forEach(r => r.addEventListener('change', function(){
    const isNew = this.value === 'new';
    document.getElementById('existing-client-fields').style.display = isNew ? 'none' : 'block';
    document.getElementById('new-client-fields').style.display = isNew ? 'block' : 'none';
  }));
  calcEnd();
})();
</script>
<?php renderAdminPageEnd(); ?>
