<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/certificate-system.php';

startSession();
ensureCertificatesSchema();

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'download');
$format = strtolower((string) ($_GET['format'] ?? 'pdf'));
$certificateId = trim((string) ($_GET['id'] ?? $_POST['id'] ?? ''));

if ($certificateId === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Certificate ID required.']);
    exit;
}

$cert = findCertificateByPublicId($certificateId);
if (!$cert) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Certificate not found.']);
    exit;
}

$isAdmin = !empty($_SESSION['admin_id']);
$isOwner = !empty($_SESSION['user_id']) && (int) $_SESSION['user_id'] === (int) $cert['user_id'];

if (!$isAdmin && !$isOwner) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

if ($action === 'download' || $action === 'view') {
    streamCertificateDownload($cert, $format, $action === 'view');
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    $dbId = (int) ($cert['id'] ?? 0);
    if ($action === 'regenerate') {
        $result = regenerateCertificate($dbId);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $result['success'], 'message' => $result['message']]);
        exit;
    }
    if ($action === 'resend') {
        $result = resendCertificateEmail($dbId);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $result['success'], 'message' => $result['message']]);
        exit;
    }
}

http_response_code(400);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
