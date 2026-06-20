<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** @return array<string, string> */
function chatbotSettingsDefaults(): array
{
    return [
        'chatbot_enabled'       => '1',
        'chatbot_ai_enabled'    => '1',
        'chatbot_api_provider'  => 'gemini',
        'chatbot_api_key'       => '',
        'chatbot_welcome_message' => 'Hi! I\'m the CYBEORCH assistant. Ask me about webinars, bootcamps, payments, or anything else.',
        'chatbot_offline_message' => 'Thanks for your question! Our team will get back to you soon. For urgent help, visit the Support Desk.',
        'chatbot_bot_name'      => 'CYBEORCH Assistant',
    ];
}

function ensureChatbotSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute('CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS chatbot_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question VARCHAR(500) NOT NULL,
        answer TEXT NOT NULL,
        keywords VARCHAR(500) DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_chatbot_active (is_active),
        INDEX idx_chatbot_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS chat_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(64) NOT NULL,
        user_id INT DEFAULT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        source ENUM(\'qa\',\'ai\',\'offline\') NOT NULL DEFAULT \'offline\',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chat_history_session (session_id),
        INDEX idx_chat_history_created (created_at),
        INDEX idx_chat_history_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    seedDefaultChatbotQa();
    seedDefaultChatbotSettings();
}

function seedDefaultChatbotSettings(): void
{
    $defaults = chatbotSettingsDefaults();
    foreach ($defaults as $key => $value) {
        $exists = db()->fetchOne('SELECT setting_key FROM settings WHERE setting_key = ? LIMIT 1', [$key]);
        if (!$exists) {
            db()->execute(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)',
                [$key, $value]
            );
        }
    }
}

function seedDefaultChatbotQa(): void
{
    $count = (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM chatbot_data')['c'] ?? 0);
    if ($count > 0) {
        return;
    }

    $pairs = [
        [
            'question' => 'What is CYBEORCH?',
            'answer'   => 'CYBEORCH LABS is a cybersecurity and technology training platform offering webinars, bootcamps, live projects, and internship opportunities with real-world exposure.',
            'keywords' => 'about,company,who,cybeorch',
        ],
        [
            'question' => 'How do I register for a webinar?',
            'answer'   => 'Create a free account, browse upcoming webinars, and complete checkout. You will receive confirmation on your dashboard and by email.',
            'keywords' => 'webinar,register,signup,enroll',
        ],
        [
            'question' => 'What payment methods do you accept?',
            'answer'   => 'We support secure online payments through our payment gateway including UPI, cards, and net banking where enabled.',
            'keywords' => 'payment,pay,upi,card,refund',
        ],
        [
            'question' => 'How can I contact support?',
            'answer'   => 'Visit our Support Desk at supportdesk.php or use the Contact page. You can also raise a ticket from your dashboard after signing in.',
            'keywords' => 'support,help,contact,ticket',
        ],
    ];

    foreach ($pairs as $i => $pair) {
        db()->insert(
            'INSERT INTO chatbot_data (question, answer, keywords, is_active, sort_order) VALUES (?, ?, ?, 1, ?)',
            [$pair['question'], $pair['answer'], $pair['keywords'], $i + 1]
        );
    }
}

/** @return array<string, string> */
function getChatbotSettings(): array
{
    ensureChatbotSchema();
    $settings = chatbotSettingsDefaults();
    $keys = array_keys($settings);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $rows = db()->fetchAll(
        "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)",
        $keys
    );
    foreach ($rows as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key !== '' && array_key_exists($key, $settings)) {
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    }
    return $settings;
}

/** @param array<string, mixed> $input */
function saveChatbotSettings(array $input): array
{
    ensureChatbotSchema();

    $provider = strtolower(trim((string) ($input['chatbot_api_provider'] ?? 'gemini')));
    if (!in_array($provider, ['gemini', 'openai'], true)) {
        return ['success' => false, 'message' => 'Invalid AI provider. Choose Gemini or OpenAI.'];
    }

    $apiKey = trim((string) ($input['chatbot_api_key'] ?? ''));
    if ($apiKey === '') {
        $existing = getChatbotSettings();
        $apiKey = trim((string) ($existing['chatbot_api_key'] ?? ''));
    }

    $welcome = trim((string) ($input['chatbot_welcome_message'] ?? ''));
    $offline = trim((string) ($input['chatbot_offline_message'] ?? ''));
    $botName = trim((string) ($input['chatbot_bot_name'] ?? ''));

    if ($welcome === '' || $offline === '' || $botName === '') {
        return ['success' => false, 'message' => 'Bot name, welcome message, and offline message are required.'];
    }

    $values = [
        'chatbot_enabled'         => !empty($input['chatbot_enabled']) ? '1' : '0',
        'chatbot_ai_enabled'      => !empty($input['chatbot_ai_enabled']) ? '1' : '0',
        'chatbot_api_provider'    => $provider,
        'chatbot_api_key'         => $apiKey,
        'chatbot_welcome_message' => $welcome,
        'chatbot_offline_message' => $offline,
        'chatbot_bot_name'        => $botName,
    ];

    foreach ($values as $key => $value) {
        db()->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
            [$key, $value]
        );
    }

    return ['success' => true, 'message' => 'Chatbot settings saved successfully.'];
}

function chatbotIsEnabled(): bool
{
    try {
        $settings = getChatbotSettings();
        return ($settings['chatbot_enabled'] ?? '0') === '1';
    } catch (Throwable $e) {
        return false;
    }
}

function normalizeChatbotText(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return trim($text);
}

/** @return array<string, mixed>|null */
function findChatbotAnswer(string $question): ?array
{
    ensureChatbotSchema();
    $normalized = normalizeChatbotText($question);
    if ($normalized === '') {
        return null;
    }

    $rows = db()->fetchAll(
        'SELECT id, question, answer, keywords FROM chatbot_data WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
    );

    $best = null;
    $bestScore = 0;

    foreach ($rows as $row) {
        $qNorm = normalizeChatbotText((string) $row['question']);
        $score = 0;

        if ($qNorm === $normalized) {
            $score = 100;
        } elseif ($qNorm !== '' && (str_contains($normalized, $qNorm) || str_contains($qNorm, $normalized))) {
            $score = 80;
        } else {
            similar_text($normalized, $qNorm, $pct);
            if ($pct >= 65) {
                $score = (int) $pct;
            }
        }

        if ($score < 70) {
            $keywords = array_filter(array_map('trim', explode(',', (string) ($row['keywords'] ?? ''))));
            foreach ($keywords as $keyword) {
                $kw = normalizeChatbotText($keyword);
                if ($kw !== '' && str_contains($normalized, $kw)) {
                    $score = max($score, 75);
                    break;
                }
            }
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $row;
        }
    }

    if ($best !== null && $bestScore >= 70) {
        return [
            'answer' => (string) $best['answer'],
            'source' => 'qa',
            'qa_id'  => (int) $best['id'],
        ];
    }

    return null;
}

function callChatbotAi(string $question): array
{
    $settings = getChatbotSettings();
    if (($settings['chatbot_ai_enabled'] ?? '0') !== '1') {
        return [
            'success' => false,
            'message' => (string) ($settings['chatbot_offline_message'] ?? 'AI responses are currently disabled.'),
        ];
    }

    $apiKey = trim((string) ($settings['chatbot_api_key'] ?? ''));
    if ($apiKey === '') {
        return [
            'success' => false,
            'message' => (string) ($settings['chatbot_offline_message'] ?? 'AI is not configured yet.'),
        ];
    }

    $provider = strtolower((string) ($settings['chatbot_api_provider'] ?? 'gemini'));
    $systemPrompt = 'You are a helpful assistant for CYBEORCH LABS, a cybersecurity and technology training platform. '
        . 'Answer briefly and professionally about webinars, bootcamps, live projects, payments, support, and careers. '
        . 'If you do not know something specific, suggest visiting supportdesk.php or contact.php.';

    try {
        if ($provider === 'openai') {
            return callChatbotOpenAi($apiKey, $systemPrompt, $question);
        }
        return callChatbotGemini($apiKey, $systemPrompt, $question);
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => (string) ($settings['chatbot_offline_message'] ?? 'Sorry, I could not process your request right now.'),
        ];
    }
}

function callChatbotGemini(string $apiKey, string $systemPrompt, string $question): array
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . rawurlencode($apiKey);
    $payload = [
        'contents' => [
            [
                'role'  => 'user',
                'parts' => [
                    ['text' => $systemPrompt . "\n\nUser question: " . $question],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature'     => 0.4,
            'maxOutputTokens' => 512,
        ],
    ];

    $response = chatbotHttpPostJson($url, $payload, []);
    $text = trim((string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    if ($text === '') {
        throw new RuntimeException('Empty Gemini response');
    }

    return ['success' => true, 'answer' => $text];
}

function callChatbotOpenAi(string $apiKey, string $systemPrompt, string $question): array
{
    $url = 'https://api.openai.com/v1/chat/completions';
    $payload = [
        'model'       => 'gpt-4o-mini',
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $question],
        ],
        'temperature' => 0.4,
        'max_tokens'  => 512,
    ];

    $response = chatbotHttpPostJson($url, $payload, [
        'Authorization: Bearer ' . $apiKey,
    ]);
    $text = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
    if ($text === '') {
        throw new RuntimeException('Empty OpenAI response');
    }

    return ['success' => true, 'answer' => $text];
}

/** @param array<string, mixed> $payload */
function chatbotHttpPostJson(string $url, array $payload, array $extraHeaders): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize HTTP client');
    }

    $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . $error);
    }

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($body, true) ?? [];
    if ($status < 200 || $status >= 300) {
        $msg = (string) ($decoded['error']['message'] ?? $decoded['error']['status'] ?? 'API error');
        throw new RuntimeException($msg !== '' ? $msg : 'API returned HTTP ' . $status);
    }

    return $decoded;
}

function chatbotEnsureSessionId(): string
{
    startSession();
    if (empty($_SESSION['chatbot_session_id']) || !is_string($_SESSION['chatbot_session_id'])) {
        $_SESSION['chatbot_session_id'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['chatbot_session_id'];
}

/** @return array{success: bool, answer: string, source: string} */
function processChatbotMessage(string $question): array
{
    ensureChatbotSchema();
    $settings = getChatbotSettings();

    if (($settings['chatbot_enabled'] ?? '0') !== '1') {
        return [
            'success' => false,
            'answer'  => 'The chatbot is currently unavailable.',
            'source'  => 'offline',
        ];
    }

    $question = trim($question);
    if ($question === '') {
        return [
            'success' => false,
            'answer'  => 'Please enter a question.',
            'source'  => 'offline',
        ];
    }

    if (mb_strlen($question) > 2000) {
        return [
            'success' => false,
            'answer'  => 'Your message is too long. Please shorten it and try again.',
            'source'  => 'offline',
        ];
    }

    $match = findChatbotAnswer($question);
    if ($match !== null) {
        return [
            'success' => true,
            'answer'  => (string) $match['answer'],
            'source'  => 'qa',
        ];
    }

    $ai = callChatbotAi($question);
    if (!empty($ai['success'])) {
        return [
            'success' => true,
            'answer'  => (string) $ai['answer'],
            'source'  => 'ai',
        ];
    }

    return [
        'success' => true,
        'answer'  => (string) ($ai['message'] ?? $settings['chatbot_offline_message']),
        'source'  => 'offline',
    ];
}

function saveChatHistory(string $sessionId, string $question, string $answer, string $source): int
{
    ensureChatbotSchema();
    $userId = null;
    if (!empty($_SESSION['user_id'])) {
        $userId = (int) $_SESSION['user_id'];
    }

    return db()->insert(
        'INSERT INTO chat_history (session_id, user_id, question, answer, source, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $sessionId,
            $userId,
            $question,
            $answer,
            in_array($source, ['qa', 'ai', 'offline'], true) ? $source : 'offline',
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]
    );
}

/** @return array<int, array<string, mixed>> */
function listChatbotQaPairs(int $page = 1, int $perPage = 25, string $search = ''): array
{
    ensureChatbotSchema();
    $offset = max(0, ($page - 1) * $perPage);
    $params = [];
    $where = '1=1';
    if ($search !== '') {
        $where .= ' AND (question LIKE ? OR answer LIKE ? OR keywords LIKE ?)';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like];
    }

    return db()->fetchAll(
        "SELECT * FROM chatbot_data WHERE $where ORDER BY sort_order ASC, id DESC LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
}

function countChatbotQaPairs(string $search = ''): int
{
    ensureChatbotSchema();
    if ($search === '') {
        return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM chatbot_data')['c'] ?? 0);
    }
    $like = '%' . $search . '%';
    return (int) (db()->fetchOne(
        'SELECT COUNT(*) AS c FROM chatbot_data WHERE question LIKE ? OR answer LIKE ? OR keywords LIKE ?',
        [$like, $like, $like]
    )['c'] ?? 0);
}

/** @param array<string, mixed> $data */
function saveChatbotQaPair(array $data, ?int $id = null): array
{
    ensureChatbotSchema();
    $question = trim((string) ($data['question'] ?? ''));
    $answer = trim((string) ($data['answer'] ?? ''));
    $keywords = trim((string) ($data['keywords'] ?? ''));
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $sortOrder = (int) ($data['sort_order'] ?? 0);

    if ($question === '' || $answer === '') {
        return ['success' => false, 'message' => 'Question and answer are required.'];
    }

    if ($id !== null && $id > 0) {
        db()->execute(
            'UPDATE chatbot_data SET question = ?, answer = ?, keywords = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?',
            [$question, $answer, $keywords !== '' ? $keywords : null, $isActive, $sortOrder, $id]
        );
        return ['success' => true, 'message' => 'Q&A pair updated.', 'id' => $id];
    }

    $newId = db()->insert(
        'INSERT INTO chatbot_data (question, answer, keywords, is_active, sort_order) VALUES (?, ?, ?, ?, ?)',
        [$question, $answer, $keywords !== '' ? $keywords : null, $isActive, $sortOrder]
    );
    return ['success' => true, 'message' => 'Q&A pair added.', 'id' => $newId];
}

function deleteChatbotQaPair(int $id): bool
{
    ensureChatbotSchema();
    return db()->execute('DELETE FROM chatbot_data WHERE id = ?', [$id]) > 0;
}

/** @return array<int, array<string, mixed>> */
function listChatHistory(int $page = 1, int $perPage = 30, string $search = ''): array
{
    ensureChatbotSchema();
    $offset = max(0, ($page - 1) * $perPage);
    $params = [];
    $where = '1=1';
    if ($search !== '') {
        $where .= ' AND (ch.question LIKE ? OR ch.answer LIKE ? OR ch.session_id LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)';
        $like = '%' . $search . '%';
        $params = [$like, $like, $like, $like, $like];
    }

    return db()->fetchAll(
        "SELECT ch.*, u.full_name, u.email
         FROM chat_history ch
         LEFT JOIN users u ON u.id = ch.user_id
         WHERE $where
         ORDER BY ch.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );
}

function countChatHistory(string $search = ''): int
{
    ensureChatbotSchema();
    if ($search === '') {
        return (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM chat_history')['c'] ?? 0);
    }
    $like = '%' . $search . '%';
    return (int) (db()->fetchOne(
        'SELECT COUNT(*) AS c FROM chat_history ch
         LEFT JOIN users u ON u.id = ch.user_id
         WHERE ch.question LIKE ? OR ch.answer LIKE ? OR ch.session_id LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?',
        [$like, $like, $like, $like, $like]
    )['c'] ?? 0);
}

function deleteChatHistoryEntry(int $id): bool
{
    ensureChatbotSchema();
    return db()->execute('DELETE FROM chat_history WHERE id = ?', [$id]) > 0;
}

function clearAllChatHistory(): int
{
    ensureChatbotSchema();
    return db()->execute('DELETE FROM chat_history');
}
