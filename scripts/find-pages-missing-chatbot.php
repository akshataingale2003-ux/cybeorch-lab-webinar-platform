<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$hooks = [
    'renderPublicFooter',
    'renderSiteScripts',
    'renderStudentLayoutEnd',
    'renderLegalPublicPageEnd',
    'renderAdminPageEnd',
    'renderTrainingPublicFooter',
];

$missing = [];
foreach (glob($root . '/*.php') as $file) {
    $base = basename($file);
    if (in_array($base, ['composer-setup.php', 'favicon.php'], true)) {
        continue;
    }
    $content = (string) file_get_contents($file);
    if (!str_contains($content, '</html>')) {
        continue;
    }
    $hasHook = false;
    foreach ($hooks as $hook) {
        if (str_contains($content, $hook)) {
            $hasHook = true;
            break;
        }
    }
    if (!$hasHook) {
        $missing[] = $base;
    }
}

echo "Pages without chatbot hooks:\n";
echo $missing === [] ? "(none)\n" : implode("\n", $missing) . "\n";
