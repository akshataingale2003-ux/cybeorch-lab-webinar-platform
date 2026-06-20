<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

logoutAdmin();
header('Location: ' . adminLoginUrl(['logout' => '1']));
exit;
