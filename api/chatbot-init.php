<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/chatbot.php';

startSession();

try {
    ensureChatbotSchema();
    $settings = getChatbotSettings();
    $sessionId = chatbotEnsureSessionId();

    echo json_encode([
        'success'    => true,
        'enabled'    => ($settings['chatbot_enabled'] ?? '0') === '1',
        'bot_name'   => (string) ($settings['chatbot_bot_name'] ?? 'Assistant'),
        'welcome'    => (string) ($settings['chatbot_welcome_message'] ?? ''),
        'session_id' => $sessionId,
        'ai_enabled' => ($settings['chatbot_ai_enabled'] ?? '0') === '1',
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Chatbot initialization failed.',
    ]);
}
