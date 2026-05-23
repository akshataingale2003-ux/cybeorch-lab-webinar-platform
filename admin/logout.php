<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

logoutAdmin();
header('Location: ' . url('admin/login.php?logout=1'));
exit;
