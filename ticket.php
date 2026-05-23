<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/legal-public.php';
require_once __DIR__ . '/includes/support-tickets.php';

startSession();
ensureSupportTicketSchema();

$no = strtoupper(trim($_GET['no'] ?? ''));
$email = trim($_GET['email'] ?? '');

if ($no === '') {
    header('Location: ' . url('supportdesk.php#track'));
    exit;
}

$ticket = getTicketByNo($no);
if (!$ticket) {
    redirectWith('supportdesk.php#track', 'error', 'Ticket not found.');
}

if (isLoggedIn()) {
    $u = db()->fetchOne('SELECT email FROM users WHERE id=?', [$_SESSION['user_id']]);
    if ($u) {
        $email = $u['email'];
    }
}

if (!verifyTicketAccess($ticket, $email)) {
    redirectWith('supportdesk.php#track', 'error', 'Access denied. Use the email on the ticket.');
}

$tid = (int) $ticket['id'];
$closed = in_array($ticket['status'], ['resolved', 'closed'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$closed) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        redirectWith('ticket.php?no=' . rawurlencode($no), 'error', 'Invalid request.');
    }
    $reply = trim($_POST['reply'] ?? '');
    if (strlen($reply) < 2) {
        redirectWith('ticket.php?no=' . rawurlencode($no), 'error', 'Reply too short.');
    }
    $uid = isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    addTicketReply($tid, 'user', $reply, $uid);
    if (in_array($ticket['status'], ['open', 'waiting_customer', 'resolved'], true)) {
        updateTicketStatus($tid, 'in_progress');
    }
    redirectWith('ticket.php?no=' . rawurlencode($no), 'success', 'Reply sent.');
}

$replies = getTicketReplies($tid);
$ticket  = getTicketById($tid);

renderLegalPublicPageStart('Ticket ' . $ticket['ticket_no'], '', 'support-desk');
renderSupportDeskStyles();
?>

<header class="page-hero" style="padding:2rem 0 1rem">
  <div class="container">
    <a href="<?= url('supportdesk.php') ?>" style="color:var(--cyber-muted);font-size:.88rem"><i class="fas fa-arrow-left"></i> Support Desk</a>
    <h1 class="mt-2" style="font-size:clamp(1.4rem,4vw,2rem)">Ticket <span class="accent"><?= htmlspecialchars($ticket['ticket_no']) ?></span></h1>
  </div>
</header>

<section class="section pt-0">
  <div class="container" style="max-width:800px">
    <?= showFlash() ?>

    <div class="feature-card">
      <div class="feature-card-head">
        <div class="ico"><i class="fas fa-ticket-alt"></i></div>
        <div class="flex-grow-1">
          <h3 style="margin:0"><?= htmlspecialchars($ticket['subject']) ?></h3>
          <p><?= htmlspecialchars($ticket['category_name']) ?></p>
        </div>
        <div><?= renderStatusBadge($ticket['status']) ?> <?= renderPriorityBadge($ticket['priority']) ?></div>
      </div>
      <div class="feature-card-body">
        <div class="row g-2 mb-3" style="font-size:.85rem">
          <div class="col-6 col-md-3"><span style="color:var(--cyber-muted)">Team</span><br><strong style="color:#fff"><?= htmlspecialchars($ticket['team_name'] ?? '—') ?></strong></div>
          <div class="col-6 col-md-3"><span style="color:var(--cyber-muted)">Agent</span><br><strong style="color:#fff"><?= htmlspecialchars($ticket['admin_name'] ?? '—') ?></strong></div>
          <div class="col-6 col-md-3"><span style="color:var(--cyber-muted)">Created</span><br><?= date('M j, Y', strtotime($ticket['created_at'])) ?></div>
          <div class="col-6 col-md-3"><span style="color:var(--cyber-muted)">Updated</span><br><?= timeAgo($ticket['updated_at']) ?></div>
        </div>

        <h4 style="font-family:'Rajdhani',sans-serif;color:#fff;font-size:1.1rem;margin-bottom:1rem"><i class="fas fa-comments me-2" style="color:var(--cyber-accent)"></i>Chat / Replies</h4>
        <div style="max-height:400px;overflow-y:auto;margin-bottom:1rem">
          <?php foreach ($replies as $r): ?>
          <?php $isA = $r['sender_type'] === 'admin'; ?>
          <div class="cb <?= $isA ? 'admin' : 'user' ?>" style="max-width:90%;padding:.75rem 1rem;border-radius:10px;margin-bottom:.6rem;font-size:.9rem;<?= $isA ? 'margin-left:auto;background:rgba(0,255,136,.08);border:1px solid rgba(0,255,136,.2)' : 'background:rgba(0,212,255,.1);border:1px solid var(--cyber-border)' ?>">
            <div style="font-size:.7rem;color:var(--cyber-muted);margin-bottom:.25rem">
              <?= $isA ? 'Support' . ($r['admin_name'] ? ' — ' . htmlspecialchars($r['admin_name']) : '') : htmlspecialchars($r['user_name'] ?? $ticket['name']) ?>
              · <?= date('M j, g:i A', strtotime($r['created_at'])) ?>
            </div>
            <?= nl2br(htmlspecialchars($r['message'])) ?>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($closed): ?>
        <div class="alert alert-secondary">Ticket is <?= htmlspecialchars($ticket['status']) ?>. <a href="<?= url('supportdesk.php#create') ?>" style="color:var(--cyber-accent)">Create new ticket</a></div>
        <?php else: ?>
        <form method="post" class="sd-form">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <label class="form-label">Your reply</label>
          <textarea name="reply" class="form-control mb-2" rows="4" required placeholder="Message support team..."></textarea>
          <button type="submit" class="btn-sd" style="width:auto"><i class="fas fa-paper-plane me-2"></i>Send Reply</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php renderLegalPublicPageEnd(); ?>
