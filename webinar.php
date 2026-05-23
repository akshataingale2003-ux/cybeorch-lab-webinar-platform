<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$slug = sanitize($_GET['slug'] ?? '');
$w = $slug ? db()->fetchOne('SELECT * FROM webinars WHERE slug = ?', [$slug]) : null;
if (!$w) {
    header('Location: ' . url('webinars.php'));
    exit;
}
header('Location: ' . url('checkout.php?type=webinar&id=' . $w['id']));
exit;
