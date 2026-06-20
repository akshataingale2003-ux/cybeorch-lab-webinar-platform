<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/webinar-attendance.php';
requireAdminLogin();

$attendanceTable = attendanceResolveAdminTable();
$attendanceEntity = attendanceAdminEntityForTable($attendanceTable);
$dbError = '';

$rows = adminDb(
    static fn () => webinarAttendanceFetchList(100),
    []
);

if (hasDbError()) {
    $dbError = dbErrorMessage();
    adminLogDbError($dbError);
}

renderAdminPageStart('Attendance', 'attendance', 'fa-user-check');
?>

<?php if ($dbError !== ''): ?>
<div class="alert alert-error" style="margin-bottom:1rem">
  <i class="fas fa-database me-2"></i>Could not load attendance records. Please refresh or contact support if this continues.
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">Webinar attendance (<?= count($rows) ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
    <table class="data-table" id="attendance-table">
      <thead><tr><th>Date</th><th>User Registration &amp; Trainee</th><th>Webinar</th><th>Joined</th><th>Duration</th><th>Marked by</th><th>Actions</th></tr></thead>
      <tbody id="attendance-table-body">
        <?php if (!$rows): ?>
        <tr id="attendance-empty-row"><td colspan="7" style="color:var(--cyber-muted)">No attendance records yet.</td></tr>
        <?php else: foreach ($rows as $a):
          $blocked = adminRecordIsBlocked($a);
          $dateSrc = !empty($a['attendance_date']) ? $a['attendance_date'] : $a['created_at'];
        ?>
        <tr<?= renderAdminRecordRowAttrs($attendanceEntity, (int) $a['id'], $blocked) ?>>
          <td><?= date('d M Y', strtotime((string) $dateSrc)) ?></td>
          <td><?= htmlspecialchars((string) $a['full_name']) ?></td>
          <td><?= htmlspecialchars((string) $a['webinar_title']) ?></td>
          <td><?= !empty($a['joined_at']) ? date('H:i', strtotime((string) $a['joined_at'])) : '—' ?></td>
          <td><?= (int) $a['duration_mins'] ?> min</td>
          <td><?= htmlspecialchars((string) ($a['marked_by_label'] ?? $a['marked_by'] ?? 'Admin')) ?></td>
          <td style="white-space:nowrap">
            <?php renderAdminRecordActions($attendanceEntity, (int) $a['id'], $blocked); ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
