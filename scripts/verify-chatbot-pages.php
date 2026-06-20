<?php
declare(strict_types=1);

$pages = [
    'index.php',
    'about.php',
    'contact.php',
    'webinars.php',
    'bootcamps.php',
    'dashboard.php',
];

$base = 'http://localhost/cybeorch_final_project/';

foreach ($pages as $page) {
    $url = $base . $page;
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $html = @file_get_contents($url, false, $ctx);
    if ($html === false) {
        echo "$page: FETCH FAILED\n";
        continue;
    }
    $hasRoot = str_contains($html, 'cybeorch-chatbot-root');
    $hasCss = str_contains($html, 'chatbot-widget.css');
    $hasJs = str_contains($html, 'chatbot-widget.js');
    $hasToggle = str_contains($html, 'cybChatbotToggle');
    $hasCacheBust = (bool) preg_match('/chatbot-widget\.css\?v=\d+/', $html);
    echo "$page: root=" . ($hasRoot ? 'YES' : 'NO')
        . ' css=' . ($hasCss ? 'YES' : 'NO')
        . ' js=' . ($hasJs ? 'YES' : 'NO')
        . ' toggle=' . ($hasToggle ? 'YES' : 'NO')
        . ' cacheBust=' . ($hasCacheBust ? 'YES' : 'NO')
        . ' bytes=' . strlen($html) . "\n";
}
