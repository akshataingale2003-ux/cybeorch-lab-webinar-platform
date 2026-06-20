<?php
declare(strict_types=1);

require_once __DIR__ . '/contact-messages.php';
require_once __DIR__ . '/admin-schema.php';

function renderAdminStyles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style>'
        . ':root{--cyber-dark:#050b18;--cyber-navy:#0a1628;--cyber-accent:#00d4ff;--cyber-green:#00ff88;--cyber-orange:#ff6b35;--cyber-red:#ff4444;--cyber-muted:#7a8fa6;--cyber-text:#e0e8f0;--cyber-border:rgba(0,212,255,0.2);--cyber-card:rgba(15,52,96,0.35);--sidebar-w:250px;}'
        . '*{box-sizing:border-box;margin:0;padding:0;}'
        . 'body{background:var(--cyber-dark);color:var(--cyber-text);font-family:\'DM Sans\',sans-serif;}'
        . '.sidebar{width:var(--sidebar-w);height:100vh;background:var(--cyber-navy);border-right:1px solid var(--cyber-border);position:fixed;top:0;left:0;z-index:100;overflow-y:auto;display:flex;flex-direction:column;}'
        . '.sidebar-logo{padding:1.25rem 1.5rem;border-bottom:1px solid var(--cyber-border);}'
        . '.sidebar-logo-text{font-size:1.4rem;}'
        . '.admin-badge{font-size:0.65rem;background:rgba(255,107,53,0.2);border:1px solid rgba(255,107,53,0.4);color:var(--cyber-orange);padding:2px 8px;border-radius:4px;letter-spacing:1px;margin-top:4px;display:inline-block;}'
        . '.sidebar-nav{flex:1;padding:0.75rem 0;}'
        . '.nav-section{padding:0.75rem 1.25rem 0.25rem;font-size:0.68rem;color:var(--cyber-muted);text-transform:uppercase;letter-spacing:2px;font-weight:600;}'
        . '.sidebar-link{display:flex;align-items:center;gap:0.75rem;padding:0.6rem 1.25rem;color:var(--cyber-muted);text-decoration:none;font-size:0.85rem;transition:all 0.2s;border-left:3px solid transparent;}'
        . '.sidebar-link i{width:18px;text-align:center;}'
        . '.sidebar-link:hover,.sidebar-link.active{color:var(--cyber-accent);background:rgba(0,212,255,0.06);border-left-color:var(--cyber-accent);}'
        . '.sidebar-link.danger:hover{color:var(--cyber-red);border-left-color:var(--cyber-red);}'
        . '.main{margin-left:var(--sidebar-w);min-height:100vh;}'
        . '.topbar{background:rgba(10,22,40,0.9);border-bottom:1px solid var(--cyber-border);padding:0.85rem 1.5rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}'
        . '.page-title{font-family:\'Rajdhani\',sans-serif;font-size:1.35rem;font-weight:700;}'
        . '.content{padding:1.5rem;}'
        . '.section-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;overflow:hidden;margin-bottom:1.5rem;}'
        . '.section-card-header{padding:1rem 1.5rem;border-bottom:1px solid var(--cyber-border);font-family:\'Rajdhani\',sans-serif;font-weight:600;}'
        . '.section-card-body{padding:1.25rem 1.5rem;}'
        . '.data-table{width:100%;border-collapse:collapse;}'
        . '.data-table th{padding:0.75rem 1rem;font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--cyber-muted);border-bottom:1px solid var(--cyber-border);text-align:left;}'
        . '.data-table td{padding:0.85rem 1rem;font-size:0.85rem;border-bottom:1px solid rgba(0,212,255,0.06);vertical-align:middle;}'
        . '.badge-status{font-size:0.68rem;padding:0.25rem 0.6rem;border-radius:4px;font-weight:600;text-transform:uppercase;}'
        . '.badge-paid,.badge-active{background:rgba(0,255,136,0.12);color:var(--cyber-green);}'
        . '.badge-blocked{background:rgba(255,107,53,0.12);color:var(--cyber-orange);}'
        . '.badge-deleted{background:rgba(255,68,68,0.12);color:#ff8888;}'
        . '.badge-refunded{background:rgba(0,212,255,0.12);color:var(--cyber-accent);}'
        . '.badge-pending,.badge-created{background:rgba(255,107,53,0.12);color:var(--cyber-orange);}'
        . '.badge-failed,.badge-rejected{background:rgba(255,68,68,0.12);color:#ff6666;}'
        . '.badge-unread{background:rgba(255,68,68,0.15);color:#ff8888;}'
        . '.badge-read{background:rgba(0,212,255,0.1);color:var(--cyber-accent);}'
        . '.form-control{background:rgba(255,255,255,0.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:7px!important;padding:0.65rem 1rem!important;font-size:0.88rem!important;}'
        . '.form-label{color:var(--cyber-muted);font-size:0.82rem;margin-bottom:0.35rem;}'
        . '.btn-submit{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.65rem 1.25rem;border-radius:7px;font-weight:700;cursor:pointer;}'
        . '.btn-sm-link{font-size:0.78rem;color:var(--cyber-accent);text-decoration:none;}'
        . '.alert{padding:0.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;}'
        . '.alert-success{background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:var(--cyber-green);}'
        . '.alert-error{background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666;}'
        . '.filter-bar{display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;}'
        . '.filter-bar a{padding:0.4rem 0.85rem;border-radius:6px;font-size:0.82rem;border:1px solid var(--cyber-border);color:var(--cyber-muted);text-decoration:none;}'
        . '.filter-bar a.active,.filter-bar a:hover{border-color:var(--cyber-accent);color:var(--cyber-accent);}'
        . '.admin-toolbar{display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-end;justify-content:space-between;margin-bottom:1rem;}'
        . '.admin-search-form{display:flex;flex-wrap:wrap;gap:0.5rem;flex:1;min-width:min(100%,240px);max-width:420px;}'
        . '.admin-search-form input{flex:1;min-width:140px;}'
        . '.btn-search{background:rgba(0,212,255,0.15);color:var(--cyber-accent);border:1px solid var(--cyber-border);padding:0.65rem 1rem;border-radius:7px;font-weight:600;cursor:pointer;font-size:0.85rem;}'
        . '.btn-search:hover{background:rgba(0,212,255,0.25);}'
        . '.password-masked{display:inline-flex;align-items:center;gap:0.5rem;font-family:monospace;letter-spacing:2px;color:var(--cyber-muted);background:rgba(0,0,0,0.25);border:1px solid var(--cyber-border);border-radius:6px;padding:0.5rem 0.85rem;font-size:0.9rem;}'
        . '.password-hint{font-size:0.78rem;color:var(--cyber-muted);margin-top:0.35rem;line-height:1.5;}'
        . '.stat-cards-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:0.75rem;margin-bottom:1.25rem;}'
        . '.stat-cards-row .stat-card{padding:0.85rem 1rem;}'
        . '.stat-cards-row .stat-value{font-size:1.5rem;}'
        . '.detail-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:1rem;}'
        . '.detail-item label{display:block;font-size:0.72rem;color:var(--cyber-muted);text-transform:uppercase;margin-bottom:0.25rem;}'
        . '.msg-body{background:rgba(0,0,0,0.2);border:1px solid var(--cyber-border);border-radius:8px;padding:1rem;white-space:pre-wrap;font-size:0.9rem;line-height:1.7;}'
        . '.form-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;overflow:hidden;margin-bottom:1.5rem;}'
        . '.form-card-header{padding:1rem 1.5rem;border-bottom:1px solid var(--cyber-border);font-family:\'Rajdhani\',sans-serif;font-weight:600;display:flex;align-items:center;gap:0.5rem;}'
        . '.form-card-body{padding:1.5rem;}'
        . '.btn-submit{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.65rem 1.25rem;border-radius:7px;font-weight:700;cursor:pointer;}'
        . '.btn-submit:hover{background:var(--cyber-green);}'
        . '.btn-cancel{background:transparent;color:var(--cyber-muted);border:1px solid var(--cyber-border);padding:0.65rem 1.25rem;border-radius:7px;text-decoration:none;display:inline-block;}'
        . '.btn-cancel:hover{color:var(--cyber-text);border-color:var(--cyber-text);}'
        . '.btn-primary-cyber{background:var(--cyber-accent);color:var(--cyber-dark);border:none;padding:0.6rem 1.25rem;border-radius:7px;font-weight:600;font-size:0.85rem;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:0.4rem;}'
        . '.btn-primary-cyber:hover{background:var(--cyber-green);}'
        . '.btn-sm-cyber{font-size:0.75rem;padding:0.3rem 0.75rem;border-radius:5px;text-decoration:none;display:inline-flex;align-items:center;gap:0.25rem;}'
        . '.btn-edit{background:rgba(0,212,255,0.12);color:var(--cyber-accent);border:1px solid rgba(0,212,255,0.25);}'
        . '.btn-delete{background:rgba(255,68,68,0.12);color:#ff6666;border:1px solid rgba(255,68,68,0.35);cursor:pointer;}'
        . '.btn-delete:hover{background:rgba(255,68,68,0.22);color:#fff;}'
        . '.btn-block{background:rgba(255,107,53,0.12);color:var(--cyber-orange);border:1px solid rgba(255,107,53,0.35);cursor:pointer;}'
        . '.btn-block:hover{background:rgba(255,107,53,0.22);}'
        . '.btn-unblock{background:rgba(0,255,136,0.12);color:var(--cyber-green);border:1px solid rgba(0,255,136,0.35);cursor:pointer;}'
        . '.btn-unblock:hover{background:rgba(0,255,136,0.22);}'
        . '.btn-restore{background:rgba(0,212,255,0.12);color:var(--cyber-accent);border:1px solid rgba(0,212,255,0.35);cursor:pointer;}'
        . '.admin-action-btns{gap:0.35rem!important;}'
        . '.admin-action-btns .btn-sm-cyber{border:none;white-space:nowrap;}'
        . '.admin-action-btns .btn-sm-cyber span{font-size:0.72rem;font-weight:600;}'
        . '.admin-row-blocked td:not(:last-child){opacity:0.65;}'
        . '.admin-row-blocked{background:rgba(255,107,53,0.04);}'
        . 'tr.admin-row-unread td{background:rgba(255,68,68,0.03);}'
        . '.admin-block-badge{font-size:0.65rem;padding:0.15rem 0.45rem;border-radius:4px;background:rgba(255,107,53,0.15);color:var(--cyber-orange);border:1px solid rgba(255,107,53,0.35);margin-left:0.35rem;}'
        . '.admin-toast-host{position:fixed;top:1rem;right:1rem;z-index:20000;display:flex;flex-direction:column;gap:0.5rem;max-width:min(360px,calc(100vw - 2rem));}'
        . '.admin-toast{padding:0.85rem 1rem;border-radius:8px;font-size:0.88rem;display:flex;align-items:center;gap:0.5rem;opacity:0;transform:translateX(12px);transition:all 0.28s ease;box-shadow:0 8px 32px rgba(0,0,0,0.35);}'
        . '.admin-toast.show{opacity:1;transform:translateX(0);}'
        . '.admin-toast-success{background:rgba(0,255,136,0.12);border:1px solid rgba(0,255,136,0.35);color:var(--cyber-green);}'
        . '.admin-toast-error{background:rgba(255,68,68,0.12);border:1px solid rgba(255,68,68,0.35);color:#ff8888;}'
        . '.quick-action{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:10px;padding:1rem;text-align:center;text-decoration:none;color:var(--cyber-text);transition:all 0.2s;display:flex;flex-direction:column;align-items:center;gap:0.5rem;}'
        . '.quick-action:hover{border-color:var(--cyber-accent);color:var(--cyber-accent);transform:translateY(-2px);}'
        . '.stat-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:12px;padding:1.25rem 1.5rem;}'
        . '.stat-value{font-family:\'Rajdhani\',sans-serif;font-size:2rem;font-weight:700;}'
        . '.stat-label{font-size:0.72rem;line-height:1.35;color:var(--cyber-muted);}'
        . '.sidebar-link{line-height:1.35;}'
        . '.admin-menu-btn{display:none;background:rgba(0,212,255,0.1);border:1px solid var(--cyber-border);color:var(--cyber-accent);padding:0.4rem 0.65rem;border-radius:6px;cursor:pointer;font-size:1rem;}'
        . '.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;}'
        . '.table-responsive,.table-responsive-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;}'
        . '.section-card-body .data-table{min-width:720px;}'
        . '.admin-pagination{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:0.75rem;margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--cyber-border);}'
        . '.admin-pagination .page-links{display:flex;flex-wrap:wrap;gap:0.35rem;}'
        . '.admin-pagination a,.admin-pagination span{padding:0.4rem 0.75rem;border-radius:6px;font-size:0.82rem;border:1px solid var(--cyber-border);color:var(--cyber-muted);text-decoration:none;}'
        . '.admin-pagination a:hover,.admin-pagination a.active{border-color:var(--cyber-accent);color:var(--cyber-accent);}'
        . '.admin-pagination span.disabled{opacity:0.45;}'
        . '.submission-cards{display:none;gap:0.75rem;}'
        . '.submission-card{background:rgba(0,0,0,0.2);border:1px solid var(--cyber-border);border-radius:10px;padding:1rem;}'
        . '.submission-card.unread{border-color:rgba(255,68,68,0.35);}'
        . '.submission-card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem;margin-bottom:0.65rem;}'
        . '.submission-card-meta{font-size:0.78rem;color:var(--cyber-muted);line-height:1.6;}'
        . '.submission-card-msg{font-size:0.85rem;color:var(--cyber-text);margin:0.65rem 0;line-height:1.5;}'
        . '.submission-card-actions{display:flex;flex-wrap:wrap;gap:0.35rem;margin-top:0.75rem;align-items:center;}'
        . '.filter-bar .filter-count{opacity:0.75;font-size:0.75em;}'
        . '.active-filter-banner{font-size:0.95rem;color:var(--cyber-accent);margin-bottom:0.75rem;font-family:\'Rajdhani\',sans-serif;font-weight:600;}'
        . '.btn-mark-read{background:rgba(0,212,255,0.12);color:var(--cyber-accent);border:1px solid rgba(0,212,255,0.3);cursor:pointer;}'
        . '.admin-trash-bulk-bar{display:flex;flex-wrap:wrap;align-items:center;gap:0.65rem;margin-bottom:1rem;padding:0.85rem 1rem;background:rgba(0,0,0,0.2);border:1px solid var(--cyber-border);border-radius:8px;}'
        . '.admin-trash-bulk-bar .bulk-count{font-size:0.82rem;color:var(--cyber-muted);margin-right:auto;}'
        . '.admin-trash-bulk-bar .bulk-count strong{color:var(--cyber-accent);}'
        . '.admin-trash-select-cb{width:16px;height:16px;accent-color:var(--cyber-accent);cursor:pointer;vertical-align:middle;}'
        . '.admin-trash-bulk-bar .btn-sm-cyber{font-size:0.78rem;padding:0.45rem 0.85rem;border-radius:6px;}'
        . '.admin-trash-bulk-bar .btn-sm-cyber:disabled{opacity:0.45;cursor:not-allowed;}'
        . '@media(max-width:767px){.submission-table-wrap{display:none!important}.submission-cards{display:flex!important;flex-direction:column}}'
        . '@media(max-width:991px){.admin-menu-btn{display:inline-block}.sidebar{transform:translateX(-100%);transition:transform 0.25s ease;z-index:101}.sidebar.open{transform:translateX(0)}.sidebar-overlay.open{display:block}.main{margin-left:0}.content{padding:1rem;}.filter-bar{gap:.35rem;}.filter-bar a{font-size:.78rem;padding:.35rem .65rem;}.topbar{flex-wrap:wrap;gap:.5rem;}.page-title{font-size:clamp(1rem,4vw,1.35rem)!important;}.section-card-body .data-table{min-width:640px;}.form-card-body{padding:1rem!important;}.btn-primary-cyber,.btn-submit,.btn-cancel{width:auto;max-width:100%;min-height:44px;}}'
        . '@media(max-width:767px){.stat-cards-row{grid-template-columns:repeat(2,minmax(0,1fr));}.admin-toast-host{left:1rem;right:1rem;max-width:none;}.admin-pagination{flex-direction:column;align-items:flex-start;}}'
        . '@media(max-width:575px){.stat-cards-row{grid-template-columns:1fr;}.admin-trash-bulk-bar{flex-direction:column;align-items:stretch;}.admin-trash-bulk-bar .bulk-count{margin-right:0;}}'
        . '</style>';
    renderFormSelectStyles();
}

function renderAdminSidebar(string $active = ''): void
{
    try {
        ensureFreelancerRegistrationsSchema();
    } catch (Throwable $e) {
        // continue — badge counts optional
    }
    $unread = (int) dbTry(fn () => getUnreadContactMessageCount(), 0);
    $unreadForms = (int) dbTry(static function () {
        require_once __DIR__ . '/form-submissions.php';
        return getUnreadFormSubmissionCount();
    }, 0);
    $submissionBadge = $unread + $unreadForms > 0 ? $unread + $unreadForms : null;
    $pendingFreelancers = (int) dbTry(fn () => getPendingFreelancerCount(), 0);

    $link = static function (string $page, string $href, string $icon, string $label, ?int $badge = null) use ($active): void {
        $cls = $active === $page ? 'sidebar-link active' : 'sidebar-link';
        echo '<a href="' . htmlspecialchars(adminUrl($href)) . '" class="' . $cls . '">';
        echo '<i class="fas ' . $icon . '"></i>' . htmlspecialchars($label);
        if ($badge !== null && $badge > 0) {
            echo '<span style="background:var(--cyber-red);color:#fff;font-size:0.65rem;padding:1px 6px;border-radius:10px;margin-left:auto">' . $badge . '</span>';
        }
        echo '</a>';
    };
    ?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-text"><?= brandMark() ?></div>
    <div class="admin-badge">ADMIN PANEL</div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Overview</div>
    <?php $link('dashboard', 'dashboard.php', 'fa-th-large', 'Dashboard'); ?>
    <?php $link('analytics', 'analytics.php', 'fa-chart-line', 'Analytics'); ?>

    <div class="nav-section">Management</div>
    <?php $link('webinars', 'webinars.php', 'fa-video', 'Webinars'); ?>
    <?php $link('bootcamps', 'bootcamps.php', 'fa-graduation-cap', 'Bootcamps'); ?>
    <?php $link('recorded-sessions', 'recorded-sessions.php', 'fa-circle-play', 'Recorded Sessions'); ?>
    <?php $link('live-projects', 'live-projects.php', 'fa-code-branch', 'Live Projects'); ?>
    <?php $link('company-content', 'company-content.php', 'fa-building', 'Company Content'); ?>
    <?php $link('team-profiles', 'team-profiles.php', 'fa-user-group', 'Team Profiles'); ?>
    <?php $link('assignments', 'assignments.php', 'fa-briefcase', 'Hands-on Projects'); ?>
    <?php $link('freelance-projects', 'freelance-projects.php', 'fa-laptop-code', 'Freelancer Projects'); ?>
    <?php $link('registrations', 'registrations.php', 'fa-ticket-alt', 'Registrations'); ?>
    <?php $link('certificates', 'certificates.php', 'fa-certificate', 'Certificates'); ?>
    <?php $link('freelancers', 'freelancers.php', 'fa-user-tie', 'Freelancers', $pendingFreelancers); ?>
    <?php $link('attendance', 'attendance.php', 'fa-user-check', 'Attendance'); ?>

    <div class="nav-section">User Registrations & Trainees & Finance</div>
    <?php $link('students', 'students.php', 'fa-users', 'User Management'); ?>
    <?php $link('payments', 'payments.php', 'fa-credit-card', 'Payments'); ?>
    <?php $link('clients', 'clients.php', 'fa-building', 'Client Management'); ?>
    <?php $link('invoices', 'invoices.php', 'fa-file-invoice-dollar', 'Invoice Management'); ?>
    <?php $link('amc', 'amc.php', 'fa-file-contract', 'AMC Management'); ?>
    <?php
    $walletActivityUnread = 0;
    if (function_exists('getAdminUnreadNotificationCount')) {
        require_once __DIR__ . '/nxl-wallet.php';
        $walletActivityUnread = getAdminUnreadNotificationCount();
    }
    $link('wallet', 'wallet.php', 'fa-coins', 'NxL Wallet', $walletActivityUnread > 0 ? $walletActivityUnread : null);
    ?>
    <?php $link('referrals', 'referrals.php', 'fa-share-alt', 'Referrals'); ?>

    <div class="nav-section">Form submissions</div>
    <?php $link('form-submissions', 'form-submissions.php', 'fa-clipboard-list', 'All submissions', $submissionBadge); ?>
    <?php $link('messages', 'messages.php', 'fa-envelope', 'Messages', $unread); ?>
    <?php $link('demo-requests', 'demo-requests.php', 'fa-desktop', 'Demo requests'); ?>
    <?php $link('collaboration', 'collaboration-inquiries.php', 'fa-handshake', 'Collaboration'); ?>
    <?php $link('website-registrations', 'website-registrations.php', 'fa-user-plus', 'Website signups'); ?>

    <div class="nav-section">System</div>
    <?php $link('chatbot', 'chatbot.php', 'fa-robot', 'AI Chatbot'); ?>
    <?php $link('chat_history', 'chat_history.php', 'fa-comments', 'Chat History'); ?>
    <?php $link('manage_qa', 'manage_qa.php', 'fa-question-circle', 'Chatbot Q&A'); ?>
    <?php $link('tickets', 'tickets.php', 'fa-headset', 'Support Tickets'); ?>
    <?php
    $trashCount = (int) dbTry(fn () => adminTrashCount(), 0);
    $link('trash', 'trash.php', 'fa-trash-restore', 'Trash', $trashCount > 0 ? $trashCount : null);
    ?>
    <?php $link('notifications', 'notifications.php', 'fa-bell', 'Notifications'); ?>
    <?php $link('settings', 'settings.php', 'fa-cog', 'Settings'); ?>
    <a href="<?= adminUrl('logout.php') ?>" class="sidebar-link danger"><i class="fas fa-sign-out-alt"></i>Logout</a>
  </nav>
</aside>
    <?php
}

function renderAdminPageStart(string $title, string $activeNav, string $icon = 'fa-th-large'): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php renderStandardViewport(); ?>
<title><?= htmlspecialchars($title) ?> – CYBEORCH Admin</title>
<?php renderAdminPageHead(); ?>
</head>
<body class="admin-page">
<div class="sidebar-overlay" id="adminSidebarOverlay" onclick="toggleAdminSidebar()"></div>
<?php renderAdminSidebar($activeNav); ?>
<main class="main">
  <div class="topbar">
    <div class="topbar-row">
      <div class="page-title" style="display:flex;align-items:center;gap:0.75rem">
        <button type="button" class="admin-menu-btn" id="adminMenuBtn" onclick="toggleAdminSidebar()" aria-label="Open menu"><i class="fas fa-bars"></i></button>
        <span><i class="fas <?= $icon ?>" style="color:var(--cyber-accent);margin-right:0.5rem"></i><?= htmlspecialchars($title) ?></span>
      </div>
      <span style="font-size:0.82rem;color:var(--cyber-muted)"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
    </div>
  </div>
  <?= function_exists('adminDbErrorBanner') ? adminDbErrorBanner() : '' ?>
  <div class="content">
    <?php
}

/**
 * Newest / oldest sort tabs for admin list pages.
 *
 * @param array<string, scalar|null> $extraQuery
 */
function renderAdminSortBar(string $basePath, string $activeSort, array $extraQuery = []): void
{
    $extraQuery = array_filter($extraQuery, static fn ($v) => $v !== null && $v !== '');
    echo '<div class="filter-bar mb-2">';
    foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first'] as $key => $label) {
        $q = array_merge($extraQuery, ['sort' => $key]);
        $href = adminUrl($basePath . '?' . http_build_query($q));
        $cls = $activeSort === $key ? 'active' : '';
        echo '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '">' . htmlspecialchars($label) . '</a>';
    }
    echo '</div>';
}

/**
 * Status tabs + search for admin list pages.
 *
 * @param string $basePath e.g. admin/students.php
 * @param array<string, string> $statusTabs map status key => label (counts optional via $counts)
 * @param array<string, int>|null $counts
 */
function renderAdminListToolbar(
    string $basePath,
    string $activeStatus,
    string $searchQuery,
    array $statusTabs,
    ?array $counts = null
): void {
    $qParam = $searchQuery !== '' ? '&q=' . rawurlencode($searchQuery) : '';
    echo '<div class="admin-toolbar">';
    echo '<div class="filter-bar" style="margin-bottom:0">';
    foreach ($statusTabs as $key => $label) {
        $href = adminUrl($basePath . '?status=' . rawurlencode($key) . $qParam);
        $cls = $activeStatus === $key ? 'active' : '';
        $cnt = $counts[$key] ?? null;
        echo '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '">'
            . htmlspecialchars($label);
        if ($cnt !== null) {
            echo ' <span style="opacity:0.75">(' . (int) $cnt . ')</span>';
        }
        echo '</a>';
    }
    echo '</div>';
    echo '<form method="get" action="' . htmlspecialchars(adminUrl($basePath)) . '" class="admin-search-form">';
    echo '<input type="hidden" name="status" value="' . htmlspecialchars($activeStatus) . '">';
    echo '<input type="search" name="q" class="form-control" placeholder="Search name, email, phone…" value="'
        . htmlspecialchars($searchQuery) . '" aria-label="Search">';
    echo '<button type="submit" class="btn-search"><i class="fas fa-search"></i></button>';
    if ($searchQuery !== '') {
        echo '<a href="' . htmlspecialchars(adminUrl($basePath . '?status=' . rawurlencode($activeStatus))) . '" class="btn-cancel" style="padding:0.5rem 0.75rem;font-size:0.8rem">Clear</a>';
    }
    echo '</form></div>';
}

/**
 * @param array<string, scalar|null> $query
 */
function renderAdminPagination(int $page, int $totalPages, int $total, string $adminPath, array $query = []): void
{
    if ($totalPages <= 1 && $total <= 0) {
        return;
    }

    $base = adminUrl($adminPath);
    $build = static function (int $p) use ($base, $query): string {
        $q = array_merge($query, ['page' => $p]);
        $qs = http_build_query($q);
        return $base . ($qs !== '' ? '?' . $qs : '');
    };

    $from = $total > 0 ? (($page - 1) * (int) ($query['per_page'] ?? 25)) + 1 : 0;
    $per = (int) ($query['per_page'] ?? 25);
    $to = min($total, $page * $per);

    echo '<nav class="admin-pagination" aria-label="Pagination">';
    echo '<span style="font-size:0.82rem;color:var(--cyber-muted)">';
    if ($total > 0) {
        echo 'Showing ' . $from . '–' . $to . ' of ' . number_format($total);
    } else {
        echo 'No results';
    }
    echo '</span><div class="page-links">';

    if ($page > 1) {
        echo '<a href="' . htmlspecialchars($build($page - 1)) . '"><i class="fas fa-chevron-left"></i> Prev</a>';
    } else {
        echo '<span class="disabled">Prev</span>';
    }

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            echo '<a href="#" class="active" onclick="return false">' . $i . '</a>';
        } else {
            echo '<a href="' . htmlspecialchars($build($i)) . '">' . $i . '</a>';
        }
    }

    if ($page < $totalPages) {
        echo '<a href="' . htmlspecialchars($build($page + 1)) . '">Next <i class="fas fa-chevron-right"></i></a>';
    } else {
        echo '<span class="disabled">Next</span>';
    }
    echo '</div></nav>';
}

function renderAdminMaskedPasswordField(): void
{
    echo '<div class="password-masked" aria-hidden="true"><i class="fas fa-lock"></i> ••••••••••••</div>';
    echo '<p class="password-hint"><i class="fas fa-shield-alt"></i> Passwords are stored as one-way hashes. They cannot be viewed or recovered — use <strong>Reset Password</strong> below to set a new one.</p>';
}

function renderAdminPageEnd(array $extraScripts = []): void
{
    ?>
  </div>
</main>
<?php
    if (!function_exists('renderChatbotWidgetAssets')) {
        require_once __DIR__ . '/chatbot-widget.php';
    }
    renderChatbotWidgetAssets(true);
    renderSiteScripts(false);
?>
<script>
window.CYBEORCH_ADMIN_ACTIONS = {
  url: <?= json_encode(adminUrl('record-action.php'), JSON_THROW_ON_ERROR) ?>,
  csrf: <?= json_encode(generateCSRF(), JSON_THROW_ON_ERROR) ?>
};
</script>
<script src="<?= htmlspecialchars(url('assets/js/admin-record-actions.js')) ?>"></script>
<?php foreach ($extraScripts as $scriptPath): ?>
<script src="<?= htmlspecialchars(url($scriptPath)) ?>"></script>
<?php endforeach; ?>
<script>
function toggleAdminSidebar() {
  document.querySelector('.sidebar')?.classList.toggle('open');
  document.getElementById('adminSidebarOverlay')?.classList.toggle('open');
}
</script>
</body>
</html>
    <?php
}
