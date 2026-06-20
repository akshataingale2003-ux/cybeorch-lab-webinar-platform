<?php
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/amc-pdf.php';
requireAdminLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(400);
    echo 'Invalid AMC ID.';
    exit;
}

amcPdfStream($id);
