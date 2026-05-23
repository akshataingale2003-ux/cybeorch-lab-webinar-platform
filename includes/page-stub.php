<?php
function redirectToHome(string $anchor = ''): void {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/helpers.php';
    $target = url('') . $anchor;
    header('Location: ' . $target);
    exit;
}
