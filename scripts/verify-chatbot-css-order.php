<?php
declare(strict_types=1);

$html = (string) file_get_contents('http://localhost/cybeorch_final_project/about.php');
$cssPos = strpos($html, 'chatbot-widget.css');
$rootPos = strpos($html, 'cybeorch-chatbot-root');
echo 'css position: ' . $cssPos . PHP_EOL;
echo 'root position: ' . $rootPos . PHP_EOL;
echo 'css in head before widget: ' . ($cssPos !== false && $rootPos !== false && $cssPos < $rootPos ? 'YES' : 'NO') . PHP_EOL;

$css = (string) file_get_contents('http://localhost/cybeorch_final_project/assets/css/chatbot-widget.css');
echo 'red theme in css: ' . (str_contains($css, '#ff3b30') ? 'YES' : 'NO') . PHP_EOL;
