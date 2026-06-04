<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

logoutUser();
$dest = isPublicAuthEnabled() ? 'login.php?logout=1' : 'index.php';
header('Location: ' . url($dest));
exit;
