<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/nxl-wallet.php';
require_once __DIR__ . '/../includes/certificate-system.php';
require_once __DIR__ . '/../includes/webinar-attendance.php';

header('Content-Type: application/json; charset=utf-8');

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request token.']);
    exit;
}

$regId = (int) ($_POST['registration_id'] ?? 0);
if ($regId < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Registration ID required.']);
    exit;
}

$adminId = (int) ($_SESSION['admin_id'] ?? 0);
$result = markWebinarRegistrationAttended($regId, $adminId > 0 ? $adminId : null);

$attendanceCount = webinarAttendanceCount();

echo json_encode([
    'success'          => (bool) ($result['success'] ?? false),
    'message'          => (string) ($result['message'] ?? ''),
    'attendance_id'    => (int) ($result['attendance_id'] ?? 0),
    'attendance_count' => $attendanceCount,
    'certificate_issued' => !empty($result['certificate_issued']),
    'granted'          => !empty($result['granted']),
]);
