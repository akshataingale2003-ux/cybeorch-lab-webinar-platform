<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$action = $_GET['action'] ?? '';
$editId = (int) ($_GET['edit'] ?? 0);
$msg    = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title'            => sanitize($_POST['title']),
        'slug'             => slugify($_POST['title']),
        'description'      => sanitize($_POST['description']),
        'short_desc'       => sanitize($_POST['short_desc']),
        'instructor'       => sanitize($_POST['instructor']),
        'category'         => sanitize($_POST['category']),
        'tags'             => sanitize($_POST['tags'] ?? ''),
        'scheduled_at'     => $_POST['scheduled_at'],
        'duration_mins'    => (int) $_POST['duration_mins'],
        'max_seats'        => (int) $_POST['max_seats'],
        'fee'              => (float) $_POST['fee'],
        'is_free'          => isset($_POST['is_free']) ? 1 : 0,
        'meeting_link'     => sanitize($_POST['meeting_link'] ?? ''),
        'meeting_platform' => sanitize($_POST['meeting_platform']),
        'status'           => sanitize($_POST['status']),
    ];

    if (empty($data['title']) || empty($data['scheduled_at'])) {
        $msg = 'Title and schedule date are required.';
        $msgType = 'error';
    } else {
        $postId = (int) ($_POST['webinar_id'] ?? 0);
        if ($postId > 0) {
            db()->execute(
                'UPDATE webinars SET title=?,slug=?,description=?,short_desc=?,instructor=?,category=?,tags=?,scheduled_at=?,duration_mins=?,max_seats=?,fee=?,is_free=?,meeting_link=?,meeting_platform=?,status=?,updated_at=NOW() WHERE id=?',
                array_merge(array_values($data), [$postId])
            );
            $msg = 'Webinar updated successfully!';
        } else {
            $slug   = $data['slug'];
            $exists = db()->fetchOne('SELECT id FROM webinars WHERE slug = ?', [$slug]);
            if ($exists) {
                $data['slug'] = $slug . '-' . time();
            }
            db()->execute(
                'INSERT INTO webinars (title,slug,description,short_desc,instructor,category,tags,scheduled_at,duration_mins,max_seats,fee,is_free,meeting_link,meeting_platform,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array_values($data)
            );
            $msg = 'Webinar created successfully!';
        }
        $action = '';
        $editId = 0;
    }
}

$editWebinar = null;
if ($editId > 0) {
    $editWebinar = db()->fetchOne('SELECT * FROM webinars WHERE id = ?', [$editId]);
    $action = 'add';
}

$webinars = db()->fetchAll(
    'SELECT w.*, COUNT(wr.id) as reg_count FROM webinars w
     LEFT JOIN webinar_registrations wr ON wr.webinar_id = w.id
         WHERE ' . adminSqlActive('w') . '
         GROUP BY w.id ORDER BY w.scheduled_at DESC'
);

$pageTitle = $action === 'add' ? ($editId ? 'Edit Webinar' : 'Add Webinar') : 'Webinars';
renderAdminPageStart($pageTitle, 'webinars', 'fa-video');
?>

<?php if ($action !== 'add'): ?>
<p class="mb-3"><a href="?action=add" class="btn-primary-cyber"><i class="fas fa-plus"></i>Add Webinar</a></p>
<?php endif; ?>
    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType === 'success' ? 'check' : 'exclamation' ?>-circle"></i><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if ($action === 'add'): ?>
    <!-- ADD / EDIT FORM -->
    <div class="form-card">
      <div class="form-card-header"><i class="fas fa-<?= $editId ? 'pen' : 'plus-circle' ?>" style="color:var(--cyber-accent)"></i><?= $editId ? 'Edit Webinar' : 'Create New Webinar' ?></div>
      <div class="form-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <?php if ($editId): ?>
          <input type="hidden" name="webinar_id" value="<?= $editId ?>">
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Webinar Title *</label>
              <input type="text" name="title" class="form-control" required placeholder="e.g. Ethical Hacking Masterclass" value="<?= htmlspecialchars($editWebinar['title'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <input type="text" name="category" class="form-control" placeholder="e.g. Cybersecurity" value="<?= htmlspecialchars($editWebinar['category'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Short Description</label>
              <input type="text" name="short_desc" class="form-control" placeholder="One-line summary shown in listing" value="<?= htmlspecialchars($editWebinar['short_desc'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Full Description</label>
              <textarea name="description" class="form-control" placeholder="Detailed webinar description, agenda, takeaways..."><?= htmlspecialchars($editWebinar['description'] ?? '') ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Instructor Name</label>
              <input type="text" name="instructor" class="form-control" placeholder="Speaker full name" value="<?= htmlspecialchars($editWebinar['instructor'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Scheduled Date & Time *</label>
              <input type="datetime-local" name="scheduled_at" class="form-control" required value="<?= $editWebinar ? date('Y-m-d\TH:i', strtotime($editWebinar['scheduled_at'])) : '' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Duration (minutes)</label>
              <input type="number" name="duration_mins" class="form-control" min="15" max="480" value="<?= htmlspecialchars($editWebinar['duration_mins'] ?? 60) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Max Seats</label>
              <input type="number" name="max_seats" class="form-control" min="1" value="<?= htmlspecialchars($editWebinar['max_seats'] ?? 100) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fee (₹)</label>
              <input type="number" name="fee" class="form-control" min="0" step="0.01" value="<?= htmlspecialchars($editWebinar['fee'] ?? 0) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Meeting Platform</label>
              <select name="meeting_platform" class="form-select">
                <?php foreach (['zoom','google_meet','teams','other'] as $p): ?>
                <option value="<?=$p?>" <?= ($editWebinar['meeting_platform'] ?? 'zoom') === $p ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$p)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['upcoming','live','completed','cancelled'] as $s): ?>
                <option value="<?=$s?>" <?= ($editWebinar['status'] ?? 'upcoming') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Meeting Link</label>
              <input type="url" name="meeting_link" class="form-control" placeholder="https://zoom.us/j/..." value="<?= htmlspecialchars($editWebinar['meeting_link'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Tags (comma separated)</label>
              <input type="text" name="tags" class="form-control" placeholder="ethical hacking, nmap, penetration testing" value="<?= htmlspecialchars($editWebinar['tags'] ?? '') ?>">
            </div>
            <div class="col-12">
              <div style="display:flex;align-items:center;gap:0.5rem">
                <input type="checkbox" name="is_free" id="is_free" style="accent-color:var(--cyber-accent)" <?= ($editWebinar['is_free'] ?? 0) ? 'checked' : '' ?>>
                <label for="is_free" style="font-size:0.88rem;color:var(--cyber-text);cursor:pointer">This is a FREE webinar (no payment required)</label>
              </div>
            </div>
            <div class="col-12 d-flex gap-2 pt-1">
              <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i><?= $editId ? 'Update Webinar' : 'Create Webinar' ?></button>
              <a href="<?= url('admin/webinars.php') ?>" class="btn-cancel">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- WEBINARS TABLE -->
    <div class="form-card">
      <div class="form-card-header"><i class="fas fa-list" style="color:var(--cyber-accent)"></i>All Webinars (<?= count($webinars) ?>)</div>
      <table class="data-table">
        <thead><tr>
          <th>#</th><th>Title</th><th>Instructor</th><th>Scheduled</th><th>Seats</th><th>Fee</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($webinars as $i => $w):
          $blocked = adminRecordIsBlocked($w);
        ?>
        <tr<?= renderAdminRecordRowAttrs('webinar', (int) $w['id'], $blocked) ?>>
          <td style="color:var(--cyber-muted);font-size:0.78rem"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:500;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($w['title']) ?></div>
            <div style="font-size:0.72rem;color:var(--cyber-muted)"><?= htmlspecialchars($w['category']) ?></div>
          </td>
          <td style="font-size:0.82rem"><?= htmlspecialchars($w['instructor'] ?? '—') ?></td>
          <td style="font-size:0.8rem;white-space:nowrap">
            <?= date('d M Y', strtotime($w['scheduled_at'])) ?><br>
            <span style="color:var(--cyber-muted)"><?= date('h:i A', strtotime($w['scheduled_at'])) ?></span>
          </td>
          <td>
            <span style="color:var(--cyber-accent)"><?= $w['reg_count'] ?></span>
            <span style="color:var(--cyber-muted);font-size:0.78rem">/ <?= $w['max_seats'] ?></span>
          </td>
          <td style="font-family:'Rajdhani',sans-serif;font-size:0.95rem;font-weight:600">
            <?= $w['is_free'] ? '<span style="color:var(--cyber-green)">FREE</span>' : '₹'.number_format($w['fee']) ?>
          </td>
          <td><span class="badge-status badge-<?= $w['status'] ?>"><?= strtoupper($w['status']) ?></span></td>
          <td style="white-space:nowrap">
            <a href="?edit=<?= $w['id'] ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-pen"></i>Edit</a>
            <?php renderAdminRecordActions('webinar', (int) $w['id'], $blocked); ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($webinars)): ?>
        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--cyber-muted)">No webinars found. <a href="?action=add" style="color:var(--cyber-accent)">Add your first one →</a></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

<script>
document.querySelector('[name="fee"]')?.addEventListener('input', function() {
  const cb = document.getElementById('is_free');
  if (cb) cb.checked = parseFloat(this.value) === 0;
});
</script>
<?php renderAdminPageEnd(); ?>
