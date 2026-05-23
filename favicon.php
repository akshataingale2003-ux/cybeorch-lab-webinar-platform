<?php
declare(strict_types=1);

$root = __DIR__;
$logo = $root . '/assets/images/logo.jpeg';
$png  = $root . '/favicon.png';
$file = is_file($logo) ? $logo : (is_file($png) ? $png : '');

if ($file === '') {
    http_response_code(404);
    exit;
}

$mime = str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . (string) filesize($file));
readfile($file);
exit;
