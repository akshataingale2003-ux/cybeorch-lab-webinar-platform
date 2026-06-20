<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/payment.php';
require_once __DIR__ . '/includes/payment-slip.php';
require_once __DIR__ . '/includes/student-layout.php';

$ctx = studentContext();
$payments = Payment::getHistory($ctx['userId']);

renderStudentHead('Payment History');
renderStudentSidebar('payments', $ctx);
?>
<main class="main">
  <?php renderPortalTopbar('Payment History'); ?>
  <div class="content">
    <?php if (empty($payments)): ?>
    <div class="card-panel" style="color:var(--cyber-muted)">No payment receipts yet. Completed payments will appear here with view and download options.</div>
    <?php else: ?>
    <div class="card-panel" style="padding:0;overflow:hidden">
      <div class="table-responsive">
        <table class="data-table" style="margin:0">
          <thead>
            <tr>
              <th>Receipt No.</th>
              <th>Course / Webinar</th>
              <th>Amount</th>
              <th>Payment Type</th>
              <th>NXL Used</th>
              <th>Payment Date</th>
              <th>Status</th>
              <th>Slip</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $p):
                $pid = (int) $p['id'];
                $ptype = paymentSlipTypeLabels()[paymentSlipResolveType($p)] ?? 'Online';
                $nxlUsed = (float) ($p['nxl_tokens_used'] ?? 0) > 0;
                $paidAt = $p['paid_at'] ?? $p['created_at'];
                $receipt = $p['receipt_no'] ?? $p['invoice_no'] ?? '—';
                $status = strtolower((string) ($p['status'] ?? ''));
                $amount = (float) ($p['amount'] ?? 0);
                $currency = strtoupper((string) ($p['currency'] ?? 'INR'));
                $amountLabel = $currency === 'USD' ? '$' . number_format($amount, 2) : '₹' . number_format($amount, 2);
            ?>
            <tr>
              <td><code style="font-size:0.78rem"><?= htmlspecialchars((string) $receipt) ?></code></td>
              <td><?= htmlspecialchars((string) ($p['item_title'] ?? 'Payment')) ?></td>
              <td style="font-weight:600;color:var(--cyber-green)"><?= htmlspecialchars($amountLabel) ?></td>
              <td><?= htmlspecialchars($ptype) ?></td>
              <td><?= $nxlUsed ? 'Yes' : 'No' ?></td>
              <td><?= $paidAt ? date('d M Y, h:i A', strtotime((string) $paidAt)) : '—' ?></td>
              <td><span class="badge-status <?= $status === 'refunded' ? 'badge-refunded' : 'badge-paid' ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
              <td style="white-space:nowrap">
                <a href="<?= htmlspecialchars(paymentSlipUrl($pid, 'html')) ?>" class="btn-sm-cyber btn-edit" target="_blank" rel="noopener"><i class="fas fa-eye"></i><span>View</span></a>
                <a href="<?= htmlspecialchars(paymentSlipUrl($pid, 'pdf')) ?>" class="btn-sm-cyber" style="margin-left:0.35rem"><i class="fas fa-file-pdf"></i><span>PDF</span></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
