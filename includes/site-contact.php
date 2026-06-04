<?php
declare(strict_types=1);

/**
 * Site-wide contact channels (mailto, tel, WhatsApp) and reusable action cards.
 */

/** @return array<string, array{key: string, label: string, icon: string, href: string, hint: string, external: bool}> */
function siteContactChannels(): array
{
    $email = defined('SITE_EMAIL') ? (string) SITE_EMAIL : 'info@cybeorch.com';
    $phoneDisplay = defined('SITE_PHONE_DISPLAY') ? (string) SITE_PHONE_DISPLAY : '+91 97640 96069';
    $phoneE164 = defined('SITE_PHONE_E164') ? (string) SITE_PHONE_E164 : '+919764096069';
    $whatsapp = defined('SITE_WHATSAPP') ? (string) SITE_WHATSAPP : '919764096069';
    $telDigits = preg_replace('/\D+/', '', $phoneE164) ?: '919764096069';

    $subject = rawurlencode('CYBEORCH LAB — Program Enquiry');
    $waText = rawurlencode('Hello CYBEORCH LAB, I would like to enquire about your programs.');

    return [
        'email' => [
            'key'      => 'email',
            'label'    => 'Email',
            'icon'     => 'fas fa-envelope',
            'href'     => 'mailto:' . $email . '?subject=' . $subject,
            'hint'     => $email,
            'external' => false,
        ],
        'phone' => [
            'key'      => 'phone',
            'label'    => 'Phone Call',
            'icon'     => 'fas fa-phone',
            'href'     => 'tel:+' . ltrim($telDigits, '+'),
            'hint'     => $phoneDisplay,
            'external' => false,
        ],
        'whatsapp' => [
            'key'      => 'whatsapp',
            'label'    => 'WhatsApp',
            'icon'     => 'fab fa-whatsapp',
            'href'     => 'https://wa.me/' . preg_replace('/\D+/', '', $whatsapp) . '?text=' . $waText,
            'hint'     => $phoneDisplay,
            'external' => true,
        ],
    ];
}

function renderContactActionStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style id="cybeorch-contact-actions">'
        . '.contact-method-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}'
        . 'a.contact-method-card{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;min-height:96px;padding:.9rem .65rem;background:rgba(255,255,255,.04);border:1px solid var(--cyber-border,rgba(0,212,255,.2));border-radius:10px;color:var(--cyber-muted,#7a8fa6);font-size:.82rem;font-weight:600;text-align:center;text-decoration:none;cursor:pointer;transition:border-color .2s,background .2s,color .2s,box-shadow .2s,transform .2s}'
        . 'a.contact-method-card i{font-size:1.2rem;color:var(--cyber-accent,#00d4ff);transition:color .2s,transform .2s}'
        . 'a.contact-method-card .contact-method-detail{font-size:.72rem;font-weight:500;color:var(--cyber-muted,#7a8fa6);line-height:1.35;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 .25rem}'
        . 'a.contact-method-card:hover,a.contact-method-card:focus-visible{border-color:var(--cyber-accent,#00d4ff);background:rgba(0,212,255,.14);color:var(--cyber-text,#e0e8f0);transform:translateY(-2px);box-shadow:0 10px 28px rgba(0,212,255,.18);outline:none}'
        . 'a.contact-method-card:hover i,a.contact-method-card:focus-visible i{color:var(--cyber-green,#00ff88);transform:scale(1.08)}'
        . 'a.contact-method-card.is-selected{border-color:var(--cyber-accent,#00d4ff);background:rgba(0,212,255,.12);color:var(--cyber-text,#e0e8f0);box-shadow:0 0 0 1px rgba(0,212,255,.28)}'
        . 'a.contact-method-card.is-selected i{color:var(--cyber-green,#00ff88)}'
        . '@media(max-width:767px){.contact-method-grid{grid-template-columns:1fr}}'
        . '</style>' . "\n";
}

/**
 * @param string $selectedPref  email | phone | whatsapp
 * @param bool   $withHiddenField Emit hidden preferred_contact for enquiry forms
 */
function renderContactActionGrid(string $selectedPref = 'email', bool $withHiddenField = false): void
{
    $channels = siteContactChannels();
    if (!isset($channels[$selectedPref])) {
        $selectedPref = 'email';
    }

    if ($withHiddenField) {
        echo '<input type="hidden" name="preferred_contact" id="preferredContact" value="'
            . htmlspecialchars($selectedPref, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }

    echo '<div class="contact-method-grid" role="list">';
    foreach ($channels as $channel) {
        $key = $channel['key'];
        $href = $channel['href'];
        $classes = 'contact-method-card' . ($selectedPref === $key ? ' is-selected' : '');
        $attrs = ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="' . $classes . '"'
            . ' data-contact-pref="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"'
            . ' aria-label="' . htmlspecialchars($channel['label'] . ' — ' . $channel['hint'], ENT_QUOTES, 'UTF-8') . '"';
        if (!empty($channel['external'])) {
            $attrs .= ' target="_blank" rel="noopener noreferrer"';
        }
        echo '<a' . $attrs . ' role="listitem">';
        echo '<i class="' . htmlspecialchars($channel['icon'], ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>';
        echo '<span>' . htmlspecialchars($channel['label'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<span class="contact-method-detail">' . htmlspecialchars($channel['hint'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '</a>';
    }
    echo '</div>' . "\n";
}

function renderContactActionGridScript(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<script>(function(){var hidden=document.getElementById("preferredContact");document.querySelectorAll("a[data-contact-pref]").forEach(function(link){link.addEventListener("click",function(){var pref=link.getAttribute("data-contact-pref");if(hidden&&pref){hidden.value=pref;}document.querySelectorAll("a.contact-method-card").forEach(function(c){c.classList.remove("is-selected");});link.classList.add("is-selected");});});})();</script>' . "\n";
}
