<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();
header('Location: ' . adminUrl('admin/dashboard.php'));
exit;
