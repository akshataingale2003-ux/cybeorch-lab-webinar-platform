<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$slug = sanitize($_GET['slug'] ?? '');
$b = $slug ? db()->fetchOne('SELECT * FROM bootcamps WHERE slug = ?', [$slug]) : null;
if (!$b) {
    header('Location: ' . url('bootcamps.php'));
    exit;
}
header('Location: ' . url('checkout.php?type=bootcamp&id=' . $b['id']));
exit;
