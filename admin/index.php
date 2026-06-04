<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();
header('Location: ' . adminUrl('dashboard.php'));
exit;
