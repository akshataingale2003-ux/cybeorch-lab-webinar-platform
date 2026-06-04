<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function supportTicketPriorities(): array
{
    return [
        'low'    => ['label' => 'Low', 'color' => '#7a8fa6', 'desc' => 'General questions'],
        'medium' => ['label' => 'Medium', 'color' => '#00d4ff', 'desc' => 'Standard requests'],
        'high'   => ['label' => 'High', 'color' => '#ff6b35', 'desc' => 'Blocking issues'],
        'urgent' => ['label' => 'Urgent', 'color' => '#ff4444', 'desc' => 'Critical — immediate'],
    ];
}

function supportTicketStatuses(): array
{
    return [
        'open'             => ['label' => 'Open', 'class' => 'st-open'],
        'in_progress'      => ['label' => 'In Progress', 'class' => 'st-progress'],
        'waiting_customer' => ['label' => 'Waiting on You', 'class' => 'st-waiting'],
        'resolved'         => ['label' => 'Resolved', 'class' => 'st-resolved'],
        'closed'           => ['label' => 'Closed', 'class' => 'st-closed'],
    ];
}

function ensureSupportTicketSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    db()->execute("CREATE TABLE IF NOT EXISTS support_ticket_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(40) DEFAULT 'fa-folder',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1
    )");

    db()->execute("CREATE TABLE IF NOT EXISTS support_teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(50) UNIQUE NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(40) DEFAULT 'fa-users',
        is_active TINYINT(1) DEFAULT 1
    )");

    db()->execute("CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_no VARCHAR(24) UNIQUE NOT NULL,
        user_id INT DEFAULT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(15) DEFAULT NULL,
        category_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
        status ENUM('open','in_progress','waiting_customer','resolved','closed') DEFAULT 'open',
        assigned_team_id INT DEFAULT NULL,
        assigned_admin_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        resolved_at DATETIME DEFAULT NULL,
        INDEX idx_ticket_email (email),
        INDEX idx_ticket_status (status)
    )");

    db()->execute("CREATE TABLE IF NOT EXISTS support_ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        sender_type ENUM('user','admin') NOT NULL,
        sender_user_id INT DEFAULT NULL,
        sender_admin_id INT DEFAULT NULL,
        message TEXT NOT NULL,
        is_internal TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reply_ticket (ticket_id)
    )");

    seedSupportTicketDefaults();
    $done = true;
}

function seedSupportTicketDefaults(): void
{
    $cats = [
        ['enrollment', 'Enrollment & Programs', 'Webinar and bootcamp registration', 'fa-graduation-cap', 1],
        ['payment', 'Payments & Billing', 'Transactions and wallet', 'fa-credit-card', 2],
        ['technical', 'Technical / Live Session', 'Access links and platform errors', 'fa-laptop-code', 3],
        ['refund', 'Refunds & Cancellations', 'Refund requests', 'fa-rotate-left', 4],
        ['account', 'Account & Login', 'Password and sign-in', 'fa-key', 5],
        ['certificate', 'Certificates', 'Certificate eligibility', 'fa-certificate', 6],
        ['other', 'Other', 'General enquiries', 'fa-ellipsis', 99],
    ];
    foreach ($cats as [$slug, $name, $desc, $icon, $ord]) {
        if (!db()->fetchOne('SELECT id FROM support_ticket_categories WHERE slug = ?', [$slug])) {
            db()->execute(
                'INSERT INTO support_ticket_categories (slug, name, description, icon, sort_order) VALUES (?,?,?,?,?)',
                [$slug, $name, $desc, $icon, $ord]
            );
        }
    }
    $teams = [
        ['enrollment', 'Enrollment Team', 'Registrations and access', 'fa-user-graduate'],
        ['billing', 'Billing Team', 'Payments and refunds', 'fa-file-invoice-dollar'],
        ['technical', 'Technical Support', 'Platform and sessions', 'fa-screwdriver-wrench'],
        ['general', 'General Support', 'Other requests', 'fa-headset'],
    ];
    foreach ($teams as [$slug, $name, $desc, $icon]) {
        if (!db()->fetchOne('SELECT id FROM support_teams WHERE slug = ?', [$slug])) {
            db()->execute('INSERT INTO support_teams (slug, name, description, icon) VALUES (?,?,?,?)', [$slug, $name, $desc, $icon]);
        }
    }
}

function getSupportCategories(): array
{
    ensureSupportTicketSchema();
    return db()->fetchAll('SELECT * FROM support_ticket_categories WHERE is_active=1 ORDER BY sort_order, name');
}

function getSupportTeams(): array
{
    ensureSupportTicketSchema();
    return db()->fetchAll('SELECT * FROM support_teams WHERE is_active=1 ORDER BY name');
}

function generateTicketNo(): string
{
    $p = 'TKT-' . date('Ymd') . '-';
    for ($i = 0; $i < 10; $i++) {
        $no = $p . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        if (!db()->fetchOne('SELECT id FROM support_tickets WHERE ticket_no=?', [$no])) {
            return $no;
        }
    }
    return $p . strtoupper(bin2hex(random_bytes(4)));
}

function defaultTeamForCategory(int $catId): ?int
{
    $cat = db()->fetchOne('SELECT slug FROM support_ticket_categories WHERE id=?', [$catId]);
    if (!$cat) {
        return null;
    }
    $map = ['enrollment' => 'enrollment', 'payment' => 'billing', 'refund' => 'billing', 'technical' => 'technical', 'account' => 'general', 'certificate' => 'enrollment', 'other' => 'general'];
    $t = db()->fetchOne('SELECT id FROM support_teams WHERE slug=?', [$map[$cat['slug']] ?? 'general']);
    return $t ? (int) $t['id'] : null;
}

function createSupportTicket(array $d): int
{
    ensureSupportTicketSchema();
    $prio = $d['priority'] ?? 'medium';
    if (!isset(supportTicketPriorities()[$prio])) {
        $prio = 'medium';
    }
    $ticketNo = generateTicketNo();
    $tid = db()->insert(
        'INSERT INTO support_tickets (ticket_no,user_id,name,email,phone,category_id,subject,message,priority,assigned_team_id) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$ticketNo, $d['user_id'] ?? null, $d['name'], $d['email'], $d['phone'] ?? null, $d['category_id'], $d['subject'], $d['message'], $prio, defaultTeamForCategory((int) $d['category_id'])]
    );
    db()->insert('INSERT INTO support_ticket_replies (ticket_id,sender_type,sender_user_id,message) VALUES (?,?,?,?)', [$tid, 'user', $d['user_id'] ?? null, $d['message']]);
    if (!empty($d['user_id'])) {
        require_once __DIR__ . '/helpers.php';
        sendNotification((int) $d['user_id'], 'support', 'Ticket created', 'Your support ticket was submitted.', $tid, 'support_ticket');
    }

    require_once __DIR__ . '/form-submissions.php';
    recordFormSubmission([
        'form_key'          => 'support-ticket',
        'form_label'        => 'Support desk ticket',
        'source_page'       => 'supportdesk.php',
        'full_name'         => $d['name'],
        'email'             => $d['email'],
        'phone'             => $d['phone'] ?? null,
        'summary'           => ($d['subject'] ?? '') . ' — ' . $ticketNo,
        'payload'           => $d,
        'storage_table'     => 'support_tickets',
        'storage_record_id' => $tid,
    ]);

    return $tid;
}

function getTicketByNo(string $no): ?array
{
    ensureSupportTicketSchema();
    return db()->fetchOne(
        "SELECT t.*,c.name category_name,tm.name team_name,a.full_name admin_name
         FROM support_tickets t JOIN support_ticket_categories c ON c.id=t.category_id
         LEFT JOIN support_teams tm ON tm.id=t.assigned_team_id LEFT JOIN admin a ON a.id=t.assigned_admin_id
         WHERE t.ticket_no=?",
        [strtoupper(trim($no))]
    );
}

function getTicketById(int $id): ?array
{
    ensureSupportTicketSchema();
    return db()->fetchOne(
        "SELECT t.*,c.name category_name,tm.name team_name,a.full_name admin_name
         FROM support_tickets t JOIN support_ticket_categories c ON c.id=t.category_id
         LEFT JOIN support_teams tm ON tm.id=t.assigned_team_id LEFT JOIN admin a ON a.id=t.assigned_admin_id WHERE t.id=?",
        [$id]
    );
}

function verifyTicketAccess(array $t, string $email = ''): bool
{
    require_once __DIR__ . '/helpers.php';
    startSession();
    if (isLoggedIn()) {
        if (!empty($t['user_id']) && (int) $t['user_id'] === (int) $_SESSION['user_id']) {
            return true;
        }
        $u = db()->fetchOne('SELECT email FROM users WHERE id=?', [$_SESSION['user_id']]);
        if ($u && strcasecmp($t['email'], $u['email']) === 0) {
            return true;
        }
    }
    return $email !== '' && strcasecmp($t['email'], trim($email)) === 0;
}

function getTicketReplies(int $tid, bool $internal = false): array
{
    $sql = "SELECT r.*,u.full_name user_name,a.full_name admin_name FROM support_ticket_replies r
            LEFT JOIN users u ON u.id=r.sender_user_id LEFT JOIN admin a ON a.id=r.sender_admin_id WHERE r.ticket_id=?";
    if (!$internal) {
        $sql .= ' AND r.is_internal=0';
    }
    return db()->fetchAll($sql . ' ORDER BY r.created_at ASC', [$tid]);
}

function addTicketReply(int $tid, string $type, string $msg, ?int $uid = null, ?int $aid = null, bool $int = false): void
{
    db()->insert('INSERT INTO support_ticket_replies (ticket_id,sender_type,sender_user_id,sender_admin_id,message,is_internal) VALUES (?,?,?,?,?,?)', [$tid, $type, $uid, $aid, $msg, $int ? 1 : 0]);
    db()->execute('UPDATE support_tickets SET updated_at=NOW() WHERE id=?', [$tid]);
}

function updateTicketStatus(int $id, string $st): void
{
    if (!isset(supportTicketStatuses()[$st])) {
        return;
    }
    $x = in_array($st, ['resolved', 'closed'], true) ? ', resolved_at=COALESCE(resolved_at,NOW())' : '';
    db()->execute("UPDATE support_tickets SET status=?{$x} WHERE id=?", [$st, $id]);
}

function assignTicket(int $id, ?int $team, ?int $admin): void
{
    db()->execute('UPDATE support_tickets SET assigned_team_id=?,assigned_admin_id=?,status=IF(status=\'open\',\'in_progress\',status) WHERE id=?', [$team, $admin, $id]);
}

function updateTicketPriority(int $id, string $p): void
{
    if (isset(supportTicketPriorities()[$p])) {
        db()->execute('UPDATE support_tickets SET priority=? WHERE id=?', [$p, $id]);
    }
}

function getUserTickets(int $uid, string $email): array
{
    ensureSupportTicketSchema();
    return db()->fetchAll(
        "SELECT t.*,c.name category_name,tm.name team_name FROM support_tickets t
         JOIN support_ticket_categories c ON c.id=t.category_id LEFT JOIN support_teams tm ON tm.id=t.assigned_team_id
         WHERE t.user_id=? OR LOWER(t.email)=LOWER(?) ORDER BY t.updated_at DESC",
        [$uid, $email]
    );
}

function countOpenTickets(): int
{
    ensureSupportTicketSchema();
    return (int) (db()->fetchOne("SELECT COUNT(*) c FROM support_tickets WHERE status IN ('open','in_progress','waiting_customer')")['c'] ?? 0);
}

function renderStatusBadge(string $s): string
{
    $i = supportTicketStatuses()[$s] ?? ['label' => $s, 'class' => 'st-open'];
    return '<span class="ticket-st ' . $i['class'] . '">' . htmlspecialchars($i['label']) . '</span>';
}

function renderPriorityBadge(string $p): string
{
    $i = supportTicketPriorities()[$p] ?? ['label' => $p, 'color' => '#7a8fa6'];
    return '<span class="ticket-prio" style="--c:' . $i['color'] . '">' . htmlspecialchars($i['label']) . '</span>';
}

function renderSupportDeskStyles(): void
{
    echo '<style>'
        .'.desk-features{margin-bottom:2rem}'
        .'.desk-features>.col-md-6,.desk-features>.col-md-4{display:flex}'
        .'.desk-features .feature-card{width:100%;flex:1}'
        .'.feature-card{background:var(--cyber-card);border:1px solid var(--cyber-border);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;min-height:100%}'
        .'.feature-card-head{padding:1rem 1.2rem;border-bottom:1px solid var(--cyber-border);display:flex;align-items:center;gap:.85rem;background:rgba(0,0,0,.12)}'
        .'.feature-card-head .ico{width:42px;height:42px;border-radius:10px;background:rgba(0,212,255,.1);border:1px solid var(--cyber-border);display:flex;align-items:center;justify-content:center;color:var(--cyber-accent)}'
        .'.feature-card-head h3{font-family:\'Rajdhani\',sans-serif;font-size:1.05rem;color:#fff;margin:0}'
        .'.feature-card-head p{font-size:.76rem;color:var(--cyber-muted);margin:.1rem 0 0}'
        .'.feature-card-body{padding:1.2rem;flex:1}'
        .'.cat-item{display:flex;gap:.6rem;padding:.55rem .65rem;border:1px solid var(--cyber-border);border-radius:8px;margin-bottom:.45rem;font-size:.84rem}'
        .'.cat-item i{color:var(--cyber-accent);margin-top:.1rem}'
        .'.prio-item{display:flex;justify-content:space-between;align-items:center;padding:.5rem .7rem;border:1px solid var(--cyber-border);border-radius:8px;margin-bottom:.4rem;font-size:.84rem}'
        .'.prio-dot{width:9px;height:9px;border-radius:50%;display:inline-block;margin-right:.4rem}'
        .'.team-item{display:flex;gap:.7rem;padding:.55rem 0;border-bottom:1px solid rgba(0,212,255,.06);font-size:.84rem}'
        .'.team-item i{width:34px;height:34px;border-radius:8px;background:rgba(0,255,136,.08);color:var(--cyber-green);display:flex;align-items:center;justify-content:center}'
        .'.sd-form .form-control,.sd-form .form-select{background:rgba(255,255,255,.05)!important;border:1px solid var(--cyber-border)!important;color:var(--cyber-text)!important;border-radius:6px!important;padding:.65rem .9rem!important}'
        .'.sd-form .form-label{color:var(--cyber-muted);font-size:.8rem}'
        .'.btn-sd{background:var(--cyber-accent);color:#050b18;border:none;padding:.7rem 1.2rem;border-radius:6px;font-weight:700;width:100%;cursor:pointer}'
        .'.btn-sd:hover{background:var(--cyber-green)}'
        .'.ticket-st,.ticket-prio{font-size:.68rem;padding:.2rem .5rem;border-radius:4px;font-weight:600}'
        .'.st-open{background:rgba(0,212,255,.12);color:var(--cyber-accent)}.st-progress{background:rgba(255,107,53,.12);color:var(--cyber-orange)}'
        .'.st-waiting{background:rgba(255,200,0,.12);color:#ffc800}.st-resolved{background:rgba(0,255,136,.12);color:var(--cyber-green)}.st-closed{background:rgba(255,255,255,.08);color:var(--cyber-muted)}'
        .'.ticket-prio{border:1px solid var(--c);color:var(--c)}'
        .'.track-box{background:rgba(0,212,255,.06);border:1px solid var(--cyber-border);border-radius:8px;padding:1rem;margin-top:.75rem}'
        .'.ticket-link{font-family:\'Rajdhani\',sans-serif;font-weight:700;color:var(--cyber-accent)}'
        .'.my-row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid rgba(0,212,255,.06);font-size:.84rem}'
        .'.chat-mini{max-height:200px;overflow-y:auto;margin-bottom:.75rem}'
        .'.chat-mini .cb{padding:.55rem .75rem;border-radius:8px;margin-bottom:.4rem;font-size:.82rem;max-width:95%}'
        .'.chat-mini .cb.user{background:rgba(0,212,255,.1);border:1px solid var(--cyber-border)}'
        .'.chat-mini .cb.admin{background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2);margin-left:auto}'
        .'</style>';
}
