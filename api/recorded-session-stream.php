<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/recorded-sessions.php';

startSession();

$sessionId = (int) ($_GET['id'] ?? 0);
if ($sessionId < 1) {
    http_response_code(400);
    exit('Invalid session.');
}

$session = recordedSessionGetById($sessionId, recordedSessionAdminHasAccess());
if (!$session || empty($session['video_path'])) {
    http_response_code(404);
    exit('Recording not found.');
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!recordedSessionCanStream($userId, $session)) {
    http_response_code(403);
    if (!recordedSessionUserHasAccess($userId, $session)) {
        exit('Access expired or you are not enrolled in this course.');
    }
    exit('You have reached the maximum number of full views for this video.');
}

$absolute = recordedSessionAbsolutePath((string) $session['video_path']);
if ($absolute === null) {
    http_response_code(404);
    exit('Video file not found.');
}

recordedSessionStreamFile($absolute, (string) ($session['video_mime'] ?? 'video/mp4'));
