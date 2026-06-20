<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/invoice-amc.php';
require_once __DIR__ . '/invoice-amc-branding.php';

function invoiceMailerSendInvoiceCreated(int $invoiceId, array $client, string $pdfPath): array
{
    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    $toEmail = (string) ($client['email'] ?? $invoice['client_email'] ?? '');
    $toName = (string) ($client['name'] ?? $invoice['client_name'] ?? '');
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'Client email missing.'];
    }

    $subject = 'Invoice ' . $invoice['invoice_no'] . ' from ' . invoiceBrandLabel();
    $total = invoiceAmcFormatMoney((float) $invoice['total_amount'], (string) $invoice['currency']);

    $html = invoiceMailerWrap(
        'Invoice Created',
        '<p>Dear ' . htmlspecialchars($toName) . ',</p>'
        . '<p>Your invoice has been generated and is attached to this email.</p>'
        . '<ul style="padding-left:1.2rem">'
        . '<li><strong>Invoice No:</strong> ' . htmlspecialchars((string) $invoice['invoice_no']) . '</li>'
        . '<li><strong>Project:</strong> ' . htmlspecialchars((string) $invoice['project_name']) . '</li>'
        . '<li><strong>Total Amount:</strong> ' . htmlspecialchars($total) . '</li>'
        . '<li><strong>Due:</strong> Payment as per invoice terms</li>'
        . '</ul>'
        . '<p>Thank you for your business.</p>',
        invoiceBrandLabel()
    );

    $text = "Dear {$toName},\n\nYour invoice {$invoice['invoice_no']} has been generated.\nProject: {$invoice['project_name']}\nTotal: {$total}\n\nRegards,\n" . invoiceBrandLabel();

    $result = invoiceMailerDeliver($toEmail, $toName, $subject, $html, $text, $pdfPath, (string) $invoice['invoice_no'] . '.pdf');
    $logId = invoiceAmcLogEmail('invoice', $invoiceId, 'invoice_created', $toEmail, $subject, $result['ok'], $result['error'] ?? null);

    return array_merge($result, ['log_id' => $logId]);
}

function invoiceMailerSendInvoicePaid(int $invoiceId, array $client, string $pdfPath): array
{
    $invoice = invoiceAmcGetInvoice($invoiceId);
    if (!$invoice) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    $toEmail = (string) ($client['email'] ?? $invoice['client_email'] ?? '');
    $toName = (string) ($client['name'] ?? $invoice['client_name'] ?? '');
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'Client email missing.'];
    }

    $subject = 'Payment Received — Invoice ' . $invoice['invoice_no'];
    $total = invoiceAmcFormatMoney((float) $invoice['total_amount'], (string) $invoice['currency']);

    $html = invoiceMailerWrap(
        'Invoice Paid',
        '<p>Dear ' . htmlspecialchars($toName) . ',</p>'
        . '<p>We have received payment for your invoice. A copy is attached for your records.</p>'
        . '<ul style="padding-left:1.2rem">'
        . '<li><strong>Invoice No:</strong> ' . htmlspecialchars((string) $invoice['invoice_no']) . '</li>'
        . '<li><strong>Amount Paid:</strong> ' . htmlspecialchars($total) . '</li>'
        . '</ul>'
        . '<p>Thank you for your business.</p>',
        invoiceBrandLabel()
    );

    $text = "Dear {$toName},\n\nPayment received for invoice {$invoice['invoice_no']}.\nAmount: {$total}\n\nRegards,\n" . invoiceBrandLabel();

    $result = invoiceMailerDeliver($toEmail, $toName, $subject, $html, $text, $pdfPath, (string) $invoice['invoice_no'] . '.pdf');
    $logId = invoiceAmcLogEmail('invoice', $invoiceId, 'invoice_paid', $toEmail, $subject, $result['ok'], $result['error'] ?? null);

    return array_merge($result, ['log_id' => $logId]);
}

function invoiceMailerSendAmcCreated(int $amcId, array $client, string $pdfPath): array
{
    $amc = invoiceAmcGetAmc($amcId);
    if (!$amc) {
        return ['ok' => false, 'error' => 'AMC contract not found.'];
    }

    $toEmail = (string) ($client['email'] ?? $amc['client_email'] ?? '');
    $toName = (string) ($client['name'] ?? $amc['client_name'] ?? '');
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'Client email missing.'];
    }

    $subject = 'AMC Contract ' . $amc['amc_no'] . ' from CYBEORCH';

    $html = invoiceMailerWrap(
        'AMC Contract Created',
        '<p>Dear ' . htmlspecialchars($toName) . ',</p>'
        . '<p>Your Annual Maintenance Contract has been created. The signed contract PDF is attached.</p>'
        . '<ul style="padding-left:1.2rem">'
        . '<li><strong>AMC No:</strong> ' . htmlspecialchars((string) $amc['amc_no']) . '</li>'
        . '<li><strong>Project:</strong> ' . htmlspecialchars((string) $amc['project_name']) . '</li>'
        . '<li><strong>Valid Until:</strong> ' . htmlspecialchars(date('d M Y', strtotime((string) $amc['end_date']))) . '</li>'
        . '</ul>'
    );

    $text = "Dear {$toName},\n\nAMC contract {$amc['amc_no']} created.\nValid until: {$amc['end_date']}\n\nRegards,\nCYBEORCH";

    $result = invoiceMailerDeliver($toEmail, $toName, $subject, $html, $text, $pdfPath, (string) $amc['amc_no'] . '.pdf');
    $logId = invoiceAmcLogEmail('amc', $amcId, 'amc_created', $toEmail, $subject, $result['ok'], $result['error'] ?? null);

    return array_merge($result, ['log_id' => $logId]);
}

function invoiceMailerSendAmcRenewalReminder(int $amcId, array $contract, int $daysBefore): array
{
    $toEmail = (string) ($contract['client_email'] ?? '');
    $toName = (string) ($contract['client_name'] ?? '');
    if ($toEmail === '') {
        return ['ok' => false, 'error' => 'Client email missing.'];
    }

    $subject = 'AMC Renewal Reminder — ' . $contract['amc_no'] . ' (' . $daysBefore . ' days)';
    $endDate = date('d M Y', strtotime((string) $contract['end_date']));

    $html = invoiceMailerWrap(
        'AMC Renewal Reminder',
        '<p>Dear ' . htmlspecialchars($toName) . ',</p>'
        . '<p>This is a reminder that your AMC contract is expiring in <strong>' . (int) $daysBefore . ' day(s)</strong>.</p>'
        . '<ul style="padding-left:1.2rem">'
        . '<li><strong>AMC No:</strong> ' . htmlspecialchars((string) $contract['amc_no']) . '</li>'
        . '<li><strong>Project:</strong> ' . htmlspecialchars((string) $contract['project_name']) . '</li>'
        . '<li><strong>Expiry Date:</strong> ' . htmlspecialchars($endDate) . '</li>'
        . '</ul>'
        . '<p>Please contact us to renew your contract and ensure uninterrupted support.</p>'
    );

    $text = "Dear {$toName},\n\nAMC {$contract['amc_no']} expires on {$endDate} ({$daysBefore} days).\n\nRegards,\nCYBEORCH";

    $pdfPath = (string) ($contract['pdf_path'] ?? '');
    $attach = ($pdfPath !== '' && is_file($pdfPath)) ? $pdfPath : null;

    $result = invoiceMailerDeliver(
        $toEmail,
        $toName,
        $subject,
        $html,
        $text,
        $attach,
        $attach ? (string) $contract['amc_no'] . '.pdf' : null
    );
    $logId = invoiceAmcLogEmail('amc', $amcId, 'amc_renewal_reminder', $toEmail, $subject, $result['ok'], $result['error'] ?? null);

    return array_merge($result, ['log_id' => $logId]);
}

function invoiceMailerWrap(string $title, string $body, string $signOff = 'CYBEORCH — Blockchain Development Division'): string
{
    return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#1a2744;line-height:1.6">'
        . '<h2 style="color:#1a4a8e;margin:0 0 16px;font-size:20px">' . htmlspecialchars($title) . '</h2>'
        . $body
        . '<p style="margin-top:24px">Regards,<br><strong>' . htmlspecialchars($signOff) . '</strong></p></div>';
}

function invoiceMailerDeliver(
    string $toEmail,
    string $toName,
    string $subject,
    string $html,
    string $text,
    ?string $pdfPath = null,
    ?string $attachName = null
): array {
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Recipient email is missing or invalid.';
        mailLog('error', 'invoice_amc', 'Email not sent', [
            'to'      => $toEmail,
            'subject' => $subject,
            'error'   => $error,
        ]);

        return ['ok' => false, 'error' => $error];
    }

    if (!mailerAutoload()) {
        $error = 'PHPMailer not installed. Run: composer install';
        mailLog('error', 'invoice_amc', 'Email not sent', [
            'to'      => $toEmail,
            'subject' => $subject,
            'error'   => $error,
        ]);

        return ['ok' => false, 'error' => $error];
    }

    $issue = smtpConfigurationIssue();
    if ($issue !== null) {
        mailLog('error', 'invoice_amc', 'Email not sent', [
            'to'      => $toEmail,
            'subject' => $subject,
            'error'   => $issue,
        ]);

        return ['ok' => false, 'error' => $issue];
    }

    if ($pdfPath !== null && $pdfPath !== '' && !is_file($pdfPath)) {
        $error = 'Attachment PDF not found: ' . $pdfPath;
        mailLog('error', 'invoice_amc', 'Email not sent', [
            'to'         => $toEmail,
            'subject'    => $subject,
            'attachment' => $pdfPath,
            'error'      => $error,
        ]);

        return ['ok' => false, 'error' => $error];
    }

    $mail = new PHPMailer(true);
    $mail->addAddress($toEmail, $toName);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    if ($pdfPath !== null && is_file($pdfPath)) {
        $mail->addAttachment($pdfPath, $attachName ?? basename($pdfPath));
    }

    $result = deliverPhpMailer($mail);
    mailLog($result['ok'] ? 'info' : 'error', 'invoice_amc', $result['ok'] ? 'Email sent' : 'Email failed', [
        'to'         => $toEmail,
        'subject'    => $subject,
        'attachment' => ($pdfPath !== null && is_file($pdfPath)) ? ($attachName ?? basename($pdfPath)) : null,
        'error'      => $result['error'] ?? null,
    ]);

    return $result;
}
