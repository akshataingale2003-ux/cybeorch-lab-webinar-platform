<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-amc.php';

$stats = invoiceAmcDashboardStats();
echo 'dashboard_total_clients=' . (int) ($stats['total_clients'] ?? -1) . PHP_EOL;
