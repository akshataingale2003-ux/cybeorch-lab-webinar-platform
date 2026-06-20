<?php
declare(strict_types=1);

require_once __DIR__ . '/chatbot.php';

function chatbotWidgetForceShow(): bool
{
    return defined('CYBEORCH_ADMIN_PAGE') && CYBEORCH_ADMIN_PAGE;
}

function chatbotWidgetShouldRender(bool $forceShow = false): bool
{
    return $forceShow || chatbotWidgetForceShow() || chatbotIsEnabled();
}

/** @return non-empty-string */
function chatbotWidgetCssUrl(): string
{
    $path = dirname(__DIR__) . '/assets/css/chatbot-widget.css';
    $version = is_file($path) ? (string) filemtime($path) : '1';

    return url('assets/css/chatbot-widget.css') . '?v=' . rawurlencode($version);
}

/** @return non-empty-string */
function chatbotWidgetJsUrl(): string
{
    $path = dirname(__DIR__) . '/assets/js/chatbot-widget.js';
    $version = is_file($path) ? (string) filemtime($path) : '1';

    return url('assets/js/chatbot-widget.js') . '?v=' . rawurlencode($version);
}

/**
 * Chatbot stylesheet — call from shared page heads so styles load before paint.
 */
function renderChatbotWidgetHeadStyles(bool $forceShow = false): void
{
    static $done = false;
    if ($done || !chatbotWidgetShouldRender($forceShow)) {
        return;
    }
    $done = true;

    echo '<link rel="stylesheet" href="' . htmlspecialchars(chatbotWidgetCssUrl(), ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

/**
 * Render the site-wide chatbot widget (HTML + JS).
 * Safe to call from footer, layout end, or renderSiteScripts() — renders once per request.
 */
function renderChatbotWidgetAssets(bool $forceShow = false): void
{
    static $rendered = false;
    if ($rendered || !chatbotWidgetShouldRender($forceShow)) {
        return;
    }
    $rendered = true;

    renderChatbotWidgetHeadStyles($forceShow);

    $settings = dbTry(static fn () => getChatbotSettings(), chatbotSettingsDefaults());
    $sessionId = chatbotEnsureSessionId();

    echo '<div id="cybeorch-chatbot-root" class="cyb-chatbot-root"'
        . ' data-init-url="' . htmlspecialchars(url('api/chatbot-init.php'), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-send-url="' . htmlspecialchars(url('api/chatbot-send.php'), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-session-id="' . htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8') . '"'
        . ' data-bot-name="' . htmlspecialchars((string) ($settings['chatbot_bot_name'] ?? 'Assistant'), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-welcome="' . htmlspecialchars((string) ($settings['chatbot_welcome_message'] ?? ''), ENT_QUOTES, 'UTF-8') . '"'
        . ' aria-live="polite">'
        . '<button type="button" class="cyb-chatbot-toggle" id="cybChatbotToggle" aria-label="Open chat assistant" aria-expanded="false">'
        . '<span class="cyb-chatbot-badge" id="cybChatbotBadge" hidden aria-hidden="true"></span>'
        . '<i class="fas fa-comment-dots" aria-hidden="true"></i>'
        . '<span class="cyb-chatbot-toggle-label">Chat</span>'
        . '</button>'
        . '<div class="cyb-chatbot-tooltip" id="cybChatbotTooltip" role="status" aria-live="polite" hidden>'
        . '👋 Need Help? Chat with us!'
        . '</div>'
        . '<div class="cyb-chatbot-panel" id="cybChatbotPanel" hidden>'
        . '<div class="cyb-chatbot-header">'
        . '<div class="cyb-chatbot-header-info">'
        . '<div class="cyb-chatbot-avatar"><i class="fas fa-robot" aria-hidden="true"></i></div>'
        . '<div><strong class="cyb-chatbot-title">' . htmlspecialchars((string) ($settings['chatbot_bot_name'] ?? 'Assistant'), ENT_QUOTES, 'UTF-8') . '</strong>'
        . '<span class="cyb-chatbot-status">Online</span></div>'
        . '</div>'
        . '<button type="button" class="cyb-chatbot-close" id="cybChatbotClose" aria-label="Close chat"><i class="fas fa-times"></i></button>'
        . '</div>'
        . '<div class="cyb-chatbot-messages" id="cybChatbotMessages" role="log" aria-relevant="additions"></div>'
        . '<form class="cyb-chatbot-form" id="cybChatbotForm" autocomplete="off">'
        . '<textarea id="cybChatbotInput" class="cyb-chatbot-input" rows="1" maxlength="2000" placeholder="Ask anything about CYBEORCH…" aria-label="Your message"></textarea>'
        . '<button type="submit" class="cyb-chatbot-send" id="cybChatbotSend" aria-label="Send message"><i class="fas fa-paper-plane"></i></button>'
        . '</form>'
        . '</div>'
        . '</div>' . "\n";

    echo '<script src="' . htmlspecialchars(chatbotWidgetJsUrl(), ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
}
