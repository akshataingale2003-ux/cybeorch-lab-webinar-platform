<?php
declare(strict_types=1);

require_once __DIR__ . '/invoice-amc.php';
require_once __DIR__ . '/invoice-amc-branding.php';

/**
 * @return array<string, mixed>|null
 */
function amcPdfBuildData(int $amcId): ?array
{
    $amc = invoiceAmcGetAmc($amcId);
    if (!$amc) {
        return null;
    }

    return [
        'amc_id'          => $amcId,
        'amc_no'          => (string) $amc['amc_no'],
        'client_name'     => (string) $amc['client_name'],
        'client_code'     => (string) $amc['client_code'],
        'project_name'    => (string) $amc['project_name'],
        'description'     => (string) ($amc['description'] ?? ''),
        'start_date'      => date('d M Y', strtotime((string) $amc['start_date'])),
        'end_date'        => date('d M Y', strtotime((string) $amc['end_date'])),
        'renewal_date'    => date('d M Y', strtotime((string) $amc['renewal_date'])),
        'duration_label'  => (string) $amc['duration_label'],
        'amount'          => (float) $amc['amount'],
        'currency'        => strtoupper((string) $amc['currency']),
        'logo_data_uri'   => invoicePdfLogoDataUri(),
    ];
}

/**
 * @param array<string, mixed> $data
 */
function amcPdfRenderHtml(array $data, bool $forPdf = true): string
{
    $blue = '#1a4a8e';
    $lightBlue = '#d9e8fb';
    $headerGrey = '#d9d9d9';
    $currency = (string) ($data['currency'] ?? 'USD');
    $amount = invoiceAmcFormatMoney((float) $data['amount'], $currency);

    $brandHeader = invoiceAmcBrandHeaderHtml('amc', (string) ($data['logo_data_uri'] ?? ''));

    $descBlock = trim((string) ($data['description'] ?? ''));
    $descHtml = $descBlock !== ''
        ? '<tr><td style="border:1px solid #000;padding:8px 10px;font-size:12px;color:#000" colspan="2">' . nl2br(htmlspecialchars($descBlock)) . '</td></tr>'
        : '';

    $font = $forPdf ? 'DejaVu Sans, Arial, sans-serif' : 'Arial, Helvetica, sans-serif';
    $bodyPad = '28px 36px';

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>AMC ' . htmlspecialchars((string) $data['amc_no']) . '</title></head>
<body style="margin:0;padding:0;font-family:' . $font . ';background:#fff;color:#000">
<div style="padding:' . $bodyPad . ';max-width:760px;margin:0 auto">

  ' . $brandHeader . '

  <table style="width:100%;border-collapse:collapse;margin-bottom:22px;font-size:12px">
    <tr><td style="width:150px;padding:4px 0;font-weight:700;color:#000">AMC No :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars((string) $data['amc_no']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Client Name :</td><td style="padding:4px 0;color:#000;border-bottom:1px solid #000">' . htmlspecialchars((string) $data['client_name']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Client ID :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars((string) $data['client_code']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Project Name :</td><td style="padding:4px 0;color:#000;border-bottom:1px solid #000">' . htmlspecialchars((string) $data['project_name']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Duration :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars((string) $data['duration_label']) . '</td></tr>
    <tr><td style="padding:4px 0;font-weight:700;color:#000">Currency :</td><td style="padding:4px 0;color:#000">' . htmlspecialchars($currency) . '</td></tr>
  </table>

  <table style="width:100%;border-collapse:collapse;margin-bottom:20px">
    <thead>
      <tr>
        <th style="border:1px solid #000;background:' . $headerGrey . ';padding:8px 10px;font-size:12px;font-weight:700;text-align:left;color:#000" colspan="2">CONTRACT DETAILS</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;font-weight:700;width:45%;color:#000">Start Date</td>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;color:#000">' . htmlspecialchars((string) $data['start_date']) . '</td>
      </tr>
      <tr>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;font-weight:700;color:#000">End Date</td>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;color:#000">' . htmlspecialchars((string) $data['end_date']) . '</td>
      </tr>
      <tr>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;font-weight:700;color:#000">Renewal Date</td>
        <td style="border:1px solid #000;padding:8px 10px;font-size:12px;color:#000">' . htmlspecialchars((string) $data['renewal_date']) . '</td>
      </tr>
      ' . $descHtml . '
      <tr>
        <td style="border:1px solid #000;padding:10px;font-size:13px;font-weight:700;text-align:right;color:' . $blue . ';background:' . $lightBlue . '">CONTRACT VALUE</td>
        <td style="border:1px solid #000;padding:10px;font-size:13px;font-weight:700;text-align:right;color:' . $blue . ';background:' . $lightBlue . '">' . htmlspecialchars($amount) . '</td>
      </tr>
    </tbody>
  </table>

  <table style="width:100%;border-collapse:collapse;font-size:11px;color:#000;margin-bottom:30px">
    <tr><td style="padding:3px 0"><strong>Scope :</strong> Annual Maintenance Contract covering support, updates, monitoring, and agreed service levels.</td></tr>
    <tr><td style="padding:3px 0"><strong>Payment Terms :</strong> As per agreement (Bank / Crypto / USDT / Wire Transfer).</td></tr>
    <tr><td style="padding:3px 0"><strong>Renewal :</strong> Automatic renewal reminders will be sent before contract expiry.</td></tr>
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

function amcPdfGenerateAndSave(int $amcId): ?string
{
    $data = amcPdfBuildData($amcId);
    if (!$data) {
        return null;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        return null;
    }
    require_once $autoload;

    invoiceAmcEnsureUploadDirs();
    $safeNo = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $data['amc_no']);
    $dir = invoiceAmcUploadsRoot() . DIRECTORY_SEPARATOR . 'amc';
    $path = $dir . DIRECTORY_SEPARATOR . $safeNo . '.pdf';

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml(amcPdfRenderHtml($data, true));
    $dompdf->setPaper('A4', 'portrait');
    try {
        $dompdf->render();
    } catch (Throwable $e) {
        return null;
    }

    file_put_contents($path, $dompdf->output());

    return is_file($path) ? $path : null;
}

function amcPdfStream(int $amcId): void
{
    $amc = invoiceAmcGetAmc($amcId);
    if (!$amc) {
        http_response_code(404);
        echo 'AMC contract not found.';
        exit;
    }

    $path = (string) ($amc['pdf_path'] ?? '');
    if ($path === '' || !is_file($path)) {
        $ensure = invoiceAmcEnsureAmcPdf($amcId);
        $path = !empty($ensure['path']) ? (string) $ensure['path'] : '';
    }

    if (!$path || !is_file($path)) {
        http_response_code(500);
        echo 'PDF could not be generated.';
        exit;
    }

    $filename = 'CYBEORCH-AMC-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $amc['amc_no']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($path);
    exit;
}
