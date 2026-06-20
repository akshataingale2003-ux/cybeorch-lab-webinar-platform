<?php
declare(strict_types=1);

require_once __DIR__ . '/payment-schema.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/admin-actions.php';

ensurePaymentReceiptSchema();

/** @return array<string, string> */
function paymentSlipTypeLabels(): array
{
    return [
        'online'    => 'Online',
        'nxl_token' => 'NXL Token',
        'mixed'     => 'Mixed',
        'free'      => 'Free',
    ];
}

function paymentSlipResolveType(array $row): string
{
    $type = strtolower(trim((string) ($row['payment_type'] ?? '')));
    if ($type !== '' && isset(paymentSlipTypeLabels()[$type])) {
        return $type;
    }
    $nxl = (float) ($row['nxl_tokens_used'] ?? 0);
    $cash = (float) ($row['cash_amount'] ?? 0);
    if ($nxl > 0 && $cash > 0) {
        return 'mixed';
    }
    if ($nxl > 0) {
        return 'nxl_token';
    }
    if (!empty($row['razorpay_payment_id']) || !empty($row['razorpay_order_id'])) {
        return 'online';
    }
    return 'online';
}

function paymentSlipStatusLabel(string $status): string
{
    return match (strtolower($status)) {
        'paid'      => 'PAID',
        'refunded'  => 'REFUNDED',
        'failed'    => 'FAILED',
        'created'   => 'PENDING',
        default     => strtoupper($status),
    };
}

function paymentSlipLogoDataUri(): string
{
    $candidates = [
        defined('CYBEORCH_APP_ROOT') ? CYBEORCH_APP_ROOT . '/assets/images/Blue_home_white.png' : '',
        dirname(__DIR__) . '/assets/images/Blue_home_white.png',
    ];
    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            $mime = 'image/png';
            if (str_ends_with(strtolower($path), '.jpeg') || str_ends_with(strtolower($path), '.jpg')) {
                $mime = 'image/jpeg';
            }
            return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        }
    }
    return '';
}

/**
 * Load payment slip data for trainee (ownership) or admin.
 *
 * @return array<string, mixed>|null
 */
function paymentSlipLoad(int $paymentId, ?int $userId = null, bool $adminBypass = false): ?array
{
    ensurePaymentReceiptSchema();
    ensureAdminActionsSchema();

    if ($paymentId < 1) {
        return null;
    }

    $sql = "SELECT p.*,
            u.full_name AS trainee_name,
            u.email AS trainee_email,
            u.phone AS trainee_phone,
            CASE p.payment_for
                WHEN 'webinar' THEN w.title
                WHEN 'bootcamp' THEN bc.title
                ELSE 'Wallet Top-up'
            END AS program_title,
            wr.registration_no AS webinar_registration_no,
            be.enrollment_no AS bootcamp_enrollment_no
            FROM payments p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN webinars w ON p.payment_for = 'webinar' AND w.id = p.reference_id
            LEFT JOIN bootcamps bc ON p.payment_for = 'bootcamp' AND bc.id = p.reference_id
            LEFT JOIN webinar_registrations wr ON wr.payment_id = p.id
            LEFT JOIN bootcamp_enrollments be ON be.payment_id = p.id
            WHERE p.id = ?";

    $params = [$paymentId];
    if (!$adminBypass && $userId !== null && $userId > 0) {
        $sql .= ' AND p.user_id = ?';
        $params[] = $userId;
    }
    if (adminTableHasColumn('payments', 'deleted_at')) {
        $sql .= ' AND p.deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';

    $row = db()->fetchOne($sql, $params);
    if (!$row) {
        return null;
    }

    if (!in_array(strtolower((string) ($row['status'] ?? '')), ['paid', 'refunded'], true)) {
        return null;
    }

    return paymentSlipBuildData($row);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function paymentSlipBuildData(array $row): array
{
    $paymentType = paymentSlipResolveType($row);
    $nxlUsed = (float) ($row['nxl_tokens_used'] ?? 0);
    $nxlInr = (float) ($row['nxl_inr_value'] ?? 0);
    if ($nxlInr <= 0 && $nxlUsed > 0) {
        $nxlInr = round($nxlUsed * paymentSlipNxlInrValue(), 2);
    }

    $cashAmount = (float) ($row['cash_amount'] ?? 0);
    if ($cashAmount <= 0 && $paymentType === 'online') {
        $cashAmount = (float) ($row['amount'] ?? 0);
    }

    $totalAmount = (float) ($row['amount'] ?? 0);
    if ($totalAmount <= 0) {
        $totalAmount = $cashAmount + $nxlInr;
    }

    $regNo = trim((string) ($row['registration_no'] ?? ''));
    if ($regNo === '') {
        $regNo = trim((string) ($row['webinar_registration_no'] ?? $row['bootcamp_enrollment_no'] ?? ''));
    }

    $walletTx = null;
    $walletTxId = (int) ($row['wallet_transaction_id'] ?? 0);
    if ($walletTxId > 0) {
        $walletTx = db()->fetchOne('SELECT * FROM wallet_transactions WHERE id = ?', [$walletTxId]);
    } elseif ($nxlUsed > 0) {
        $walletTx = db()->fetchOne(
            "SELECT * FROM wallet_transactions WHERE user_id = ? AND type = 'debit' AND reason = 'redemption' AND reference_id = ? ORDER BY id DESC LIMIT 1",
            [(int) $row['user_id'], (int) $row['reference_id']]
        );
    }

    $nxlRef = trim((string) ($row['nxl_transaction_ref'] ?? ''));
    if ($nxlRef === '' && $walletTx) {
        $nxlRef = paymentSchemaGenerateNxlTransactionRef((int) $walletTx['id']);
    }

    $txnId = trim((string) ($row['razorpay_payment_id'] ?? ''));
    if ($txnId === '') {
        $txnId = trim((string) ($row['order_id'] ?? ''));
    }

    $paidAt = $row['paid_at'] ?? $row['created_at'] ?? null;
    $method = trim((string) ($row['payment_method'] ?? ''));
    if ($method === '') {
        $method = match ($paymentType) {
            'nxl_token' => 'NXL Wallet',
            'mixed'     => 'Razorpay + NXL Wallet',
            'free'      => 'Free Registration',
            default     => !empty($row['razorpay_payment_id']) ? 'Razorpay (Online)' : 'Online',
        };
    }

    $programLabel = match ((string) ($row['payment_for'] ?? '')) {
        'webinar'  => 'Webinar',
        'bootcamp' => 'Bootcamp / Course',
        default    => 'Program',
    };

    return [
        'payment_id'          => (int) $row['id'],
        'receipt_no'          => (string) ($row['receipt_no'] ?? $row['invoice_no'] ?? ''),
        'invoice_no'          => (string) ($row['invoice_no'] ?? ''),
        'transaction_id'      => $txnId,
        'order_id'            => (string) ($row['order_id'] ?? ''),
        'paid_at'             => $paidAt,
        'paid_at_formatted'   => $paidAt ? date('d M Y, h:i A', strtotime((string) $paidAt)) : '—',
        'trainee_name'        => (string) ($row['trainee_name'] ?? ''),
        'trainee_email'       => (string) ($row['trainee_email'] ?? ''),
        'trainee_phone'       => (string) ($row['trainee_phone'] ?? ''),
        'program_type'        => $programLabel,
        'program_title'       => (string) ($row['program_title'] ?? 'Payment'),
        'registration_no'     => $regNo,
        'amount_total'        => $totalAmount,
        'amount_cash'         => $cashAmount,
        'currency'            => strtoupper((string) ($row['currency'] ?? 'INR')),
        'payment_method'      => $method,
        'payment_type'        => $paymentType,
        'payment_type_label'  => paymentSlipTypeLabels()[$paymentType] ?? ucfirst($paymentType),
        'status'              => strtolower((string) ($row['status'] ?? '')),
        'status_label'        => paymentSlipStatusLabel((string) ($row['status'] ?? '')),
        'support_email'       => defined('SITE_EMAIL') ? SITE_EMAIL : 'info@cybeorch.com',
        'support_phone'       => '',
        'site_name'           => defined('SITE_NAME') ? SITE_NAME : 'CYBEORCH LABS',
        'nxl_used'            => $nxlUsed > 0,
        'nxl_tokens_used'     => $nxlUsed,
        'nxl_inr_value'       => $nxlInr,
        'nxl_transaction_ref' => $nxlRef,
        'nxl_wallet_address'  => 'CYBEORCH-NXL-' . str_pad((string) ($row['user_id'] ?? 0), 6, '0', STR_PAD_LEFT),
        'nxl_tx_datetime'     => $walletTx['created_at'] ?? ($nxlUsed > 0 ? $paidAt : null),
        'nxl_tx_status'       => $nxlUsed > 0 ? 'COMPLETED' : '',
        'logo_data_uri'       => paymentSlipLogoDataUri(),
    ];
}

function paymentSlipFormatMoney(array $data, float $amount): string
{
    $currency = $data['currency'] ?? 'INR';
    if ($currency === 'USD') {
        return '$' . number_format($amount, 2);
    }
    return '₹' . number_format($amount, 2);
}

/**
 * @param array<string, mixed> $data
 */
function paymentSlipRenderHtml(array $data, bool $forPdf = false): string
{
    $logo = $data['logo_data_uri'] ?? '';
    $logoHtml = $logo !== ''
        ? '<img src="' . htmlspecialchars($logo) . '" alt="CYBEORCH LABS" style="max-height:52px;margin-bottom:8px">'
        : '<div style="font-size:22px;font-weight:700;color:#00d4ff;letter-spacing:1px">CYBEORCH <span style="color:#0a1628;background:#fff;padding:2px 6px;border-radius:4px">LABS</span></div>';

    $nxlBlock = '';
    if (!empty($data['nxl_used'])) {
        $nxlDt = !empty($data['nxl_tx_datetime'])
            ? date('d M Y, h:i A', strtotime((string) $data['nxl_tx_datetime']))
            : '—';
        $nxlBlock = '
        <tr><td colspan="2" style="padding:12px 0 6px;font-weight:700;color:#00a8cc;border-top:1px dashed #ccc">NXL Token Details</td></tr>
        <tr><td style="padding:6px 0;color:#555;width:42%">NXL Token Used</td><td style="padding:6px 0;font-weight:600">Yes</td></tr>
        <tr><td style="padding:6px 0;color:#555">NXL Token Quantity</td><td style="padding:6px 0">' . number_format((float) $data['nxl_tokens_used'], 0) . ' NxL</td></tr>
        <tr><td style="padding:6px 0;color:#555">NXL Token Value</td><td style="padding:6px 0">' . paymentSlipFormatMoney($data, (float) $data['nxl_inr_value']) . '</td></tr>
        <tr><td style="padding:6px 0;color:#555">NXL Transaction ID</td><td style="padding:6px 0;font-family:monospace;font-size:12px">' . htmlspecialchars((string) $data['nxl_transaction_ref']) . '</td></tr>
        <tr><td style="padding:6px 0;color:#555">NXL Wallet Address</td><td style="padding:6px 0;font-family:monospace;font-size:12px">' . htmlspecialchars((string) $data['nxl_wallet_address']) . '</td></tr>
        <tr><td style="padding:6px 0;color:#555">NXL Transaction Date</td><td style="padding:6px 0">' . htmlspecialchars($nxlDt) . '</td></tr>
        <tr><td style="padding:6px 0;color:#555">NXL Payment Status</td><td style="padding:6px 0;color:#0a8f4f;font-weight:600">' . htmlspecialchars((string) ($data['nxl_tx_status'] ?: 'COMPLETED')) . '</td></tr>';
    }

    $bodyPad = $forPdf ? '24px' : '32px';
    $wrapStyle = $forPdf
        ? 'font-family:DejaVu Sans,Arial,sans-serif;font-size:13px;color:#1a1a2e;margin:0;padding:20px;background:#fff'
        : 'font-family:DM Sans,Arial,sans-serif;font-size:14px;color:#e0e8f0;margin:0;padding:24px;background:#050b18;min-height:100vh';

    $cardStyle = $forPdf
        ? 'max-width:720px;margin:0 auto;border:2px solid #0a1628;border-radius:8px;overflow:hidden'
        : 'max-width:720px;margin:0 auto;border:1px solid rgba(0,212,255,0.25);border-radius:12px;overflow:hidden;background:#0a1628';

    $headerBg = '#0a1628';
    $accent = '#00d4ff';
    $statusColor = ($data['status'] ?? '') === 'refunded' ? '#c0392b' : '#0a8f4f';

    $actions = '';
    if (!$forPdf) {
        $pid = (int) ($data['payment_id'] ?? 0);
        $actions = '<div style="text-align:center;margin-top:20px;gap:12px;display:flex;justify-content:center;flex-wrap:wrap">
            <a href="' . htmlspecialchars(url('payment-slip.php?id=' . $pid . '&format=pdf')) . '" style="background:#00d4ff;color:#050b18;padding:10px 20px;border-radius:8px;font-weight:700;text-decoration:none"><i class="fas fa-download"></i> Download PDF</a>
            <a href="' . htmlspecialchars(url('payment-history.php')) . '" style="border:1px solid rgba(0,212,255,0.3);color:#00d4ff;padding:10px 20px;border-radius:8px;text-decoration:none">← Payment History</a>
        </div>';
    }

    $headExtra = $forPdf ? '' : '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">';

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Payment Receipt ' . htmlspecialchars((string) $data['receipt_no']) . '</title>' . $headExtra . '</head>
<body style="' . $wrapStyle . '">
<div style="' . $cardStyle . '">
  <div style="background:' . $headerBg . ';color:#fff;padding:' . $bodyPad . ';text-align:center">
    ' . $logoHtml . '
    <div style="font-size:18px;font-weight:700;letter-spacing:0.5px;margin-top:4px">' . htmlspecialchars((string) $data['site_name']) . '</div>
    <div style="font-size:12px;opacity:0.85;margin-top:4px">Payment Receipt / Tax Acknowledgment</div>
  </div>
  <div style="padding:' . $bodyPad . ';background:' . ($forPdf ? '#fff' : '#0f1e36') . '">
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <tr>
        <td style="vertical-align:top">
          <div style="font-size:11px;color:' . ($forPdf ? '#666' : '#7a8fa6') . ';text-transform:uppercase;letter-spacing:1px">Receipt No.</div>
          <div style="font-size:16px;font-weight:700;color:' . $accent . '">' . htmlspecialchars((string) $data['receipt_no']) . '</div>
        </td>
        <td style="text-align:right;vertical-align:top">
          <div style="font-size:11px;color:' . ($forPdf ? '#666' : '#7a8fa6') . ';text-transform:uppercase">Status</div>
          <div style="font-weight:700;color:' . $statusColor . '">' . htmlspecialchars((string) $data['status_label']) . '</div>
        </td>
      </tr>
    </table>
    <table style="width:100%;border-collapse:collapse">
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . ';width:42%">Transaction ID</td><td style="padding:6px 0;font-family:monospace;font-size:12px">' . htmlspecialchars((string) $data['transaction_id']) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Payment Date &amp; Time</td><td style="padding:6px 0">' . htmlspecialchars((string) $data['paid_at_formatted']) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Trainee Name</td><td style="padding:6px 0;font-weight:600">' . htmlspecialchars((string) $data['trainee_name']) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Email Address</td><td style="padding:6px 0">' . htmlspecialchars((string) $data['trainee_email']) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Mobile Number</td><td style="padding:6px 0">' . htmlspecialchars((string) ($data['trainee_phone'] ?: '—')) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">' . htmlspecialchars((string) $data['program_type']) . ' Name</td><td style="padding:6px 0;font-weight:600">' . htmlspecialchars((string) $data['program_title']) . '</td></tr>
      ' . ($data['registration_no'] !== '' ? '<tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Registration No.</td><td style="padding:6px 0">' . htmlspecialchars((string) $data['registration_no']) . '</td></tr>' : '') . '
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Amount Paid</td><td style="padding:6px 0;font-size:18px;font-weight:700;color:' . $statusColor . '">' . paymentSlipFormatMoney($data, (float) $data['amount_total']) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Payment Method</td><td style="padding:6px 0">' . htmlspecialchars((string) $data['payment_method']) . '</td></tr>
      <tr><td style="padding:6px 0;color:' . ($forPdf ? '#555' : '#7a8fa6') . '">Payment Type</td><td style="padding:6px 0">' . htmlspecialchars((string) $data['payment_type_label']) . '</td></tr>
      ' . $nxlBlock . '
    </table>
    <div style="margin-top:24px;padding-top:16px;border-top:1px solid ' . ($forPdf ? '#ddd' : 'rgba(0,212,255,0.15)') . ';display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px">
      <div style="font-size:11px;color:' . ($forPdf ? '#666' : '#7a8fa6') . '">
        <strong>Support</strong><br>
        ' . htmlspecialchars((string) $data['support_email']) . '
      </div>
      <div style="text-align:center;border:2px solid ' . $accent . ';border-radius:50%;width:90px;height:90px;display:flex;align-items:center;justify-content:center;font-size:9px;color:' . $accent . ';font-weight:700;line-height:1.3;padding:8px">
        SYSTEM<br>GENERATED<br>SEAL<br>CYBEORCH LABS
      </div>
    </div>
    <p style="font-size:10px;color:' . ($forPdf ? '#888' : '#7a8fa6') . ';text-align:center;margin-top:20px;margin-bottom:0">This is a computer-generated payment acknowledgment. No physical signature required.</p>
  </div>
</div>
' . $actions . '
</body></html>';
}

/**
 * @param array<string, mixed> $data
 */
function paymentSlipOutputHtml(array $data): void
{
    header('Content-Type: text/html; charset=UTF-8');
    echo paymentSlipRenderHtml($data, false);
    exit;
}

/**
 * @param array<string, mixed> $data
 */
function paymentSlipOutputPdf(array $data): void
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        header('Location: ' . url('payment-slip.php?id=' . (int) ($data['payment_id'] ?? 0)));
        exit;
    }
    require_once $autoload;

    $html = paymentSlipRenderHtml($data, true);
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'CYBEORCH-Receipt-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $data['receipt_no']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $dompdf->output();
    exit;
}

function paymentSlipUrl(int $paymentId, string $format = 'html'): string
{
    return url('payment-slip.php?id=' . $paymentId . ($format === 'pdf' ? '&format=pdf' : ''));
}

function paymentSlipNxlInrValue(): float
{
    if (!function_exists('nxlInrValuePerToken')) {
        require_once __DIR__ . '/nxl-wallet.php';
    }
    return nxlInrValuePerToken();
}
