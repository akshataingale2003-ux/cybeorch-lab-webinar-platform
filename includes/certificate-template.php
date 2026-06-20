<?php
declare(strict_types=1);

/**
 * CYBEORCH LABS certificate master template (assets/images/certificate.png).
 * Only dynamic fields are drawn on top — the design itself is never modified.
 */
function certificateTemplatePath(): string
{
    return dirname(__DIR__) . '/assets/images/certificate.png';
}

function certificateStorageRoot(): string
{
    return dirname(__DIR__) . '/uploads/certificates';
}

/** @return array{width:int,height:int} */
function certificateCanvasSize(): array
{
    static $size = null;
    if ($size !== null) {
        return $size;
    }

    $path = certificateTemplatePath();
    if (is_file($path)) {
        $info = @getimagesize($path);
        if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
            $size = ['width' => (int) $info[0], 'height' => (int) $info[1]];

            return $size;
        }
    }

    $size = ['width' => 1024, 'height' => 682];

    return $size;
}

/**
 * Pixel baselines for dynamic text on the master template.
 *
 * @return array<string, mixed>
 */
function certificateLayoutPlaceholders(): array
{
    $width = certificateCanvasSize()['width'];

    return [
        'recipient_name' => [
            'x'    => (int) ($width / 2),
            'y'    => 322,
            'size' => 24,
        ],
        'event_info' => certificateFooterGrid(),
    ];
}

/**
 * Three-column footer grid — labels row, gold lines, values row.
 * Order: CERTIFICATE ID | EVENT DATE | EVENT NAME
 *
 * @return array<string, int|bool>
 */
function certificateFooterGrid(): array
{
    return [
        // Compact footer — centered between left edge and seal (1024×682 template)
        'x_start'        => 142,
        'x_end'          => 482,
        'columns'        => 3,
        'y_label'        => 586,
        'y_line'         => 598,
        'y_value'        => 613,
        'label_size'     => 7,
        'value_size'     => 8,
        'padding'        => 7,
        'restore_y1'     => 578,
        'restore_y2'     => 628,
        'draw_dividers'  => true,
        'divider_top'    => 582,
        'divider_bottom' => 626,
    ];
}

/** @return list<array{x1:int,x2:int,center:int,width:int}> */
function certificateFooterColumnBounds(): array
{
    $grid = certificateFooterGrid();
    $colWidth = (int) floor(($grid['x_end'] - $grid['x_start']) / $grid['columns']);
    $columns = [];
    for ($i = 0; $i < $grid['columns']; $i++) {
        $x1 = $grid['x_start'] + ($i * $colWidth);
        $x2 = ($i === $grid['columns'] - 1) ? $grid['x_end'] : $x1 + $colWidth;
        $columns[] = [
            'x1'     => $x1,
            'x2'     => $x2,
            'center' => (int) (($x1 + $x2) / 2),
            'width'  => $x2 - $x1,
        ];
    }

    return $columns;
}

/**
 * Footer columns left-to-right: Certificate ID | Event Date | Event Name.
 *
 * @param array<string, string> $fields
 * @return list<array{label:string,value:string,wrap:bool}>
 */
function certificateFooterMetaItems(array $fields): array
{
    $eventType = (string) ($fields['event_type'] ?? 'webinar');
    $isBootcamp = $eventType === 'bootcamp';

    return [
        [
            'label' => 'CERTIFICATE ID',
            'value' => trim((string) ($fields['certificate_id'] ?? '')),
            'wrap'  => false,
        ],
        [
            'label' => $isBootcamp ? 'BOOTCAMP DATE' : 'WEBINAR DATE',
            'value' => certificateFormatDate((string) ($fields['event_date'] ?? '')),
            'wrap'  => false,
        ],
        [
            'label' => $isBootcamp ? 'BOOTCAMP NAME' : 'WEBINAR NAME',
            'value' => trim((string) ($fields['event_name'] ?? '')),
            'wrap'  => true,
        ],
    ];
}

/** @deprecated Use certificateFooterMetaItems() */
function certificateEventInfoLines(array $fields): array
{
    return certificateFooterMetaItems($fields);
}

/** Display name on certificate (trimmed, title-style). */
function certificateDisplayRecipientName(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }

    return function_exists('mb_convert_case')
        ? mb_convert_case($name, MB_CASE_TITLE, 'UTF-8')
        : ucwords(strtolower($name));
}

/** Navy palette matching the CYBEORCH LABS certificate design. */
function certificateColors(): array
{
    return [
        'navy'  => [0, 31, 63],
        'gold'  => [197, 160, 89],
        'muted' => [60, 74, 96],
    ];
}

function certificateFontPath(string $role): string
{
    $candidates = match ($role) {
        'name' => [
            dirname(__DIR__) . '/assets/fonts/Cinzel-Bold.ttf',
            'C:/Windows/Fonts/georgiab.ttf',
            'C:/Windows/Fonts/timesbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf',
        ],
        'body', 'label' => [
            'C:/Windows/Fonts/arialbd.ttf',
            dirname(__DIR__) . '/assets/fonts/OpenSans-Regular.ttf',
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
        ],
        default => [
            'C:/Windows/Fonts/arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        ],
    };

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

function certificateVerifyUrl(string $certificateId): string
{
    $base = trim((string) (defined('CERTIFICATE_VERIFY_URL') ? CERTIFICATE_VERIFY_URL : ''));
    if ($base === '') {
        $base = 'https://cybeorch.com/verify-certificate';
    }
    $base = rtrim($base, '/');

    return $base . '?id=' . rawurlencode($certificateId);
}

/** Short date for dashboards: 15 Jun 2026 */
function certificateFormatDate(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }

    return date('d M Y', $ts);
}

/** Long date for certificate image: 15 June 2026 */
function certificateFormatDateLong(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }

    return date('d F Y', $ts);
}

function certificateRelativePath(string $absolutePath): string
{
    $root = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    $path = str_replace('\\', '/', $absolutePath);
    if (str_starts_with($path, $root . '/')) {
        return substr($path, strlen($root) + 1);
    }

    return ltrim($path, '/');
}

function certificateAbsolutePath(?string $relativePath): ?string
{
    if ($relativePath === null || trim($relativePath) === '') {
        return null;
    }
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

    return dirname(__DIR__) . '/' . $relativePath;
}

function certificateLog(string $level, string $message, array $extra = []): void
{
    $dir = dirname(__DIR__) . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $line = date('Y-m-d H:i:s') . ' [' . strtoupper($level) . '] ' . $message;
    if ($extra !== []) {
        $line .= ' ' . json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($dir . '/certificate.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * @return GdImage|false
 */
function certificateLoadTemplateImage(string $path)
{
    if (!is_file($path)) {
        return false;
    }

    $info = @getimagesize($path);
    if (!is_array($info)) {
        return false;
    }

    return match ($info['mime'] ?? '') {
        'image/png'  => @imagecreatefrompng($path),
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default      => @imagecreatefromstring((string) file_get_contents($path)),
    };
}
