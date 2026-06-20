<?php
declare(strict_types=1);

require_once __DIR__ . '/company-content.php';

function renderCompanyPageStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style>'
        . ':root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-text:#e0e8f0;--cyber-muted:#7a8fa6;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.4);}'
        . 'body{background:var(--cyber-dark);color:var(--cyber-text);font-family:\'DM Sans\',sans-serif;overflow-x:hidden;margin:0}'
        . 'body::before{content:\'\';position:fixed;inset:0;background-image:linear-gradient(rgba(0,212,255,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(0,212,255,0.03) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}'
        . 'a{text-decoration:none}'
        . '.company-section{padding:4.5rem 0;position:relative;z-index:1}'
        . '.company-hero{padding:4rem 0 2rem;text-align:center;position:relative;z-index:1}'
        . '.company-hero h1{font-family:\'Rajdhani\',sans-serif;font-size:clamp(2.2rem,5vw,3.4rem);font-weight:700;margin-bottom:1rem}'
        . '.company-hero h1 .accent{color:var(--cyber-accent)}'
        . '.company-hero p{color:var(--cyber-muted);max-width:760px;margin:0 auto;line-height:1.8;font-size:1.05rem}'
        . '.section-title{font-family:\'Rajdhani\',sans-serif;font-size:clamp(1.8rem,4vw,2.4rem);font-weight:700;margin-bottom:.5rem}'
        . '.section-title .accent{color:var(--cyber-accent)}'
        . '.section-title .green{color:var(--cyber-green)}'
        . '.divider{width:60px;height:3px;background:linear-gradient(90deg,var(--cyber-accent),var(--cyber-green));margin-bottom:1.25rem}'
        . '.company-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.75rem;height:100%;transition:border-color .2s,transform .2s}'
        . '.company-card:hover{border-color:rgba(0,212,255,0.45);transform:translateY(-3px)}'
        . '.company-card i{font-size:1.6rem;color:var(--cyber-accent);margin-bottom:.85rem;display:block}'
        . '.company-card h3{font-family:\'Rajdhani\',sans-serif;font-size:1.25rem;margin-bottom:.65rem}'
        . '.company-card p{color:var(--cyber-muted);line-height:1.7;margin:0;font-size:.95rem}'
        . '.company-list{list-style:none;padding:0;margin:.75rem 0 0}'
        . '.company-list li{position:relative;padding:.35rem 0 .35rem 1.2rem;color:var(--cyber-muted);line-height:1.6;font-size:.92rem}'
        . '.company-list li::before{content:\'\';position:absolute;left:0;top:.65rem;width:6px;height:6px;border-radius:2px;background:linear-gradient(135deg,var(--cyber-accent),var(--cyber-green))}'
        . '.metric-pill{display:inline-block;background:rgba(0,212,255,0.08);border:1px solid var(--cyber-border);border-radius:999px;padding:.35rem .85rem;font-size:.82rem;color:var(--cyber-text);margin:.2rem .35rem .2rem 0}'
        . '.case-shot{border-radius:10px;border:1px solid var(--cyber-border);width:100%;height:auto;display:block}'
        . '.about-card.team-card{display:flex;flex-direction:column;align-items:center;text-align:center;padding:20px;height:100%;box-sizing:border-box;overflow:hidden}'
        . '.team-photo{width:96px;height:96px;max-width:min(96px,40vw);aspect-ratio:1;border-radius:50%;object-fit:cover;object-position:center top;border:2px solid var(--cyber-border);margin:0 0 15px;display:block;flex-shrink:0;background:rgba(0,212,255,0.08)}'
        . '.team-avatar{width:96px;height:96px;max-width:min(96px,40vw);aspect-ratio:1;border-radius:50%;border:2px solid var(--cyber-border);margin:0 0 15px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(0,212,255,0.1);color:var(--cyber-accent);font-family:\'Rajdhani\',sans-serif;font-size:2rem;font-weight:700}'
        . '.team-identity{width:100%;text-align:center;margin:0 0 12px}'
        . '.team-identity .team-name-line{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.25rem;margin:0;padding:0;font-family:\'Rajdhani\',sans-serif;font-size:clamp(1.1rem,2.5vw,1.28rem);font-weight:700;line-height:1.35;color:var(--cyber-text,#e0e8f0)}'
        . '.team-identity .team-name-text{display:block;max-width:100%}'
        . '.team-identity .team-degree-text{display:block;color:var(--cyber-accent);font-family:\'DM Sans\',sans-serif;font-size:clamp(.82rem,2vw,.92rem);font-weight:600;letter-spacing:.03em;line-height:1.3}'
        . '.team-identity .team-degree-sep{display:none;color:var(--cyber-muted);font-weight:400;font-size:.95em;padding:0 .15rem;line-height:1}'
        . '.team-card .team-role{width:100%;text-align:center;color:var(--cyber-accent);font-size:.9rem;font-weight:500;margin:0 0 12px;line-height:1.4}'
        . '.team-card .team-bio{width:100%;text-align:justify;color:var(--cyber-muted);line-height:1.7;font-size:.92rem;margin:0;overflow-wrap:anywhere;word-break:break-word;hyphens:auto;flex:1 1 auto}'
        . '.team-profile-col{display:flex}'
        . '@media(min-width:576px){.team-identity .team-name-line{flex-direction:row;flex-wrap:wrap;gap:.1rem .45rem}.team-identity .team-degree-sep{display:inline}.team-identity .team-degree-text{font-size:.9rem}}'
        . '@media(max-width:767px){.about-card.team-card{padding:18px}.team-card .team-bio{font-size:.9rem;line-height:1.65}.team-identity{margin-bottom:10px}}'
        . '.trust-badge{display:inline-flex;align-items:center;gap:.5rem;background:rgba(10,22,40,0.95);border:1px solid var(--cyber-border);border-radius:8px;padding:.55rem .9rem;margin:.25rem}'
        . '.btn-primary-cyber{display:inline-flex;align-items:center;gap:.5rem;background:var(--cyber-accent);color:#050b18;border:none;padding:.8rem 1.5rem;border-radius:6px;font-weight:700;font-size:.95rem;transition:all .2s}'
        . '.btn-primary-cyber:hover{background:var(--cyber-green);color:#050b18;transform:translateY(-2px)}'
        . '.btn-outline-cyber{display:inline-flex;align-items:center;gap:.5rem;border:1px solid var(--cyber-accent);color:var(--cyber-accent);padding:.8rem 1.5rem;border-radius:6px;font-weight:600;transition:all .2s}'
        . '.btn-outline-cyber:hover{background:rgba(0,212,255,0.1);color:var(--cyber-accent)}'
        . '.fade-in{opacity:0;transform:translateY(18px);transition:all .55s ease}'
        . '.fade-in.visible{opacity:1;transform:translateY(0)}'
        . '@media(max-width:767px){.company-section{padding:3rem 0}}'
        . '</style>';
}

function renderCompanyPageEndScript(): void
{
    echo '<script>document.querySelectorAll(\'.fade-in\').forEach(function(el,i){setTimeout(function(){el.classList.add(\'visible\');},i*60);});</script>';
}

/** Render a single team/founder profile card (About Us). */
function renderTeamProfileCard(array $member): void
{
    require_once __DIR__ . '/team-profiles-admin.php';
    ensureTeamProfileSchema();

    $name = htmlspecialchars((string) ($member['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $designation = htmlspecialchars(teamMemberDesignation($member), ENT_QUOTES, 'UTF-8');
    $degree = htmlspecialchars(teamMemberDegree($member), ENT_QUOTES, 'UTF-8');
    $bio = trim((string) ($member['bio'] ?? ''));
    $photo = trim((string) ($member['photo_path'] ?? ''));

    echo '<div class="about-card team-card w-100">';
    if ($photo !== '') {
        echo '<img class="team-photo" src="' . htmlspecialchars(companyPublicImageUrl($photo), ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '" loading="lazy">';
    } else {
        $initial = htmlspecialchars(strtoupper(substr((string) ($member['name'] ?? '?'), 0, 1)), ENT_QUOTES, 'UTF-8');
        echo '<div class="team-avatar" aria-hidden="true">' . $initial . '</div>';
    }
    echo '<div class="team-identity">';
    echo '<h3 class="team-name-line">';
    echo '<span class="team-name-text">' . $name . '</span>';
    if ($degree !== '') {
        echo '<span class="team-degree-sep" aria-hidden="true">|</span>';
        echo '<span class="team-degree-text">' . $degree . '</span>';
    }
    echo '</h3>';
    echo '</div>';
    if ($designation !== '') {
        echo '<p class="team-role">' . $designation . '</p>';
    }
    if ($bio !== '') {
        echo '<p class="team-bio">' . nl2br(htmlspecialchars($bio, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    echo '</div>';
}
