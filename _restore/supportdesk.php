<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/legal-public.php';
require_once __DIR__ . '/includes/support-tickets.php';

startSession();

$dbOk = true;
$categories = $teams = $priorities = [];
$myTickets = [];
$trackTicket = null;
$prefillName = $prefillEmail = $prefillPhone = '';
$userId = null;

try {
    ensureSupportTicketSchema();
    $categories = getSupportCategories();
    $teams      = getSupportTeams();
    $priorities = supportTicketPriorities();
} catch (Throwable $e) {
    $dbOk = false;
    if (!defined('DB_ERROR_MESSAGE')) {
        define('DB_ERROR_MESSAGE', $e->getMessage());
    }
}

if (isLoggedIn()) {
    $userId = (int) $_SESSION['user_id'];
    $u = $dbOk ? db()->fetchOne('SELECT full_name,email,phone FROM users WHERE id=?', [$userId]) : null;
    if ($u) {
        $prefillName  = $u['full_name'] ?? '';
        $prefillEmail = $u['email'] ?? '';
        $prefillPhone = $u['phone'] ?? '';
        if ($dbOk) {
            $myTickets = getUserTickets($userId, $prefillEmail);
        }
    }
}

if ($dbOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        redirectWith('supportdesk.php', 'error', 'Invalid request.');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'create_ticket') {
        $name = sanitize($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $subject = sanitize($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $ok = false;
        foreach ($categories as $c) {
            if ((int) $c['id'] === $categoryId) {
                $ok = true;
                break;
            }
        }
        if (strlen($name) < 2 || !isValidEmail($email) || !$ok || strlen($subject) < 3 || strlen($message) < 10) {
            redirectWith('supportdesk.php#create', 'error', 'Please complete all required fields.');
        }
        try {
            $tid = createSupportTicket([
                'user_id' => $userId, 'name' => $name, 'email' => $email,
                'phone' => sanitize($_POST['phone'] ?? ''), 'category_id' => $categoryId,
                'subject' => $subject, 'message' => $message, 'priority' => $priority,
            ]);
            $t = getTicketById($tid);
            setFlash('success', 'Ticket ' . $t['ticket_no'] . ' created successfully.');
            header('Location: ' . url('ticket.php?no=' . rawurlencode($t['ticket_no']) . '&email=' . rawurlencode($email)));
            exit;
        } catch (Throwable $e) {
            redirectWith('supportdesk.php#create', 'error', 'Could not create ticket.');
        }
    }

    if ($action === 'track_ticket') {
        $no = strtoupper(trim($_POST['ticket_no'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $trackTicket = getTicketByNo($no);
        if (!$trackTicket || !verifyTicketAccess($trackTicket, $email)) {
            redirectWith('supportdesk.php#track', 'error', 'Ticket not found or email mismatch.');
        }
    }
}

if ($dbOk && !empty($_GET['ticket'])) {
    $trackTicket = getTicketByNo((string) $_GET['ticket']);
    if ($trackTicket && !verifyTicketAccess($trackTicket, $prefillEmail)) {
        $trackTicket = null;
    }
}

$chatPreview = [];
if ($trackTicket) {
    $chatPreview = getTicketReplies((int) $trackTicket['id']);
}

renderLegalPublicPageStart('Support Desk', 'Ticket system — categories, generation, tracking, priority, teams, and chat.', 'support-desk');
renderSupportDeskStyles();
?>

<header class="page-hero">
  <div class="container">
    <h1>Support <span class="accent">Desk</span></h1>
    <p>Create tickets, track status, set priority, get assigned to a team, and chat with support.</p>
  </div>
</header>

<section class="section pt-0">
  <div class="container">
    <?= showFlash() ?>

    <?php if (!$dbOk): ?>
    <div class="alert alert-danger">Start <strong>MySQL</strong> in XAMPP and refresh. <?= htmlspecialchars(defined('DB_ERROR_MESSAGE') ? DB_ERROR_MESSAGE : '') ?></div>
    <?php else: ?>

    <div class="desk-features">

      <!-- 1. Ticket Categories -->
      <div class="feature-card" id="categories">
        <div class="feature-card-head">
          <div class="ico"><i class="fas fa-tags"></i></div>
          <div><h3>Ticket Categories</h3><p>Choose the right topic</p></div>
        </div>
        <div class="feature-card-body">
          <?php foreach ($categories as $cat): ?>
          <div class="cat-item">
            <i class="fas <?= htmlspecialchars($cat['icon'] ?? 'fa-folder') ?>"></i>
            <div><strong style="color:#fff"><?= htmlspecialchars($cat['name']) ?></strong><br>
              <span style="color:var(--cyber-muted);font-size:.8rem"><?= htmlspecialchars($cat['description'] ?? '') ?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 2. Ticket Generation Form -->
      <div class="feature-card" id="create">
        <div class="feature-card-head">
          <div class="ico"><i class="fas fa-plus-circle"></i></div>
          <div><h3>Ticket Generation</h3><p>Submit a new request</p></div>
        </div>
        <div class="feature-card-body sd-form">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="create_ticket">
            <div class="mb-2"><label class="form-label">Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($prefillName) ?>"></div>
            <div class="mb-2"><label class="form-label">Email *</label>
              <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($prefillEmail) ?>"></div>
            <div class="mb-2"><label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($prefillPhone) ?>"></div>
            <div class="mb-2"><label class="form-label">Category *</label>
              <select name="category_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="mb-2"><label class="form-label">Priority *</label>
              <select name="priority" class="form-select" required>
                <?php foreach ($priorities as $k => $p): ?>
                <option value="<?= $k ?>"<?= $k === 'medium' ? ' selected' : '' ?>><?= htmlspecialchars($p['label']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="mb-2"><label class="form-label">Subject *</label>
              <input type="text" name="subject" class="form-control" required minlength="3"></div>
            <div class="mb-2"><label class="form-label">Message *</label>
              <textarea name="message" class="form-control" rows="3" required minlength="10"></textarea></div>
            <button type="submit" class="btn-sd"><i class="fas fa-ticket-alt me-2"></i>Generate Ticket</button>
          </form>
        </div>
      </div>

      <!-- 3. Track Ticket Status -->
      <div class="feature-card" id="track">
        <div class="feature-card-head">
          <div class="ico"><i class="fas fa-search"></i></div>
          <div><h3>Track Ticket Status</h3><p>Lookup by ticket number</p></div>
        </div>
        <div class="feature-card-body sd-form">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
            <input type="hidden" name="action" value="track_ticket">
            <div class="mb-2"><label class="form-label">Ticket number *</label>
              <input type="text" name="ticket_no" class="form-control" required placeholder="TKT-..." style="text-transform:uppercase"
                value="<?= htmlspecialchars($_GET['ticket'] ?? $_POST['ticket_no'] ?? '') ?>"></div>
            <div class="mb-2"><label class="form-label">Email *</label>
              <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($prefillEmail) ?>"></div>
            <button type="submit" class="btn-sd"><i class="fas fa-search me-2"></i>Track Status</button>
          </form>
          <?php if ($trackTicket): ?>
          <div class="track-box">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
              <span class="ticket-link"><?= htmlspecialchars($trackTicket['ticket_no']) ?></span>
              <div><?= renderStatusBadge($trackTicket['status']) ?> <?= renderPriorityBadge($trackTicket['priority']) ?></div>
            </div>
            <div style="color:#fff;font-size:.9rem"><?= htmlspecialchars($trackTicket['subject']) ?></div>
            <div style="font-size:.78rem;color:var(--cyber-muted);margin-top:.4rem">
              Team: <?= htmlspecialchars($trackTicket['team_name'] ?? 'Assigning...') ?> · <?= timeAgo($trackTicket['updated_at']) ?>
            </div>
            <a href="<?= url('ticket.php?no=' . rawurlencode($trackTicket['ticket_no']) . '&email=' . rawurlencode($trackTicket['email'])) ?>"
               class="btn-sd mt-2" style="display:inline-block;width:auto;text-decoration:none;text-align:center">Open full ticket</a>
          </div>
          <?php endif; ?>
          <?php if (isLoggedIn() && $myTickets): ?>
          <div class="mt-3" style="font-size:.72rem;color:var(--cyber-muted);text-transform:uppercase">Your tickets</div>
          <?php foreach (array_slice($myTickets, 0, 3) as $mt): ?>
          <div class="my-row">
            <a href="<?= url('ticket.php?no=' . rawurlencode($mt['ticket_no'])) ?>" class="ticket-link"><?= htmlspecialchars($mt['ticket_no']) ?></a>
            <?= renderStatusBadge($mt['status']) ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- 4. Priority Levels -->
      <div class="feature-card" id="priority">
        <div class="feature-card-head">
          <div class="ico"><i class="fas fa-signal"></i></div>
          <div><h3>Priority Levels</h3><p>Set when creating a ticket</p></div>
        </div>
        <div class="feature-card-body">
          <?php foreach ($priorities as $k => $p): ?>
          <div class="prio-item">
            <span style="color:#fff"><span class="prio-dot" style="background:<?= $p['color'] ?>"></span><?= htmlspecialchars($p['label']) ?></span>
            <span style="color:var(--cyber-muted);font-size:.78rem"><?= htmlspecialchars($p['desc']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 5. Assigned Team -->
      <div class="feature-card" id="team">
        <div class="feature-card-head">
          <div class="ico"><i class="fas fa-users"></i></div>
          <div><h3>Assigned Team</h3><p>Auto-routed by category</p></div>
        </div>
        <div class="feature-card-body">
          <?php foreach ($teams as $tm): ?>
          <div class="team-item">
            <i class="fas <?= htmlspecialchars($tm['icon'] ?? 'fa-headset') ?>"></i>
            <div><strong style="color:#fff"><?= htmlspecialchars($tm['name']) ?></strong><br>
              <span style="color:var(--cyber-muted);font-size:.78rem"><?= htmlspecialchars($tm['description'] ?? '') ?></span></div>
          </div>
          <?php endforeach; ?>
          <?php if ($trackTicket && !empty($trackTicket['team_name'])): ?>
          <div class="track-box mt-2">Your ticket is assigned to: <strong style="color:var(--cyber-green)"><?= htmlspecialchars($trackTicket['team_name']) ?></strong></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- 6. Chat / Reply System -->
      <div class="feature-card" id="card-chat">
        <div class="feature-card-head">
          <div class="ico"><i class="fas fa-comments"></i></div>
          <div><h3>Chat / Reply System</h3><p>Two-way conversation on tickets</p></div>
        </div>
        <div class="feature-card-body">
          <?php if ($trackTicket): ?>
          <p style="font-size:.85rem;color:var(--cyber-muted);margin-bottom:.75rem">Preview for <strong class="ticket-link"><?= htmlspecialchars($trackTicket['ticket_no']) ?></strong>:</p>
          <div class="chat-mini">
            <?php foreach (array_slice($chatPreview, -4) as $r): ?>
            <div class="cb <?= $r['sender_type'] === 'admin' ? 'admin' : 'user' ?>">
              <div style="font-size:.68rem;color:var(--cyber-muted);margin-bottom:.2rem">
                <?= $r['sender_type'] === 'admin' ? 'Support' : 'You' ?> · <?= date('g:i A', strtotime($r['created_at'])) ?>
              </div>
              <?= nl2br(htmlspecialchars($r['message'])) ?>
            </div>
            <?php endforeach; ?>
          </div>
          <a href="<?= url('ticket.php?no=' . rawurlencode($trackTicket['ticket_no']) . '&email=' . rawurlencode($trackTicket['email'])) ?>" class="btn-sd" style="text-decoration:none;text-align:center;display:block">
            <i class="fas fa-comments me-2"></i>Reply &amp; view full chat
          </a>
          <?php elseif (isLoggedIn() && !empty($myTickets)): ?>
          <p style="font-size:.85rem;color:var(--cyber-muted)">Open a ticket to chat with support:</p>
          <a href="<?= url('ticket.php?no=' . rawurlencode($myTickets[0]['ticket_no'])) ?>" class="btn-sd" style="text-decoration:none;text-align:center;display:block">
            <i class="fas fa-comments me-2"></i>Chat on <?= htmlspecialchars($myTickets[0]['ticket_no']) ?>
          </a>
          <?php else: ?>
          <p style="font-size:.88rem;color:var(--cyber-muted);line-height:1.7;margin-bottom:1rem">
            After you create or track a ticket, open it to send and receive replies. Our assigned team responds within 24 hours (business days).
          </p>
          <a href="#create" class="btn-sd" style="text-decoration:none;text-align:center;display:block"><i class="fas fa-plus me-2"></i>Create a ticket to start chat</a>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div class="support-grid">
      <div class="support-tile"><h3>FAQ</h3><p>Quick answers.</p><a class="link" href="<?= url('faq.php') ?>">Browse FAQ</a></div>
      <div class="support-tile"><h3>Contact</h3><p>General enquiries.</p><a class="link" href="<?= url('contact.php') ?>">Contact form</a></div>
      <div class="support-tile"><h3>Refunds</h3><p>Policy info.</p><a class="link" href="<?= url('refund.php') ?>">Refund policy</a></div>
    </div>

    <?php endif; ?>
  </div>
</section>

<?php renderLegalPublicPageEnd(); ?>
