<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/public-footer.php';
require_once __DIR__ . '/contact-messages.php';

function renderPublicEnquiryStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style>'
        . ':root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.4);}'
        . 'body{background:var(--cyber-dark);color:var(--cyber-text);font-family:\'DM Sans\',sans-serif;overflow-x:hidden}'
        . 'body::before{content:\'\';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}'
        . 'a{text-decoration:none}'
        . '.section{padding:5rem 0;position:relative;z-index:1}'
        . '.page-hero{padding:4rem 0 2rem;text-align:center;position:relative;z-index:1}'
        . '.page-hero h1{font-family:\'Rajdhani\',sans-serif;font-size:clamp(2.2rem,5vw,3.5rem);font-weight:700;margin-bottom:1rem}'
        . '.page-hero h1 .accent{color:var(--cyber-accent)}'
        . '.page-hero p{color:var(--cyber-muted);max-width:720px;margin:0 auto;line-height:1.8}'
        . '.section-title{font-family:\'Rajdhani\',sans-serif;font-size:clamp(1.8rem,4vw,2.5rem);font-weight:700;margin-bottom:.5rem}'
        . '.section-title .accent{color:var(--cyber-accent)}'
        . '.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin-bottom:1.5rem}'
        . '.enquiry-form{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:2.5rem}'
        . '.info-card{background:rgba(10,22,40,0.95);border:1px solid var(--cyber-border);border-radius:12px;padding:1.5rem;margin-bottom:1rem}'
        . '.info-card i{color:var(--cyber-accent)}'
        . '.brand-eva{color:#00ff88;font-weight:600}'
        . '.form-control,.form-select{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:6px!important;padding:.75rem 1rem!important}'
        . '.form-select option{background:#0a1628;color:var(--cyber-text)}'
        . '.form-control:focus,.form-select:focus{border-color:var(--cyber-accent)!important;box-shadow:0 0 0 3px rgba(0,212,255,0.1)!important}'
        . '.form-control::placeholder{color:var(--cyber-muted)!important}'
        . '.form-label{color:var(--cyber-muted);font-size:.88rem;margin-bottom:.4rem}'
        . '.field-error{color:#ff4444;font-size:.82rem;margin-top:.35rem;line-height:1.35}'
        . '.form-control.is-invalid{border-color:rgba(255,68,68,0.65)!important;box-shadow:0 0 0 3px rgba(255,68,68,0.12)!important}'
        . '.btn-primary-cyber:disabled{opacity:.45;cursor:not-allowed;transform:none!important}'
        . '.form-select-placeholder:has(option[value=""]:checked){color:var(--cyber-muted)!important;}'
        . '.form-select-placeholder option{color:var(--cyber-text);}'
        . '.form-select-placeholder option[value=""]{color:var(--cyber-muted);}'
        . '.btn-primary-cyber{background:var(--cyber-accent);color:#050b18;border:none;padding:.85rem 1.5rem;border-radius:6px;font-weight:700;font-size:1rem;transition:all .2s;cursor:pointer;display:inline-block}'
        . '.btn-primary-cyber:hover{background:var(--cyber-green);transform:translateY(-2px);color:#050b18}'
        . '.btn-outline-cyber{display:inline-block;background:transparent;border:1px solid var(--cyber-border);color:var(--cyber-accent);padding:.85rem 1.5rem;border-radius:6px;font-weight:600;transition:all .2s}'
        . '.btn-outline-cyber:hover{border-color:var(--cyber-accent);background:rgba(0,212,255,.08)}'
        . '.info-card-cta{background:linear-gradient(135deg,rgba(0,212,255,.22) 0%,rgba(0,255,136,.1) 100%);border:1px solid var(--cyber-accent);border-radius:12px;padding:1.25rem 1.5rem}'
        . '.info-card-cta-label{font-size:.8rem;color:#ffffff;font-weight:600;margin-bottom:.75rem}'
        . '.info-card-cta .btn-outline-cyber,.info-card-cta .btn-accent-cta{display:inline-block;background:var(--cyber-accent);color:#050b18;border:1px solid var(--cyber-accent);padding:.75rem 1.35rem;border-radius:6px;font-weight:700;font-size:.95rem;transition:all .2s;text-align:center}'
        . '.info-card-cta .btn-outline-cyber:hover,.info-card-cta .btn-accent-cta:hover{background:var(--cyber-green);border-color:var(--cyber-green);color:#050b18;transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,212,255,.25)}'
        . '.info-card-cta .btn-outline-cyber i,.info-card-cta .btn-accent-cta i{color:#050b18}'
        . '.step-list{list-style:none;padding:0;margin:0}'
        . '.step-list li{display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.35rem}'
        . '.step-list li:last-child{margin-bottom:0}'
        . '.step-num{flex:0 0 40px;height:40px;border-radius:10px;background:rgba(0,212,255,.1);border:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:center;font-family:\'Rajdhani\',sans-serif;font-weight:700;font-size:1.05rem;color:var(--cyber-accent)}'
        . '.step-content{flex:1;min-width:0}'
        . '.step-title{font-family:\'Rajdhani\',sans-serif;font-size:1.05rem;font-weight:600;color:var(--cyber-text);margin:0 0 .35rem}'
        . '.step-desc{color:var(--cyber-muted);font-size:.9rem;line-height:1.65;margin:0}'
        . '@media(max-width:767px){.section{padding:3rem 0}}'
        . '</style>' . "\n";
    renderPublicFooterStyles();
}

/**
 * @param array<string, string> $extraLines  Label => value for message body
 */
function handlePublicEnquiryPost(string $redirectPath, string $fixedSubject, array $extraLines = []): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        redirectWith($redirectPath, 'error', 'Invalid form submission. Please try again.');
    }

    $name    = sanitize($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = sanitize($_POST['phone'] ?? '');
    $message = trim(sanitize($_POST['message'] ?? ''));

    if (strlen($name) < 2) {
        redirectWith($redirectPath, 'error', 'Please enter a valid name.');
    }
    if (!isValidGmailAddress($email)) {
        redirectWith($redirectPath, 'error', GMAIL_VALIDATION_MESSAGE);
    }
    if (!empty($_POST['require_phone']) && strlen($phone) < 8) {
        redirectWith($redirectPath, 'error', 'Please enter a valid contact number.');
    }
    if (strlen($message) < 10) {
        $message = 'Registration submitted via the website.';
    }

    $bodyParts = [];
    foreach ($extraLines as $label => $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $bodyParts[] = $label . ': ' . $value;
        }
    }
    if (!empty($bodyParts)) {
        $message = implode("\n", $bodyParts) . "\n\n---\n\n" . $message;
    }

    try {
        insertContactMessage($name, $email, $phone, $fixedSubject, $message);
        redirectWith($redirectPath, 'success', 'Thank you! Our team will contact you within 24 hours.');
    } catch (Throwable $e) {
        redirectWith($redirectPath, 'error', 'Could not submit your request. Please try again later.');
    }
}

function postVal(string $key, string $default = ''): string
{
    return htmlspecialchars($_POST[$key] ?? $default);
}
