<?php
require_once __DIR__ . '/../includes/admin-init.php';
requireAdminLogin();

$msg = '';
$msgType = 'success';
$viewId = (int) ($_GET['view'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $id = (int) ($_POST['id'] ?? 0);
    $status = sanitize($_POST['status'] ?? '');
    $notes = trim($_POST['admin_notes'] ?? '');
    $validStatuses = ['pending', 'reviewed', 'shortlisted', 'rejected', 'active'];

    if ($id > 0 && in_array($status, $validStatuses, true)) {
        db()->execute(
            'UPDATE freelancer_registrations SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?',
            [$status, $notes ?: null, $id]
        );
        $msg = 'Freelancer application updated.';
        $viewId = $id;
    } else {
        $msg = 'Invalid update request.';
        $msgType = 'error';
    }
}

$where = adminSqlActive();
$params = [];
if ($statusFilter && in_array($statusFilter, ['pending', 'reviewed', 'shortlisted', 'rejected', 'active'], true)) {
    $where .= ' AND status = ?';
    $params[] = $statusFilter;
}

$freelancers = db()->fetchAll(
    "SELECT * FROM freelancer_registrations WHERE {$where} ORDER BY created_at DESC",
    $params
);

$viewFreelancer = null;
if ($viewId > 0) {
    $viewFreelancer = db()->fetchOne('SELECT * FROM freelancer_registrations WHERE id = ?', [$viewId]);
}

$roleLabels = [
    'developer' => 'Software Developer',
    'designer' => 'UI/UX Designer',
    'cybersecurity' => 'Cybersecurity',
    'devops' => 'DevOps / Cloud',
    'qa' => 'QA / Testing',
    'data' => 'Data / AI',
    'mobile' => 'Mobile Developer',
    'other' => 'Other',
];
$availLabels = [
    'full_time' => 'Full-time',
    'part_time' => 'Part-time',
    'project_based' => 'Project-based',
];
$expLabels = [
    'fresher' => 'Fresher',
    '1-2' => '1–2 years',
    '3-5' => '3–5 years',
    '5+' => '5+ years',
];

renderAdminPageStart('Freelancer Applications', 'freelancers', 'fa-user-tie');
?>
    <?php if ($msg): ?><div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <?php if ($viewFreelancer): ?>
    <div class="form-card">
      <div class="form-card-header">Application #<?= (int) $viewFreelancer['id'] ?> — <?= htmlspecialchars($viewFreelancer['full_name']) ?></div>
      <div class="form-card-body"<?= renderAdminRecordRowAttrs('freelancer', (int) $viewFreelancer['id'], adminRecordIsBlocked($viewFreelancer)) ?>>
        <p class="mb-3"><?php renderAdminRecordActions('freelancer', (int) $viewFreelancer['id'], adminRecordIsBlocked($viewFreelancer)); ?></p>
        <div class="detail-grid">
          <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($viewFreelancer['email']) ?></span></div>
          <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($viewFreelancer['phone'] ?: '—') ?></span></div>
          <div class="detail-item"><label>Role</label><span><?= htmlspecialchars($roleLabels[$viewFreelancer['primary_role']] ?? $viewFreelancer['primary_role']) ?></span></div>
          <div class="detail-item"><label>Experience</label><span><?= htmlspecialchars($expLabels[$viewFreelancer['experience_level']] ?? $viewFreelancer['experience_level']) ?></span></div>
          <div class="detail-item"><label>Availability</label><span><?= htmlspecialchars($availLabels[$viewFreelancer['availability']] ?? $viewFreelancer['availability']) ?></span></div>
          <div class="detail-item"><label>Location</label><span><?= htmlspecialchars($viewFreelancer['location'] ?: '—') ?></span></div>
          <div class="detail-item"><label>Applied</label><span><?= date('d M Y, h:i A', strtotime($viewFreelancer['created_at'])) ?></span></div>
        </div>
        <p style="margin-bottom:0.75rem"><strong style="color:var(--cyber-muted);font-size:0.82rem">Skills:</strong><br><?= nl2br(htmlspecialchars($viewFreelancer['skills'])) ?></p>
        <p style="margin-bottom:1rem"><strong style="color:var(--cyber-muted);font-size:0.82rem">About:</strong><br><?= nl2br(htmlspecialchars($viewFreelancer['about'])) ?></p>
        <?php if ($viewFreelancer['portfolio_url'] || $viewFreelancer['github_url'] || $viewFreelancer['linkedin_url']): ?>
        <p style="margin-bottom:1rem;font-size:0.88rem">
          <?php if ($viewFreelancer['portfolio_url']): ?><a href="<?= htmlspecialchars($viewFreelancer['portfolio_url']) ?>" target="_blank" class="btn-sm-link me-3"><i class="fas fa-globe"></i> Portfolio</a><?php endif; ?>
          <?php if ($viewFreelancer['github_url']): ?><a href="<?= htmlspecialchars($viewFreelancer['github_url']) ?>" target="_blank" class="btn-sm-link me-3"><i class="fab fa-github"></i> GitHub</a><?php endif; ?>
          <?php if ($viewFreelancer['linkedin_url']): ?><a href="<?= htmlspecialchars($viewFreelancer['linkedin_url']) ?>" target="_blank" class="btn-sm-link"><i class="fab fa-linkedin"></i> LinkedIn</a><?php endif; ?>
        </p>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
          <input type="hidden" name="id" value="<?= (int) $viewFreelancer['id'] ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['pending','reviewed','shortlisted','active','rejected'] as $s): ?>
                <option value="<?= $s ?>" <?= $viewFreelancer['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-8">
              <label class="form-label">Admin Notes</label>
              <input type="text" name="admin_notes" class="form-control" value="<?= htmlspecialchars($viewFreelancer['admin_notes'] ?? '') ?>" placeholder="Internal notes">
            </div>
          </div>
          <button type="submit" class="btn-submit mt-3"><i class="fas fa-save me-1"></i>Save</button>
          <a href="<?= url('admin/freelancers.php') ?>" style="margin-left:0.75rem;color:var(--cyber-muted);font-size:0.88rem">← Back to list</a>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-card">
      <div class="form-card-header">All Applications (<?= count($freelancers) ?>)</div>
      <div class="form-card-body">
        <div class="filter-bar">
          <a href="<?= url('admin/freelancers.php') ?>" class="<?= $statusFilter === '' ? 'active' : '' ?>">All</a>
          <?php foreach (['pending','reviewed','shortlisted','active','rejected'] as $s): ?>
          <a href="?status=<?= $s ?>" class="<?= $statusFilter === $s ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
          <?php endforeach; ?>
        </div>
        <div style="overflow-x:auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Experience</th>
                <th>Status</th>
                <th>Applied</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($freelancers)): ?>
              <tr><td colspan="6" style="color:var(--cyber-muted);text-align:center;padding:2rem">No freelancer applications yet.</td></tr>
              <?php else: foreach ($freelancers as $f):
                $blocked = adminRecordIsBlocked($f);
              ?>
              <tr<?= renderAdminRecordRowAttrs('freelancer', (int) $f['id'], $blocked) ?>>
                <td>
                  <strong><?= htmlspecialchars($f['full_name']) ?></strong><br>
                  <span style="font-size:0.78rem;color:var(--cyber-muted)"><?= htmlspecialchars($f['email']) ?></span>
                </td>
                <td><?= htmlspecialchars($roleLabels[$f['primary_role']] ?? $f['primary_role']) ?></td>
                <td><?= htmlspecialchars($expLabels[$f['experience_level']] ?? $f['experience_level']) ?></td>
                <td><span class="badge-status badge-<?= htmlspecialchars($f['status']) ?>"><?= htmlspecialchars($f['status']) ?></span></td>
                <td style="white-space:nowrap;color:var(--cyber-muted);font-size:0.82rem"><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                <td>
                  <a href="?view=<?= (int) $f['id'] ?>" class="btn-sm-link">View</a>
                  <?php renderAdminRecordActions('freelancer', (int) $f['id'], $blocked); ?>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

<?php renderAdminPageEnd(); ?>
