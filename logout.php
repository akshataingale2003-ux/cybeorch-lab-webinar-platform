<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

logoutUser();
header('Location: ' . url('login.php?logout=1'));
exit;
