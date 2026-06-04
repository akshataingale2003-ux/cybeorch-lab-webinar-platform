<?php
/**
 * Run once in browser to download Font Awesome for offline use.
 * Visit `/install_fontawesome.php` after deployment/setup.
 */
declare(strict_types=1);

$root = __DIR__ . '/assets/vendor/fontawesome';
$cssDir = $root . '/css';
$wfDir = $root . '/webfonts';
$base = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2';

foreach ([$cssDir, $wfDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        exit('Cannot create directory: ' . $dir);
    }
}

$files = [
    'css/all.min.css' => $cssDir . '/all.min.css',
    'webfonts/fa-solid-900.woff2' => $wfDir . '/fa-solid-900.woff2',
    'webfonts/fa-solid-900.ttf' => $wfDir . '/fa-solid-900.ttf',
    'webfonts/fa-regular-400.woff2' => $wfDir . '/fa-regular-400.woff2',
    'webfonts/fa-regular-400.ttf' => $wfDir . '/fa-regular-400.ttf',
    'webfonts/fa-brands-400.woff2' => $wfDir . '/fa-brands-400.woff2',
    'webfonts/fa-brands-400.ttf' => $wfDir . '/fa-brands-400.ttf',
];

$ctx = stream_context_create(['http' => ['timeout' => 60]]);
$ok = 0;
foreach ($files as $remote => $local) {
    $url = $base . '/' . $remote;
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) {
        echo "FAILED: $url\n";
        continue;
    }
    file_put_contents($local, $data);
    echo "OK: $remote\n";
    $ok++;
}

echo "\nDownloaded $ok / " . count($files) . " files.\n";
echo $ok === count($files)
    ? "Icons are ready. Delete install_fontawesome.php and refresh your site.\n"
    : "Check internet connection and run again.\n";
