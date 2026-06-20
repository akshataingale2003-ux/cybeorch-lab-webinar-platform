<?php
declare(strict_types=1);

/**
 * Document header branding for Invoice & AMC modules (admin UI + PDF).
 */

function invoiceBrandLabel(): string
{
    return 'AMC Management';
}

function invoiceCompanyLegalName(): string
{
    return 'Cyberorch Technologies Pvt. Ltd.';
}

function amcBrandLabel(): string
{
    return 'CYBEORCH';
}

function amcBrandTagline(): string
{
    return 'BLOCKCHAIN DEVELOPMENT DIVISION';
}

function invoiceBrandTagline(): string
{
    return amcBrandTagline();
}

/** Absolute filesystem path to uploads/logo.png */
function invoiceAmcLogoPath(): string
{
    $path = defined('CYBEORCH_APP_ROOT')
        ? rtrim(CYBEORCH_APP_ROOT, '\\/') . '/uploads/logo.png'
        : dirname(__DIR__) . '/uploads/logo.png';
    $resolved = str_replace('\\', '/', $path);
    if ($resolved !== '' && is_file($resolved) && is_readable($resolved)) {
        return $resolved;
    }

    return '';
}

/** Public browser URL for uploads/logo.png */
function invoiceAmcLogoUrl(): string
{
    if (invoiceAmcLogoPath() === '') {
        return '';
    }

    if (function_exists('urlVersioned')) {
        return urlVersioned('uploads/logo.png');
    }

    return function_exists('url') ? url('uploads/logo.png') : '/uploads/logo.png';
}

/** Base64 data URI for PDF / print embedding (no remote fetch). */
function invoicePdfLogoDataUri(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $path = invoiceAmcLogoPath();
    if ($path === '') {
        $cached = '';
        return '';
    }

    $bytes = file_get_contents($path);
    if ($bytes === false || $bytes === '') {
        $cached = '';
        return '';
    }

    $mime = 'image/png';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($detected) && str_starts_with($detected, 'image/')) {
                $mime = $detected;
            }
        }
    }

    $cached = 'data:' . $mime . ';base64,' . base64_encode($bytes);
    return $cached;
}

/**
 * Shared document header HTML (invoice template branding).
 *
 * @param 'invoice'|'amc' $docType
 */
function invoiceAmcBrandHeaderHtml(string $docType, ?string $logoSrc = null): string
{
    $blue = '#1a4a8e';
    $grey = '#666666';
    $rightLabel = $docType === 'amc' ? 'AMC CONTRACT' : 'INVOICE';
    $rightSize = $docType === 'amc' ? '28px' : '34px';

    $logo = $logoSrc ?? invoicePdfLogoDataUri();
    $logoHtml = $logo !== ''
        ? '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars(amcBrandLabel()) . '" style="height:64px;width:auto;max-width:96px;display:block;object-fit:contain">'
        : '<div style="width:96px;height:64px"></div>';
    $brandName = $docType === 'invoice' ? amcBrandLabel() : amcBrandLabel();
    $brandTagline = $docType === 'invoice' ? invoiceBrandTagline() : amcBrandTagline();
    $leftHtml = '<table style="border-collapse:collapse"><tr>
      <td style="vertical-align:middle;padding-right:14px">' . $logoHtml . '</td>
      <td style="vertical-align:middle">
        <div style="font-size:26px;font-weight:700;color:#000;letter-spacing:0.5px;line-height:1.1">' . htmlspecialchars($brandName) . '</div>
        <div style="font-size:11px;color:' . $grey . ';letter-spacing:1.5px;margin-top:4px;text-transform:uppercase">' . htmlspecialchars($brandTagline) . '</div>
      </td>
    </tr></table>';

    return '<table style="width:100%;border-collapse:collapse;margin-bottom:24px">
    <tr>
      <td style="vertical-align:top;width:65%">' . $leftHtml . '</td>
      <td style="vertical-align:top;text-align:right">
        <div style="font-size:' . $rightSize . ';font-weight:700;color:' . $blue . ';letter-spacing:1px">' . htmlspecialchars($rightLabel) . '</div>
      </td>
    </tr>
  </table>';
}

/**
 * Admin page brand banner (matches PDF header on white background).
 *
 * @param 'invoice'|'amc' $docType
 */
function invoiceAmcRenderAdminBrandHeader(string $docType): void
{
    $rightLabel = $docType === 'amc' ? 'AMC CONTRACT' : 'INVOICE';
    $rightClass = $docType === 'amc' ? 'invoice-amc-brand-doc--amc' : 'invoice-amc-brand-doc--invoice';
    $ariaLabel = amcBrandLabel() . ' document branding';
    $logoUrl = invoiceAmcLogoUrl();
    ?>
<div class="invoice-amc-brand-header mb-4" role="banner" aria-label="<?= htmlspecialchars($ariaLabel) ?>">
  <div class="invoice-amc-brand-left">
    <?php if ($logoUrl !== ''): ?>
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars(amcBrandLabel()) ?>" class="invoice-amc-brand-logo">
    <?php else: ?>
    <div class="invoice-amc-brand-logo-placeholder" aria-hidden="true"></div>
    <?php endif; ?>
    <div class="invoice-amc-brand-text">
      <div class="invoice-amc-brand-name"><?= htmlspecialchars(amcBrandLabel()) ?></div>
      <div class="invoice-amc-brand-tagline"><?= htmlspecialchars($docType === 'invoice' ? invoiceBrandTagline() : amcBrandTagline()) ?></div>
    </div>
  </div>
  <div class="invoice-amc-brand-doc <?= htmlspecialchars($rightClass) ?>"><?= htmlspecialchars($rightLabel) ?></div>
</div>
    <?php
}

function invoiceAmcBrandHeaderCss(): string
{
    return '.invoice-amc-brand-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:28px 36px;background:#fff;color:#000;border:1px solid rgba(0,212,255,0.15);border-radius:8px}'
        . '.invoice-amc-brand-left{display:flex;align-items:center;gap:12px;min-width:0}'
        . '.invoice-amc-brand-logo{display:block;height:64px;width:auto;max-width:96px;object-fit:contain;flex-shrink:0;background:transparent}'
        . '.invoice-amc-brand-logo-placeholder{width:96px;height:64px;flex-shrink:0}'
        . '.invoice-amc-brand-name{font-size:26px;font-weight:700;letter-spacing:0.5px;line-height:1.1;color:#000}'
        . '.invoice-amc-brand-tagline{font-size:11px;color:#666;letter-spacing:1.5px;margin-top:4px;text-transform:uppercase}'
        . '.invoice-amc-brand-doc{font-weight:700;color:#1a4a8e;letter-spacing:1px;white-space:nowrap;flex-shrink:0}'
        . '.invoice-amc-brand-doc--invoice{font-size:34px}'
        . '.invoice-amc-brand-doc--amc{font-size:28px}'
        . '@media (max-width:640px){.invoice-amc-brand-header{flex-direction:column;padding:20px}.invoice-amc-brand-doc{align-self:flex-end}.invoice-amc-brand-doc--invoice{font-size:28px}.invoice-amc-brand-doc--amc{font-size:24px}}'
        . '@media print{.invoice-amc-brand-header{border:1px solid #ccc;break-inside:avoid}.invoice-amc-brand-logo{-webkit-print-color-adjust:exact;print-color-adjust:exact}}';
}

function invoiceAmcBrandHeaderStyles(): string
{
    return '<style>' . invoiceAmcBrandHeaderCss() . '</style>';
}
