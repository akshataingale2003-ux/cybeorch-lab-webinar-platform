<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/recorded-sessions.php';

startSession();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

requireLogin();

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId < 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Login required.']);
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit;
}

$sessionId = (int) ($_POST['session_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$position = (float) ($_POST['position'] ?? 0);
$duration = (float) ($_POST['duration'] ?? 0);

if ($sessionId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid session.']);
    exit;
}

$session = recordedSessionGetById($sessionId);
if (!$session || !recordedSessionUserHasAccess($userId, $session)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'You do not have access to this recording.']);
    exit;
}

$stats = recordedSessionGetWatchStats($userId, $sessionId);
if (!$stats['can_watch'] && $action !== 'progress') {
    http_response_code(403);
    echo json_encode([
        'ok'              => false,
        'message'         => 'You have used all ' . RECORDED_SESSION_MAX_WATCHES . ' full views for this video.',
        'completed_views' => $stats['completed_views'],
        'remaining'       => 0,
    ]);
    exit;
}

$result = match ($action) {
    'progress' => recordedSessionRecordWatchProgress($userId, $sessionId, $position, $duration),
    'complete' => recordedSessionRecordWatchComplete($userId, $sessionId, $position, $duration),
    default    => ['ok' => false, 'completed_views' => $stats['completed_views'], 'remaining' => $stats['remaining'], 'counted' => false, 'message' => 'Unknown action.'],
};

echo json_encode([
    'ok'              => $result['ok'],
    'message'         => $result['message'],
    'completed_views' => $result['completed_views'],
    'remaining'       => $result['remaining'],
    'counted'         => $result['counted'],
]);
