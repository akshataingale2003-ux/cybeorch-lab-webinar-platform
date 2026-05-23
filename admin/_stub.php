<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();
header('Location: ' . url('admin/dashboard.php'));
exit;
