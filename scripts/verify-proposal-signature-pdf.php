<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/invoice-pdf.php';
require_once __DIR__ . '/../includes/amc-pdf.php';

$expectedLine = 'For Cybeorch Technologies Pvt. Ltd.';
$legacyLine = 'For' . ' .';
$signatureLine = 'Authorized Signatory';
$legacyInvoiceBrand = 'AMC Management';
$invoiceBrandName = 'CYBEORCH';
$invoiceBrandTagline = 'BLOCKCHAIN DEVELOPMENT DIVISION';

$invoiceData = [
    'invoice_no' => 'TEST-INV-001',
    'invoice_date' => date('d M Y'),
    'client_name' => 'Test Client',
    'client_code' => 'CL-001',
    'project_name' => 'Proposal Verification',
    'project_type' => 'Custom Projects',
    'currency' => 'USD',
    'items' => [
        ['description' => 'Test Line Item', 'amount' => 100],
    ],
    'subtotal' => 100,
    'discount' => 0,
    'tax_rate' => 0,
    'tax_amount' => 0,
    'total_amount' => 100,
    'payment_terms' => 'Test Terms',
    'validity_days' => 30,
    'payment_methods' => 'Bank Transfer',
];

$amcData = [
    'amc_no' => 'TEST-AMC-001',
    'client_name' => 'Test Client',
    'client_code' => 'CL-001',
    'project_name' => 'Proposal Verification',
    'description' => 'Test AMC description.',
    'start_date' => date('d M Y'),
    'end_date' => date('d M Y', strtotime('+1 year')),
    'renewal_date' => date('d M Y', strtotime('+1 year')),
    'duration_label' => '12 Months',
    'amount' => 100,
    'currency' => 'USD',
    'logo_data_uri' => '',
];

$invoiceHtmlPdf = invoicePdfRenderHtml($invoiceData, true);
$invoiceHtmlPrint = invoicePdfRenderHtml($invoiceData, false);
$amcHtmlPdf = amcPdfRenderHtml($amcData, true);
$amcHtmlPrint = amcPdfRenderHtml($amcData, false);

$htmlChecks = [
    'invoice_pdf_html' => $invoiceHtmlPdf,
    'invoice_print_html' => $invoiceHtmlPrint,
    'amc_pdf_html' => $amcHtmlPdf,
    'amc_print_html' => $amcHtmlPrint,
];

$errors = [];
foreach ($htmlChecks as $label => $html) {
    if (strpos($html, $expectedLine) === false) {
        $errors[] = $label . ': missing expected company signature line.';
    }
    if (strpos($html, $signatureLine) === false) {
        $errors[] = $label . ': missing Authorized Signatory line.';
    }
    if (strpos($html, $legacyLine) !== false) {
        $errors[] = $label . ': legacy "For ." still present.';
    }
}

if (strpos($invoiceHtmlPdf, $legacyInvoiceBrand) !== false || strpos($invoiceHtmlPrint, $legacyInvoiceBrand) !== false) {
    $errors[] = 'invoice header still contains legacy "AMC Management" text.';
}
if (strpos($invoiceHtmlPdf, $invoiceBrandName) === false || strpos($invoiceHtmlPrint, $invoiceBrandName) === false) {
    $errors[] = 'invoice header missing CYBEORCH brand name.';
}
if (strpos($invoiceHtmlPdf, $invoiceBrandTagline) === false || strpos($invoiceHtmlPrint, $invoiceBrandTagline) === false) {
    $errors[] = 'invoice header missing BLOCKCHAIN DEVELOPMENT DIVISION tagline.';
}
if (strpos($invoiceHtmlPdf, '<img src="data:image/') === false || strpos($invoiceHtmlPrint, '<img src="data:image/') === false) {
    $errors[] = 'invoice header logo image not embedded.';
}
$expectedLogoDataUri = function_exists('invoicePdfLogoDataUri') ? invoicePdfLogoDataUri() : '';
if ($expectedLogoDataUri === '') {
    $errors[] = 'uploads/logo.png could not be loaded for invoice header.';
} else {
    if (strpos($invoiceHtmlPdf, $expectedLogoDataUri) === false || strpos($invoiceHtmlPrint, $expectedLogoDataUri) === false) {
        $errors[] = 'invoice header is not using uploads/logo.png image data.';
    }
}

$outDir = __DIR__ . '/../logs/signature-verification';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

file_put_contents($outDir . '/invoice-preview.html', $invoiceHtmlPrint);
file_put_contents($outDir . '/amc-preview.html', $amcHtmlPrint);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $invoicePdf = new \Dompdf\Dompdf($options);
    $invoicePdf->loadHtml($invoiceHtmlPdf);
    $invoicePdf->setPaper('A4', 'portrait');
    $invoicePdf->render();
    file_put_contents($outDir . '/invoice-signature-test.pdf', $invoicePdf->output());

    $amcPdf = new \Dompdf\Dompdf($options);
    $amcPdf->loadHtml($amcHtmlPdf);
    $amcPdf->setPaper('A4', 'portrait');
    $amcPdf->render();
    file_put_contents($outDir . '/amc-signature-test.pdf', $amcPdf->output());
}

if ($errors !== []) {
    echo "FAIL\n";
    foreach ($errors as $error) {
        echo '- ' . $error . "\n";
    }
    exit(1);
}

echo "PASS\n";
echo 'Expected text: ' . $expectedLine . "\n";
echo 'Signature line: ' . $signatureLine . "\n";
echo 'Artifacts: ' . str_replace('\\', '/', realpath($outDir) ?: $outDir) . "\n";
