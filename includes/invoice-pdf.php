<?php
declare(strict_types=1);

require_once __DIR__ . '/invoice-amc.php';
require_once __DIR__ . '/invoice-amc-branding.php';

/**
 * @return array<string, mixed>|null
 */
function invoicePdfBuildData(int $invoiceId): ?array
{
    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        return null;
    }
    $items = invoiceAmcGetInvoiceItems($invoiceId);

    return [
        'invoice_id'       => $invoiceId,
        'invoice_no'       => (string) $invoice['invoice_no'],
        'invoice_date'     => date('d M Y', strtotime((string) $invoice['invoice_date'])),
        'client_name'      => (string) $invoice['client_name'],
        'client_code'      => (string) $invoice['client_code'],
        'project_name'     => (string) $invoice['project_name'],
        'project_type'     => (string) $invoice['project_type'],
        'currency'         => strtoupper((string) $invoice['currency']),
        'items'            => $items,
        'subtotal'         => (float) $invoice['subtotal'],
        'discount'         => (float) $invoice['discount'],
        'tax_rate'         => (float) $invoice['tax_rate'],
        'tax_amount'       => (float) $invoice['tax_amount'],
        'total_amount'     => (float) $invoice['total_amount'],
        'payment_terms'    => (string) ($invoice['payment_terms'] ?? ''),
        'validity_days'    => (int) ($invoice['validity_days'] ?? 30),
        'payment_methods'  => (string) ($invoice['payment_methods'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $data
 */
function invoicePdfRenderHtml(array $data, bool $forPdf = true): string
{
    $blue = '#1a4a8e';
    $lightBlue = '#d9e8fb';
    $headerGrey = '#d9d9d9';
    $currency = (string) ($data['currency'] ?? 'USD');
    $amountHeader = 'AMOUNT (' . htmlspecialchars($currency) . ')';

    $brandHeader = invoiceAmcBrandHeaderHtml('invoice');

    $itemRows = '';
    foreach ($data['items'] as $item) {
        $desc = htmlspecialchars((string) $item['description']);
        $amt = invoiceAmcFormatMoney((float) $item['amount'], $currency);
        $itemRows .= '<tr>
            <td style="border:1px solid #000;padding:8px 10px;font-size:12px;color:#000">' . $desc . '</td>
            <td style="border:1px solid #000;padding:8px 10px;font-size:12px;color:#000;text-align:right;white-space:nowrap">' . htmlspecialchars($amt) . '</td>
        </tr>';
    }

    $subtotal = invoiceAmcFormatMoney((float) $data['subtotal'], $currency);
    $discount = invoiceAmcFormatMoney((float) $data['discount'], $currency);
    $taxAmount = invoiceAmcFormatMoney((float) $data['tax_amount'], $currency);
    $total = invoiceAmcFormatMoney((float) $data['total_amount'], $currency);
    $taxRate = number_format((float) ($data['tax_rate'] ?? 0), 2);

    $discountRow = '';
    if ((float) ($data['discount'] ?? 0) > 0) {
        $discountRow = '<tr>
            <td style="border:1px solid #000;padding:8px 10px;font-size:12px;font-weight:700;text-align:right;color:#000">DISCOUNT (If Applicable)</td>
            <td style="border:1px solid #000;padding:8px 10px;font-size:12px;text-align:right;color:#000">-' . htmlspecialchars($discount) . '</td>
        </tr>';
    }

    $taxRow = '';
    if ((float) ($data['tax_amount'] ?? 0) > 0) {
        $taxRow = '<tr>
            <td style="border:1px solid #000;padding:8px 10px;font-size:12px;font-weight:700;text-align:right;color:#000">TAX (' . htmlspecialchars($taxRate) . '%)</td>
            <td style="border:1px solid #000;padding:8px 10px;font-size:12px;text-align:right;color:#000">' . htmlspecialchars($taxAmount) . '</td>
        </tr>';
    }

    $font = $forPdf ? 'DejaVu Sans, Arial, sans-serif' : 'Arial, Helvetica, sans-serif';
    $bodyPad = '28px 36px';

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Invoice ' . htmlspecialchars((string) $data['invoice_no']) . '</title></head>
<body style="margin:0;padding:0;font-family:' . $font . ';background:#fff;color:#000">
<div style="padding:' . $bodyPad . ';max-width:760px;margin:0 auto">

  ' . $brandHeader . '

  <table style="width:100%;border-collapse:collapse;margin-bottom:22px;font-size:12px">
    <tr><td style="width:130px;padding:4px 0;font-weight:700;color:#000">Invoice No :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars((string) $data['invoice_no']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Date :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars((string) $data['invoice_date']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Client Name :</td><td style="padding:4px 0;color:#000;border-bottom:1px solid #000">' . htmlspecialchars((string) $data['client_name']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Project Name :</td><td style="padding:4px 0;color:#000;border-bottom:1px solid #000">' . htmlspecialchars((string) $data['project_name']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Currency :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars($currency) . '</td></tr>
  </table>

  <table style="width:100%;border-collapse:collapse;margin-bottom:20px">
    <thead>
      <tr>
        <th style="border:1px solid #000;background:' . $headerGrey . ';padding:8px 10px;font-size:12px;font-weight:700;text-align:left;color:#000">DESCRIPTION</th>
        <th style="border:1px solid #000;background:' . $headerGrey . ';padding:8px 10px;font-size:12px;font-weight:700;text-align:right;color:#000;width:160px">' . $amountHeader . '</th>
      </tr>
    </thead>
    <tbody>
      ' . $itemRows . '
      <tr>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;font-weight:700;text-align:right;color:#000">SUB TOTAL</td>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;text-align:right;color:#000">' . htmlspecialchars($subtotal) . '</td>
      </tr>
      ' . $discountRow . '
      ' . $taxRow . '
      <tr>
        <td style="border:1px solid #000;padding:10px;font-size:13px;font-weight:700;text-align:right;color:' . $blue . ';background:' . $lightBlue . '">TOTAL AMOUNT</td>
        <td style="border:1px solid #000;padding:10px;font-size:13px;font-weight:700;text-align:right;color:' . $blue . ';background:' . $lightBlue . '">' . htmlspecialchars($total) . '</td>
      </tr>
    </tbody>
  </table>

  <table style="width:100%;border-collapse:collapse;font-size:11px;color:#000;margin-bottom:30px">
    <tr><td style="padding:3px 0"><strong>Payment Terms :</strong> ' . htmlspecialchars((string) $data['payment_terms']) . '</td></tr>
    <tr><td style="padding:3px 0"><strong>Validity :</strong> ' . (int) $data['validity_days'] . ' Days</td></tr>
    <tr><td style="padding:3px 0">' . htmlspecialchars((string) $data['payment_methods']) . '</td></tr>
  </table>

  <table style="width:100%;border-collapse:collapse;margin-top:40px">
    <tr>
      <td style="vertical-align:bottom;font-size:11px;color:#000">For Cybeorch Technologies Pvt. Ltd.</td>
      <td style="vertical-align:bottom;text-align:right;width:220px">
        <div style="border-top:1px solid #000;padding-top:6px;font-size:11px;color:#000;text-align:center">Authorized Signatory</div>
      </td>
    </tr>
  </table>

</div>
</body></html>';
}

function invoicePdfGenerateAndSave(int $invoiceId): ?string
{
    $data = invoicePdfBuildData($invoiceId);
    if (!$data) {
        return null;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return null;
    }
    require_once $autoload;

    invoiceAmcEnsureUploadDirs();
    $safeNo = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $data['invoice_no']);
    $dir = invoiceAmcUploadsRoot() . DIRECTORY_SEPARATOR . 'invoices';
    $path = $dir . DIRECTORY_SEPARATOR . $safeNo . '.pdf';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml(invoicePdfRenderHtml($data, true));
    $dompdf->setPaper('A4', 'portrait');
    try {
        $dompdf->render();
    } catch (Throwable $e) {
        return null;
    }

    file_put_contents($path, $dompdf->output());

    return is_file($path) ? $path : null;
}

function invoicePdfStream(int $invoiceId): void
{
    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        http_response_code(404);
        echo 'Invoice not found.';
        exit;
    }

    $path = (string) ($invoice['pdf_path'] ?? '');
    if ($path === '' || !is_file($path)) {
        $ensure = invoiceAmcEnsureInvoicePdf($invoiceId);
        $path = !empty($ensure['path']) ? (string) $ensure['path'] : '';
    }

    if (!$path || !is_file($path)) {
        http_response_code(500);
        echo 'PDF could not be generated.';
        exit;
    }

    $filename = 'AMC-Management-Invoice-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $invoice['invoice_no']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($path);
    exit;
}
