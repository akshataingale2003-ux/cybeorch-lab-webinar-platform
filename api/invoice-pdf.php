<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/invoice-pdf.php';
requireAdminLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    echo 'Invalid invoice ID.';
    exit;
}

invoicePdfStream($id);
