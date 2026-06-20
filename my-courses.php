<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/recorded-sessions.php';
require_once __DIR__ . '/includes/student-layout.php';

startSession();
requireLogin();

ensureRecordedSessionsSchema();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$ctx = studentContext();
$userId = (int) $ctx['userId'];
$sessionId = (int) ($_GET['session'] ?? 0);

if ($sessionId > 0) {
    $session = recordedSessionGetById($sessionId);
    if (!$session || !recordedSessionUserHasAccess($userId, $session)) {
        redirectWith('my-courses.php', 'error', 'This course is not available or your ' . COURSE_ACCESS_MONTHS . '-month access has expired.');
    }

    $watchStats = recordedSessionGetWatchStats($userId, $sessionId);
    $enrollment = recordedSessionGetUserEnrollment($userId, $session);
    $canWatch = $watchStats['can_watch'];

    renderStudentHead('My Courses — ' . ($session['title'] ?? 'Recording'));
    renderStudentSidebar('my-courses', $ctx);
    ?>
<main class="main">
  <?php renderPortalTopbar('<i class="fas fa-circle-play me-2" style="color:var(--cyber-accent)"></i>Recorded Session'); ?>
  <div class="content">
    <a href="<?= url('my-courses.php') ?>" class="btn-sm-link" style="display:inline-flex;align-items:center;gap:.35rem;margin-bottom:1rem">
      <i class="fas fa-arrow-left"></i> Back to My Courses
    </a>
    <div class="card-panel">
      <div class="mb-2">
        <span class="badge-status badge-read"><?= htmlspecialchars(recordedSessionLinkedProgramTypeLabel($session)) ?></span>
        <span style="color:var(--cyber-muted);font-size:.82rem;margin-left:.5rem"><?= htmlspecialchars(recordedSessionLinkedProgramLabel($session)) ?></span>
      </div>
      <h2 style="font-family:'Rajdhani',sans-serif;margin-bottom:.75rem"><?= htmlspecialchars($session['title']) ?></h2>
      <?php if ($enrollment): ?>
      <p style="font-size:.82rem;color:var(--cyber-muted);margin-bottom:.75rem">
        <i class="fas fa-clock me-1"></i>Access until <?= htmlspecialchars(recordedSessionFormatExpiryLabel($enrollment['expires_at'])) ?>
      </p>
      <?php endif; ?>
      <?php if (!empty($session['description'])): ?>
      <p style="color:var(--cyber-muted);font-size:.9rem;line-height:1.6;margin-bottom:1.25rem"><?= nl2br(htmlspecialchars($session['description'])) ?></p>
      <?php endif; ?>
      <?php if (!empty($session['video_path']) && $canWatch): ?>
      <?php recordedSessionRenderVideoPlayer($sessionId, [
          'track_watch'  => true,
          'watch_stats'  => $watchStats,
          'mime'         => (string) ($session['video_mime'] ?? 'video/mp4'),
      ]); ?>
      <?php elseif (!empty($session['video_path'])): ?>
      <div class="text-center" style="color:var(--cyber-muted);padding:2rem;border:1px solid var(--cyber-border);border-radius:10px">
        <i class="fas fa-ban d-block mb-2" style="font-size:1.5rem;color:#ff8888"></i>
        You have used all <?= RECORDED_SESSION_MAX_WATCHES ?> full views for this video.
        <p style="font-size:.82rem;margin-top:.5rem;margin-bottom:0">Partial views do not count — only completed full watches use your limit.</p>
      </div>
      <?php else: ?>
      <div class="text-center" style="color:var(--cyber-muted);padding:2rem">Video is not available yet.</div>
      <?php endif; ?>
    </div>
  </div>
</main>
    <?php
    renderStudentLayoutEnd();
    exit;
}

$sessions = recordedSessionListForUser($userId);

renderStudentHead('My Courses');
renderStudentSidebar('my-courses', $ctx);

$flash = getFlash();
?>
<main class="main">
  <?php renderPortalTopbar('<i class="fas fa-book-open me-2" style="color:var(--cyber-accent)"></i>My Courses'); ?>
  <div class="content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= ($flash['type'] ?? '') === 'error' ? 'error' : 'success' ?>" style="padding:.85rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:.88rem;<?= ($flash['type'] ?? '') === 'error' ? 'background:rgba(255,68,68,0.1);border:1px solid rgba(255,68,68,0.3);color:#ff6666' : 'background:rgba(0,255,136,0.1);border:1px solid rgba(0,255,136,0.3);color:var(--cyber-green)' ?>">
      <?= htmlspecialchars((string) $flash['message']) ?>
    </div>
    <?php endif; ?>

    <p style="color:var(--cyber-muted);font-size:.88rem;margin-bottom:1.25rem">
      Recorded sessions from webinars and bootcamps you are enrolled in appear here automatically when published by admin.
      Access lasts <?= COURSE_ACCESS_MONTHS ?> months from enrollment. Each video allows up to <?= RECORDED_SESSION_MAX_WATCHES ?> full completed views.
    </p>

    <?php if (empty($sessions)): ?>
    <div class="card-panel text-center" style="color:var(--cyber-muted)">
      <i class="fas fa-circle-play d-block mb-3" style="font-size:2rem;color:var(--cyber-accent);opacity:.85"></i>
      <p style="margin:0">No active courses available for your enrollments.</p>
      <p style="margin:.75rem 0 0;font-size:.88rem">
        Register for a <a href="<?= url('my-webinars.php') ?>" style="color:var(--cyber-accent)">webinar</a>
        or <a href="<?= url('my-bootcamps.php') ?>" style="color:var(--cyber-accent)">bootcamp</a> to unlock recordings here.
      </p>
    </div>
    <?php else: foreach ($sessions as $s):
        $completedViews = (int) ($s['completed_views'] ?? 0);
        $remaining = max(0, RECORDED_SESSION_MAX_WATCHES - $completedViews);
        $canWatch = $remaining > 0;
    ?>
    <div class="card-panel">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div style="flex:1;min-width:200px">
          <div class="mb-2">
            <span class="badge-status badge-read"><?= htmlspecialchars(recordedSessionLinkedProgramTypeLabel($s)) ?></span>
            <?php if (!$canWatch): ?>
            <span class="badge-status badge-pending">View limit reached</span>
            <?php endif; ?>
          </div>
          <h3 style="font-family:'Rajdhani',sans-serif;margin-bottom:.35rem"><?= htmlspecialchars($s['title']) ?></h3>
          <p style="color:var(--cyber-muted);font-size:.85rem;margin:0">
            <?= htmlspecialchars(recordedSessionLinkedProgramLabel($s)) ?>
          </p>
          <p style="font-size:.78rem;color:var(--cyber-muted);margin-top:.35rem;margin-bottom:0">
            <i class="fas fa-clock me-1"></i>Access until <?= htmlspecialchars(recordedSessionFormatExpiryLabel((string) ($s['access_expires_at'] ?? ''))) ?>
            · <?= $completedViews ?>/<?= RECORDED_SESSION_MAX_WATCHES ?> full views used
          </p>
          <?php if (!empty($s['description'])):
              $descPreview = (string) $s['description'];
              $descPreview = function_exists('mb_strimwidth')
                  ? mb_strimwidth($descPreview, 0, 140, '…')
                  : (strlen($descPreview) > 140 ? substr($descPreview, 0, 137) . '…' : $descPreview);
          ?>
          <p style="color:var(--cyber-muted);font-size:.82rem;margin-top:.5rem;margin-bottom:0;line-height:1.5">
            <?= htmlspecialchars($descPreview) ?>
          </p>
          <?php endif; ?>
        </div>
        <div class="text-end">
          <?php if ($canWatch): ?>
          <a href="<?= url('my-courses.php?session=' . (int) $s['id']) ?>" class="btn-cyber">
            <i class="fas fa-play me-1"></i> Watch Recording
          </a>
          <?php else: ?>
          <span class="btn-cyber" style="opacity:.45;pointer-events:none;cursor:not-allowed">
            <i class="fas fa-ban me-1"></i> Limit reached
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</main>
<?php renderStudentLayoutEnd(); ?>
