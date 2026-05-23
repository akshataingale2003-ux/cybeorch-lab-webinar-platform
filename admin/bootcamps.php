<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['edit'] ?? 0);
$msg = '';
$msgType = 'success';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
        $data = [
            'title'          => sanitize($_POST['title'] ?? ''),
            'slug'           => slugify($_POST['title'] ?? ''),
            'description'    => sanitize($_POST['description'] ?? ''),
            'short_desc'     => sanitize($_POST['short_desc'] ?? ''),
            'instructor'     => sanitize($_POST['instructor'] ?? ''),
            'category'       => sanitize($_POST['category'] ?? ''),
            'start_date'     => $_POST['start_date'] ?? '',
            'end_date'       => $_POST['end_date'] ?? '',
            'duration_weeks' => (int) ($_POST['duration_weeks'] ?? 4),
            'total_seats'    => (int) ($_POST['total_seats'] ?? 30),
            'original_fee'   => (float) ($_POST['original_fee'] ?? 0),
            'discounted_fee' => (float) ($_POST['discounted_fee'] ?? 0),
            'certificate'    => isset($_POST['certificate']) ? 1 : 0,
            'status'         => sanitize($_POST['status'] ?? 'open'),
        ];

        if (strlen($data['title']) < 3 || empty($data['start_date']) || empty($data['end_date'])) {
            $msg = 'Title, start date, and end date are required.';
            $msgType = 'error';
            $action = 'add';
        } else {
            $postId = (int) ($_POST['bootcamp_id'] ?? 0);
            if ($postId > 0) {
                db()->execute(
                    'UPDATE bootcamps SET title=?,slug=?,description=?,short_desc=?,instructor=?,category=?,start_date=?,end_date=?,duration_weeks=?,total_seats=?,original_fee=?,discounted_fee=?,certificate=?,status=? WHERE id=?',
                    array_merge(array_values($data), [$postId])
                );
                $msg = 'Bootcamp updated successfully!';
            } else {
                $slug = $data['slug'];
                if (db()->fetchOne('SELECT id FROM bootcamps WHERE slug = ?', [$slug])) {
                    $data['slug'] = $slug . '-' . time();
                }
                db()->execute(
                    'INSERT INTO bootcamps (title,slug,description,short_desc,instructor,category,start_date,end_date,duration_weeks,total_seats,original_fee,discounted_fee,certificate,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_values($data)
                );
                $msg = 'Bootcamp created successfully!';
            }
            $action = '';
            $editId = 0;
        }
    }

    $editBootcamp = null;
    if ($editId > 0) {
        $editBootcamp = db()->fetchOne('SELECT * FROM bootcamps WHERE id = ?', [$editId]);
        if (!$editBootcamp) {
            $msg = 'Bootcamp not found.';
            $msgType = 'error';
            $editId = 0;
        } else {
            $action = 'add';
        }
    }

    $bootcamps = db()->fetchAll(
        'SELECT b.*, COUNT(be.id) AS enroll_count
         FROM bootcamps b
         LEFT JOIN bootcamp_enrollments be ON be.bootcamp_id = b.id
         WHERE ' . adminSqlActive('b') . '
         GROUP BY b.id
         ORDER BY b.start_date DESC'
    );
} catch (Throwable $e) {
    $bootcamps = [];
    $editBootcamp = null;
    $msg = 'Could not load bootcamps: ' . $e->getMessage();
    $msgType = 'error';
}

$pageTitle = $action === 'add' ? ($editId ? 'Edit Bootcamp' : 'Add Bootcamp') : 'Bootcamps';
renderAdminPageStart($pageTitle, 'bootcamps', 'fa-graduation-cap');
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if ($action !== 'add'): ?>
<p class="mb-3"><a href="<?= adminUrl('admin/bootcamps.php?action=add') ?>" class="btn-primary-cyber"><i class="fas fa-plus"></i>Add Bootcamp</a></p>
<?php endif; ?>

<?php if ($action === 'add'): ?>
<div class="form-card">
  <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>"></i><?= $editId ? 'Edit Bootcamp' : 'Create Bootcamp' ?></div>
  <div class="form-card-body">
    <form method="POST" action="<?= adminUrl('admin/bootcamps.php' . ($editId ? '?edit=' . $editId : '?action=add')) ?>">
      <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
      <?php if ($editId): ?><input type="hidden" name="bootcamp_id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($editBootcamp['title'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Category</label>
          <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($editBootcamp['category'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Short Description</label>
          <input type="text" name="short_desc" class="form-control" value="<?= htmlspecialchars($editBootcamp['short_desc'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Full Description</label>
          <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($editBootcamp['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label">Instructor</label>
          <input type="text" name="instructor" class="form-control" value="<?= htmlspecialchars($editBootcamp['instructor'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Start Date *</label>
          <input type="date" name="start_date" class="form-control" required value="<?= htmlspecialchars($editBootcamp['start_date'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">End Date *</label>
          <input type="date" name="end_date" class="form-control" required value="<?= htmlspecialchars($editBootcamp['end_date'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Duration (weeks)</label>
          <input type="number" name="duration_weeks" class="form-control" min="1" value="<?= (int) ($editBootcamp['duration_weeks'] ?? 4) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Total Seats</label>
          <input type="number" name="total_seats" class="form-control" min="1" value="<?= (int) ($editBootcamp['total_seats'] ?? 30) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Original Fee (₹)</label>
          <input type="number" name="original_fee" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editBootcamp['original_fee'] ?? 0)) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Discounted Fee (₹)</label>
          <input type="number" name="discounted_fee" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars((string) ($editBootcamp['discounted_fee'] ?? 0)) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['open', 'closed', 'ongoing', 'completed'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editBootcamp['status'] ?? 'open') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-8 d-flex align-items-end">
          <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer">
            <input type="checkbox" name="certificate" value="1" <?= ($editBootcamp['certificate'] ?? 1) ? 'checked' : '' ?>>
            Certificate of completion included
          </label>
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i><?= $editId ? 'Update' : 'Create' ?></button>
          <a href="<?= adminUrl('admin/bootcamps.php') ?>" class="btn-cancel">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header">All bootcamps (<?= count($bootcamps) ?>)</div>
  <div class="section-card-body">
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr><th>Title</th><th>Instructor</th><th>Dates</th><th>Seats</th><th>Fee</th><th>Enrolled</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (!$bootcamps): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted)">No bootcamps. <a href="<?= adminUrl('admin/bootcamps.php?action=add') ?>" style="color:var(--cyber-accent)">Add one</a></td></tr>
          <?php else: foreach ($bootcamps as $b):
            $blocked = adminRecordIsBlocked($b);
          ?>
          <tr<?= renderAdminRecordRowAttrs('bootcamp', (int) $b['id'], $blocked) ?>>
            <td>
              <strong><?= htmlspecialchars($b['title']) ?></strong><br>
              <a href="<?= url('bootcamp.php?slug=' . urlencode($b['slug'])) ?>" target="_blank" rel="noopener" class="btn-sm-link" style="font-size:0.75rem">View on site →</a>
            </td>
            <td><?= htmlspecialchars($b['instructor'] ?: '—') ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($b['start_date'])) ?> – <?= date('d M Y', strtotime($b['end_date'])) ?></td>
            <td><?= (int) $b['enrolled_seats'] ?> / <?= (int) $b['total_seats'] ?></td>
            <td>₹<?= number_format((float) $b['discounted_fee']) ?></td>
            <td><?= (int) $b['enroll_count'] ?></td>
            <td><span class="badge-status badge-<?= $b['status'] === 'open' ? 'paid' : 'pending' ?>"><?= htmlspecialchars($b['status']) ?></span></td>
            <td style="white-space:nowrap">
              <a href="<?= adminUrl('admin/bootcamps.php?edit=' . (int) $b['id']) ?>" class="btn-sm-cyber btn-edit" title="Edit"><i class="fas fa-pen"></i></a>
              <?php renderAdminRecordActions('bootcamp', (int) $b['id'], $blocked); ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
