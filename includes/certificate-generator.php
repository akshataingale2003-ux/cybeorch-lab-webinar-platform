<?php
declare(strict_types=1);

require_once __DIR__ . '/certificate-template.php';

$cybeorchCertAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($cybeorchCertAutoload)) {
    require_once $cybeorchCertAutoload;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generate PNG/JPG/PDF assets for a certificate record.
 *
 * @param array<string,mixed> $data
 * @return array{ok:bool, png?:string, jpg?:string, pdf?:string, error?:string}
 */
function generateCertificateAssets(array $data): array
{
    if (!extension_loaded('gd')) {
        return ['ok' => false, 'error' => 'PHP GD extension is required for certificate generation.'];
    }

    $template = certificateTemplatePath();
    if (!is_file($template)) {
        return ['ok' => false, 'error' => 'Certificate template image not found.'];
    }

    $certificateId = (string) ($data['certificate_id'] ?? '');
    if ($certificateId === '') {
        return ['ok' => false, 'error' => 'Certificate ID is required.'];
    }

    $userName = certificateDisplayRecipientName(trim((string) ($data['user_name'] ?? '')));
    $eventName = trim((string) ($data['event_name'] ?? ''));
    $eventType = (string) ($data['event_type'] ?? 'webinar');
    $eventDate = (string) ($data['event_date'] ?? date('Y-m-d'));

    try {
        $pngPath = renderCertificateImage($template, [
            'certificate_id' => $certificateId,
            'user_name'      => $userName,
            'event_name'     => $eventName,
            'event_type'     => $eventType,
            'event_date'     => $eventDate,
        ]);

        $jpgPath = preg_replace('/\.png$/i', '.jpg', str_replace(['/png/', '\\png\\'], ['/jpg/', '\\jpg\\'], $pngPath)) ?? $pngPath;
        $jpgDir = dirname($jpgPath);
        if (!is_dir($jpgDir)) {
            @mkdir($jpgDir, 0755, true);
        }
        $pngImg = imagecreatefrompng($pngPath);
        if ($pngImg === false) {
            return ['ok' => false, 'error' => 'Failed to read generated PNG.'];
        }
        imagejpeg($pngImg, $jpgPath, 92);
        imagedestroy($pngImg);

        $pdfPath = preg_replace('/\.png$/i', '.pdf', str_replace(['/png/', '\\png\\'], ['/pdf/', '\\pdf\\'], $pngPath)) ?? $pngPath;
        $pdfDir = dirname($pdfPath);
        if (!is_dir($pdfDir)) {
            @mkdir($pdfDir, 0755, true);
        }
        generateCertificatePdfFromPng($pngPath, $pdfPath);

        return [
            'ok'  => true,
            'png' => certificateRelativePath($pngPath),
            'jpg' => certificateRelativePath($jpgPath),
            'pdf' => certificateRelativePath($pdfPath),
        ];
    } catch (Throwable $e) {
        certificateLog('error', 'Generation failed', [
            'certificate_id' => $certificateId,
            'error'          => $e->getMessage(),
        ]);

        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * @param array<string,string> $fields
 */
function renderCertificateImage(string $templatePath, array $fields): string
{
    $canvas = certificateLoadTemplateImage($templatePath);
    if ($canvas === false) {
        throw new RuntimeException('Unable to load certificate template.');
    }

    imagealphablending($canvas, true);
    imagesavealpha($canvas, true);

    $size = certificateCanvasSize();
    $w = imagesx($canvas);
    $h = imagesy($canvas);
    if ($w !== $size['width'] || $h !== $size['height']) {
        $resized = imagecreatetruecolor($size['width'], $size['height']);
        imagecopyresampled($resized, $canvas, 0, 0, 0, 0, $size['width'], $size['height'], $w, $h);
        imagedestroy($canvas);
        $canvas = $resized;
    }

    $layout = certificateLayoutPlaceholders();
    $colors = certificateColors();
    $navy = imagecolorallocate($canvas, $colors['navy'][0], $colors['navy'][1], $colors['navy'][2]);
    $muted = imagecolorallocate($canvas, $colors['muted'][0], $colors['muted'][1], $colors['muted'][2]);

    $userName = certificateDisplayRecipientName((string) ($fields['user_name'] ?? ''));

    $bgTemplate = certificateLoadTemplateImage($templatePath);
    if ($bgTemplate !== false) {
        if (imagesx($bgTemplate) !== $size['width'] || imagesy($bgTemplate) !== $size['height']) {
            $bgResized = imagecreatetruecolor($size['width'], $size['height']);
            imagecopyresampled($bgResized, $bgTemplate, 0, 0, 0, 0, $size['width'], $size['height'], imagesx($bgTemplate), imagesy($bgTemplate));
            imagedestroy($bgTemplate);
            $bgTemplate = $bgResized;
        }
    }

    $nameFont = certificateFontPath('name');
    $labelFont = certificateFontPath('label');

    $nameCfg = $layout['recipient_name'];
    $nameSize = (int) $nameCfg['size'];
    if ($nameFont !== '' && strlen($userName) > 28) {
        $nameSize = 20;
    } elseif ($nameFont !== '' && strlen($userName) > 22) {
        $nameSize = 22;
    }
    if ($bgTemplate !== false) {
        drawCenteredTextOnTemplate($canvas, $bgTemplate, $userName, $nameFont, $nameSize, (int) $nameCfg['y'], $navy);
    } else {
        drawCenteredText($canvas, $userName, $nameFont, $nameSize, (int) $nameCfg['y'], $navy);
    }

    renderCertificateFooterOnCanvas($canvas, $bgTemplate !== false ? $bgTemplate : null, $fields, $labelFont, $navy, $muted);

    if ($bgTemplate !== false) {
        imagedestroy($bgTemplate);
    }

    $outDir = certificateStorageRoot() . '/png';
    if (!is_dir($outDir)) {
        @mkdir($outDir, 0755, true);
    }
    $outPath = $outDir . '/' . preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($fields['certificate_id'] ?? 'certificate')) . '.png';
    imagepng($canvas, $outPath, 6);
    imagedestroy($canvas);

    return $outPath;
}

/**
 * Draw three-column footer: labels on top row, values below, gold dividers.
 *
 * @param GdImage|resource $canvas
 * @param GdImage|resource|null $template
 * @param array<string, string> $fields
 */
function renderCertificateFooterOnCanvas($canvas, $template, array $fields, string $labelFont, int $navy, int $muted): void
{
    $grid = certificateFooterGrid();
    $columns = certificateFooterColumnBounds();
    $items = certificateFooterMetaItems($fields);
    $colors = certificateColors();
    $gold = imagecolorallocate($canvas, $colors['gold'][0], $colors['gold'][1], $colors['gold'][2]);
    $valueFont = certificateFontPath('label');

    if ($template !== null) {
        certificateRestoreTemplateRegion($canvas, $template, [
            'x1' => $grid['x_start'],
            'y1' => $grid['restore_y1'],
            'x2' => $grid['x_end'],
            'y2' => $grid['restore_y2'],
        ]);
    }

    foreach ($items as $index => $item) {
        $col = $columns[$index] ?? null;
        if ($col === null) {
            continue;
        }

        $lineX1 = (int) $col['x1'] + (int) $grid['padding'];
        $lineX2 = (int) $col['x2'] - (int) $grid['padding'];
        $maxWidth = $col['width'] - ((int) $grid['padding'] * 2);

        drawCenteredAt(
            $canvas,
            (string) $item['label'],
            $labelFont,
            (int) $grid['label_size'],
            (int) $col['center'],
            (int) $grid['y_label'],
            $muted
        );
        imageline($canvas, $lineX1, (int) $grid['y_line'], $lineX2, (int) $grid['y_line'], $gold);

        $valueText = (string) $item['value'];
        $fitSize = (int) $grid['value_size'];
        while ($fitSize > 7 && measureTextWidth($valueFont, $fitSize, $valueText) > $maxWidth) {
            $fitSize--;
        }
        if (!empty($item['wrap']) && measureTextWidth($valueFont, $fitSize, $valueText) > $maxWidth) {
            $valueText = certificateTruncateWithEllipsis($valueText, $valueFont, $fitSize, $maxWidth);
        }
        drawCenteredAt(
            $canvas,
            $valueText,
            $valueFont,
            $fitSize,
            (int) $col['center'],
            (int) $grid['y_value'],
            $navy
        );
    }

    if (!empty($grid['draw_dividers'])) {
        $dividerTop = (int) $grid['divider_top'];
        $dividerBottom = (int) $grid['divider_bottom'];
        foreach (array_slice($columns, 0, -1) as $col) {
            $x = (int) $col['x2'];
            imageline($canvas, $x, $dividerTop, $x, $dividerBottom, $gold);
        }
    }
}

function certificateTruncateWithEllipsis(string $text, string $font, int $size, int $maxWidth): string
{
    $text = trim($text);
    if ($text === '' || measureTextWidth($font, $size, $text) <= $maxWidth) {
        return $text;
    }

    $ellipsis = '...';
    $trimmed = $text;
    while ($trimmed !== '' && measureTextWidth($font, $size, $trimmed . $ellipsis) > $maxWidth) {
        $trimmed = substr($trimmed, 0, -1);
    }

    return $trimmed !== '' ? rtrim($trimmed) . $ellipsis : $ellipsis;
}

/** @param GdImage|resource $canvas */
function drawWrappedCenteredInColumn($canvas, string $text, string $font, int $size, int $centerX, int $y, int $maxWidth, int $color, int $lineHeight, int $maxLines = 2): void
{
    $lines = wrapTextLines($text, $font, $size, $maxWidth);
    if ($lines === []) {
        return;
    }
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $last = (string) $lines[$maxLines - 1];
        while ($last !== '' && measureTextWidth($font, $size, $last . '...') > $maxWidth) {
            $last = substr($last, 0, -1);
        }
        $lines[$maxLines - 1] = rtrim($last) . '...';
    }

    foreach ($lines as $line) {
        drawCenteredAt($canvas, $line, $font, $size, $centerX, $y, $color);
        $y += $lineHeight;
    }
}

/** @param GdImage|resource $canvas */
function drawWrappedLeftText($canvas, string $text, string $font, int $size, int $x, int $y, int $maxWidth, int $color, int $lineHeight, int $maxLines = 2): void
{
    $lines = wrapTextLines($text, $font, $size, $maxWidth);
    if ($lines === []) {
        return;
    }
    if (count($lines) > $maxLines) {
        $lines = array_slice($lines, 0, $maxLines);
        $last = (string) $lines[$maxLines - 1];
        while ($last !== '' && measureTextWidth($font, $size, $last . '...') > $maxWidth) {
            $last = substr($last, 0, -1);
        }
        $lines[$maxLines - 1] = rtrim($last) . '...';
    }

    foreach ($lines as $line) {
        drawLeftText($canvas, $line, $font, $size, $x, $y, $color);
        $y += $lineHeight;
    }
}

/** @param GdImage|resource $canvas */
function certificateRestoreTemplateRegion($canvas, $template, array $zone): void
{
    imagecopy(
        $canvas,
        $template,
        (int) $zone['x1'],
        (int) $zone['y1'],
        (int) $zone['x1'],
        (int) $zone['y1'],
        (int) $zone['x2'] - (int) $zone['x1'],
        (int) $zone['y2'] - (int) $zone['y1']
    );
}

function generateCertificatePdfFromPng(string $pngPath, string $pdfPath): void
{
    if (!is_file(dirname(__DIR__) . '/vendor/autoload.php')) {
        throw new RuntimeException('Composer autoload not found.');
    }
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $imgData = base64_encode((string) file_get_contents($pngPath));
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
        . '@page{margin:0;}html,body{margin:0;padding:0;}img{width:100%;height:auto;display:block;}'
        . '</style></head><body><img src="data:image/png;base64,' . $imgData . '" alt="Certificate"></body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    file_put_contents($pdfPath, $dompdf->output());
}

/** @param GdImage|resource $canvas */
function drawCenteredTextOnTemplate($canvas, $template, string $text, string $font, int $size, int $y, int $color): void
{
    $canvasW = imagesx($canvas);
    $textWidth = measureTextWidth($font, $size, $text);
    $padX = 14;
    $padY = 8;
    $x1 = max(0, (int) (($canvasW - $textWidth) / 2) - $padX);
    $x2 = min($canvasW, (int) (($canvasW + $textWidth) / 2) + $padX);
    $y1 = max(0, $y - $size - $padY);
    $y2 = min(imagesy($canvas), $y + $padY);
    certificateRestoreTemplateRegion($canvas, $template, ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y2]);
    drawCenteredText($canvas, $text, $font, $size, $y, $color);
}

/** @param GdImage|resource $canvas */
function drawCenteredAt($canvas, string $text, string $font, int $size, int $centerX, int $y, int $color): void
{
    if ($font === '' || !function_exists('imagettfbbox')) {
        $textWidth = imagefontwidth(5) * strlen($text);
        $x = (int) ($centerX - ($textWidth / 2));
        imagestring($canvas, 5, max(0, $x), $y - 10, $text, $color);

        return;
    }
    $bbox = imagettfbbox($size, 0, $font, $text);
    $textWidth = abs($bbox[2] - $bbox[0]);
    $x = (int) ($centerX - ($textWidth / 2));
    imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);
}

/** @param GdImage|resource $canvas */
function drawCenteredText($canvas, string $text, string $font, int $size, int $y, int $color): void
{
    if ($font === '' || !function_exists('imagettfbbox')) {
        $textWidth = imagefontwidth(5) * strlen($text);
        $x = (int) ((imagesx($canvas) - $textWidth) / 2);
        imagestring($canvas, 5, max(0, $x), $y - 10, $text, $color);

        return;
    }
    $bbox = imagettfbbox($size, 0, $font, $text);
    $textWidth = abs($bbox[2] - $bbox[0]);
    $x = (int) ((imagesx($canvas) - $textWidth) / 2);
    imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);
}

/** @param GdImage|resource $canvas */
function drawLeftText($canvas, string $text, string $font, int $size, int $x, int $y, int $color): void
{
    if ($font === '' || !function_exists('imagettftext')) {
        imagestring($canvas, 3, $x, $y - 10, $text, $color);

        return;
    }
    imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);
}

/** @param GdImage|resource $canvas */
function drawWrappedCenteredText($canvas, string $text, string $font, int $size, int $y, int $maxWidth, int $color, int $lineHeight): void
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        $width = measureTextWidth($font, $size, $test);
        if ($width > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    foreach ($lines as $line) {
        drawCenteredText($canvas, $line, $font, $size, $y, $color);
        $y += $lineHeight;
    }
}

function measureTextWidth(string $font, int $size, string $text): int
{
    if ($font === '' || !function_exists('imagettfbbox')) {
        return imagefontwidth(5) * strlen($text);
    }
    $bbox = imagettfbbox($size, 0, $font, $text);

    return abs($bbox[2] - $bbox[0]);
}
/** @return list<string> */
function wrapTextLines(string $text, string $font, int $size, int $maxWidth): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $test = $current === '' ? $word : $current . ' ' . $word;
        if (measureTextWidth($font, $size, $test) > $maxWidth && $current !== '') {
            $lines[] = $current;
            $current = $word;
        } else {
            $current = $test;
        }
    }
    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

