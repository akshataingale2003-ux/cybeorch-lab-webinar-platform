<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/company-content.php';

$pdfPath = companyProfilePdfPath();
if ($pdfPath === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $home = htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8');
    $about = htmlspecialchars(url('about.php'), ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Company Profile</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#050b18;color:#e0e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1.5rem}'
        . '.box{max-width:520px;text-align:center;border:1px solid rgba(0,212,255,.25);border-radius:12px;padding:2rem;background:rgba(15,52,96,.45)}'
        . 'a{color:#00d4ff}</style></head><body><div class="box">'
        . '<h1 style="margin-top:0">Company Profile</h1>'
        . '<p>The company profile PDF is not available yet. Please check back soon or visit our About page.</p>'
        . '<p><a href="' . $about . '">About Us</a> &middot; <a href="' . $home . '">Home</a></p>'
        . '</div></body></html>';
    exit;
}

$fullPath = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pdfPath);
$filename = basename($fullPath);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($fullPath));
header('Cache-Control: private, max-age=3600');
readfile($fullPath);
exit;
