<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

logoutAdmin();
header('Location: ' . adminUrl('login.php?logout=1'));
exit;
