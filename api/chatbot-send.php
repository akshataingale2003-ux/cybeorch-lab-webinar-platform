<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/chatbot.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
/** @var array<string, mixed> $payload */
$payload = json_decode($raw, true) ?? $_POST;

$message = trim((string) ($payload['message'] ?? ''));
$sessionId = trim((string) ($payload['session_id'] ?? ''));

if ($sessionId === '' || !preg_match('/^[a-f0-9]{32}$/i', $sessionId)) {
    $sessionId = chatbotEnsureSessionId();
}

try {
    $result = processChatbotMessage($message);

    if ($result['success']) {
        saveChatHistory($sessionId, $message, (string) $result['answer'], (string) $result['source']);
    }

    echo json_encode([
        'success' => (bool) $result['success'],
        'answer'  => (string) $result['answer'],
        'source'  => (string) $result['source'],
        'session_id' => $sessionId,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not process your message. Please try again.',
    ]);
}
