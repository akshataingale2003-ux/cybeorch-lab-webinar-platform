-- Chatbot system tables for CYBEORCH LABS

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chatbot_data (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    user_id INT DEFAULT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    source ENUM('qa','ai','offline') NOT NULL DEFAULT 'offline',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_history_session (session_id),
    INDEX idx_chat_history_created (created_at),
    INDEX idx_chat_history_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: admin authentication uses the existing `admin` table (same role as "admins").

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('chatbot_enabled', '1'),
('chatbot_ai_enabled', '1'),
('chatbot_api_provider', 'gemini'),
('chatbot_api_key', ''),
('chatbot_welcome_message', 'Hi! I''m the CYBEORCH assistant. Ask me about webinars, bootcamps, payments, or anything else.'),
('chatbot_offline_message', 'Thanks for your question! Our team will get back to you soon. For urgent help, visit the Support Desk.'),
('chatbot_bot_name', 'CYBEORCH Assistant');
