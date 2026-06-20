<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/company-content.php';
requireAdminLogin();

ensureCompanyContentSchema();

$section = (string) ($_GET['section'] ?? 'trust');
$allowedSections = ['trust', 'services', 'portfolio', 'case-studies', 'awards', 'badges'];
if (!in_array($section, $allowedSections, true)) {
    $section = 'trust';
}

if ((string) ($_GET['section'] ?? '') === 'team') {
    header('Location: ' . adminUrl('admin/team-profiles.php'));
    exit;
}

$editId = (int) ($_GET['edit'] ?? 0);
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $action = (string) ($_POST['action'] ?? '');
    $postSection = (string) ($_POST['section'] ?? $section);
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $map = [
            'services'       => 'corporate_services',
            'portfolio'      => 'portfolio_items',
            'case-studies'   => 'case_studies',
            'team'           => 'company_team_members',
            'awards'         => 'company_awards',
            'badges'         => 'company_trust_badges',
        ];
        if (isset($map[$postSection])) {
            if ($postSection === 'case-studies') {
                db()->execute('DELETE FROM case_study_screenshots WHERE case_study_id = ?', [$id]);
            }
            db()->execute('DELETE FROM ' . $map[$postSection] . ' WHERE id = ?', [$id]);
            setFlash('success', 'Record deleted.');
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=' . rawurlencode($postSection)));
        exit;
    }

    if ($action === 'delete_screenshot') {
        $shotId = (int) ($_POST['screenshot_id'] ?? 0);
        if ($shotId > 0) {
            db()->execute('DELETE FROM case_study_screenshots WHERE id = ?', [$shotId]);
            setFlash('success', 'Screenshot removed.');
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=case-studies&edit=' . $id));
        exit;
    }

    if ($action === 'save_trust') {
        $result = saveCompanyProfileSettings($_POST);
        if (!empty($_FILES['profile_pdf']['name'])) {
            $pdfUpload = companyStorePdfUpload($_FILES['profile_pdf']);
            if ($pdfUpload['ok']) {
                db()->execute(
                    'UPDATE company_profile_settings SET profile_pdf_path = ?, updated_at = NOW() WHERE id = 1',
                    [$pdfUpload['path']]
                );
                setFlash('success', 'Company trust details and profile PDF saved.');
            } else {
                setFlash($result['success'] ? 'warning' : 'error', $pdfUpload['error']);
            }
        } else {
            setFlash($result['success'] ? 'success' : 'error', $result['message']);
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=trust'));
        exit;
    }

    if ($action === 'save_service') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Title is required.');
        } else {
            $data = [
                $title,
                slugify($title),
                trim((string) ($_POST['short_desc'] ?? '')),
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['icon_class'] ?? 'fa-code')),
                (int) ($_POST['sort_order'] ?? 0),
                !empty($_POST['is_active']) ? 1 : 0,
            ];
            $imagePath = trim((string) ($_POST['image_path'] ?? ''));
            if (!empty($_FILES['image']['name'])) {
                $up = companyStoreUpload($_FILES['image'], 'service');
                if ($up['ok']) {
                    $imagePath = $up['path'];
                }
            }
            if ($id > 0) {
                db()->execute(
                    'UPDATE corporate_services SET title=?, slug=?, short_desc=?, description=?, icon_class=?, sort_order=?, is_active=?, image_path=COALESCE(NULLIF(?,\'\'), image_path), updated_at=NOW() WHERE id=?',
                    array_merge($data, [$imagePath, $id])
                );
            } else {
                db()->execute(
                    'INSERT INTO corporate_services (title, slug, short_desc, description, icon_class, sort_order, is_active, image_path) VALUES (?,?,?,?,?,?,?,?)',
                    array_merge($data, [$imagePath])
                );
            }
            setFlash('success', 'Corporate service saved.');
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=services'));
        exit;
    }

    if ($action === 'save_portfolio') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Title is required.');
        } else {
            $data = [
                $title,
                slugify($title),
                trim((string) ($_POST['short_desc'] ?? '')),
                trim((string) ($_POST['description'] ?? '')),
                trim((string) ($_POST['tech_stack'] ?? '')),
                trim((string) ($_POST['icon_class'] ?? '')),
                (int) ($_POST['sort_order'] ?? 0),
                !empty($_POST['is_active']) ? 1 : 0,
            ];
            $imagePath = trim((string) ($_POST['image_path'] ?? ''));
            if (!empty($_FILES['image']['name'])) {
                $up = companyStoreUpload($_FILES['image'], 'portfolio');
                if ($up['ok']) {
                    $imagePath = $up['path'];
                }
            }
            if ($id > 0) {
                db()->execute(
                    'UPDATE portfolio_items SET title=?, slug=?, short_desc=?, description=?, tech_stack=?, icon_class=?, sort_order=?, is_active=?, image_path=COALESCE(NULLIF(?,\'\'), image_path), updated_at=NOW() WHERE id=?',
                    array_merge($data, [$imagePath, $id])
                );
            } else {
                db()->execute(
                    'INSERT INTO portfolio_items (title, slug, short_desc, description, tech_stack, icon_class, sort_order, is_active, image_path) VALUES (?,?,?,?,?,?,?,?,?)',
                    array_merge($data, [$imagePath])
                );
            }
            setFlash('success', 'Portfolio item saved.');
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=portfolio'));
        exit;
    }

    if ($action === 'save_case_study') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Title is required.');
        } else {
            $data = [
                $title,
                slugify($title),
                trim((string) ($_POST['client_name'] ?? '')),
                trim((string) ($_POST['summary'] ?? '')),
                trim((string) ($_POST['projects_delivered'] ?? '')),
                trim((string) ($_POST['client_outcomes'] ?? '')),
                trim((string) ($_POST['before_after_metrics'] ?? '')),
                trim((string) ($_POST['performance_improvements'] ?? '')),
                trim((string) ($_POST['success_story'] ?? '')),
                trim((string) ($_POST['results_achievements'] ?? '')),
                (int) ($_POST['sort_order'] ?? 0),
                !empty($_POST['is_active']) ? 1 : 0,
            ];
            $cover = trim((string) ($_POST['cover_image_path'] ?? ''));
            if (!empty($_FILES['cover_image']['name'])) {
                $up = companyStoreUpload($_FILES['cover_image'], 'case-cover');
                if ($up['ok']) {
                    $cover = $up['path'];
                }
            }
            if ($id > 0) {
                db()->execute(
                    'UPDATE case_studies SET title=?, slug=?, client_name=?, summary=?, projects_delivered=?, client_outcomes=?, before_after_metrics=?, performance_improvements=?, success_story=?, results_achievements=?, sort_order=?, is_active=?, cover_image_path=COALESCE(NULLIF(?,\'\'), cover_image_path), updated_at=NOW() WHERE id=?',
                    array_merge($data, [$cover, $id])
                );
                $caseId = $id;
            } else {
                db()->execute(
                    'INSERT INTO case_studies (title, slug, client_name, summary, projects_delivered, client_outcomes, before_after_metrics, performance_improvements, success_story, results_achievements, sort_order, is_active, cover_image_path) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array_merge($data, [$cover])
                );
                $caseId = (int) db()->lastInsertId();
            }
            if ($caseId > 0 && !empty($_FILES['screenshot']['name'])) {
                $up = companyStoreUpload($_FILES['screenshot'], 'case-shot');
                if ($up['ok']) {
                    db()->execute(
                        'INSERT INTO case_study_screenshots (case_study_id, image_path, caption, sort_order) VALUES (?,?,?,?)',
                        [$caseId, $up['path'], trim((string) ($_POST['screenshot_caption'] ?? '')), (int) ($_POST['screenshot_sort'] ?? 0)]
                    );
                }
            }
            setFlash('success', 'Case study saved.');
            header('Location: ' . adminUrl('admin/company-content.php?section=case-studies&edit=' . $caseId));
            exit;
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=case-studies'));
        exit;
    }

    $simpleSave = static function (string $table, array $fields, string $redirectSection) use ($id): void {
        if ($id > 0) {
            $sets = implode(', ', array_map(static fn ($f) => $f . ' = ?', $fields));
            $vals = [];
            foreach ($fields as $f) {
                $vals[] = $_POST[$f] ?? '';
            }
            $vals[] = $id;
            db()->execute("UPDATE {$table} SET {$sets}, updated_at=NOW() WHERE id = ?", $vals);
        } else {
            $cols = implode(', ', $fields);
            $ph = implode(', ', array_fill(0, count($fields), '?'));
            $vals = [];
            foreach ($fields as $f) {
                $vals[] = $_POST[$f] ?? '';
            }
            db()->execute("INSERT INTO {$table} ({$cols}) VALUES ({$ph})", $vals);
        }
        setFlash('success', 'Record saved.');
        header('Location: ' . adminUrl('admin/company-content.php?section=' . rawurlencode($redirectSection)));
        exit;
    };

    if ($action === 'save_award') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Title is required.');
        } else {
            $photo = '';
            if (!empty($_FILES['image']['name'])) {
                $up = companyStoreUpload($_FILES['image'], 'award');
                if ($up['ok']) {
                    $photo = $up['path'];
                }
            }
            if ($id > 0) {
                db()->execute(
                    'UPDATE company_awards SET title=?, award_year=?, description=?, sort_order=?, is_active=?, image_path=COALESCE(NULLIF(?,\'\'), image_path), updated_at=NOW() WHERE id=?',
                    [$title, trim((string) ($_POST['award_year'] ?? '')), trim((string) ($_POST['description'] ?? '')), (int) ($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, $photo, $id]
                );
            } else {
                db()->execute(
                    'INSERT INTO company_awards (title, award_year, description, sort_order, is_active, image_path) VALUES (?,?,?,?,?,?)',
                    [$title, trim((string) ($_POST['award_year'] ?? '')), trim((string) ($_POST['description'] ?? '')), (int) ($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, $photo]
                );
            }
            setFlash('success', 'Award saved.');
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=awards'));
        exit;
    }

    if ($action === 'save_badge') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            setFlash('error', 'Title is required.');
        } else {
            $photo = '';
            if (!empty($_FILES['image']['name'])) {
                $up = companyStoreUpload($_FILES['image'], 'badge');
                if ($up['ok']) {
                    $photo = $up['path'];
                }
            }
            if ($id > 0) {
                db()->execute(
                    'UPDATE company_trust_badges SET title=?, sort_order=?, is_active=?, image_path=COALESCE(NULLIF(?,\'\'), image_path), updated_at=NOW() WHERE id=?',
                    [$title, (int) ($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, $photo, $id]
                );
            } else {
                db()->execute(
                    'INSERT INTO company_trust_badges (title, sort_order, is_active, image_path) VALUES (?,?,?,?)',
                    [$title, (int) ($_POST['sort_order'] ?? 0), !empty($_POST['is_active']) ? 1 : 0, $photo]
                );
            }
            setFlash('success', 'Trust badge saved.');
        }
        header('Location: ' . adminUrl('admin/company-content.php?section=badges'));
        exit;
    }
}

$trust = getCompanyProfileSettings();
$services = db()->fetchAll('SELECT * FROM corporate_services ORDER BY sort_order ASC, id ASC');
$portfolio = db()->fetchAll('SELECT * FROM portfolio_items ORDER BY sort_order ASC, id ASC');
$caseStudies = db()->fetchAll('SELECT * FROM case_studies ORDER BY sort_order ASC, id ASC');
$awards = db()->fetchAll('SELECT * FROM company_awards ORDER BY sort_order ASC, id ASC');
$badges = db()->fetchAll('SELECT * FROM company_trust_badges ORDER BY sort_order ASC, id ASC');

$editRow = null;
$screenshots = [];
if ($editId > 0) {
    $editMap = [
        'services' => ['corporate_services', $services],
        'portfolio' => ['portfolio_items', $portfolio],
        'case-studies' => ['case_studies', $caseStudies],
        'awards' => ['company_awards', $awards],
        'badges' => ['company_trust_badges', $badges],
    ];
    if (isset($editMap[$section])) {
        $editRow = db()->fetchOne('SELECT * FROM ' . $editMap[$section][0] . ' WHERE id = ?', [$editId]);
        if ($section === 'case-studies' && $editRow) {
            $screenshots = db()->fetchAll('SELECT * FROM case_study_screenshots WHERE case_study_id = ? ORDER BY sort_order ASC, id ASC', [$editId]);
        }
    }
}

$tab = static function (string $key, string $label) use ($section): void {
    $active = $section === $key ? ' active' : '';
    echo '<a href="' . htmlspecialchars(adminUrl('admin/company-content.php?section=' . rawurlencode($key))) . '" class="' . trim('filter-bar-link' . $active) . '" style="padding:.4rem .85rem;border-radius:6px;font-size:.82rem;border:1px solid var(--cyber-border);color:var(--cyber-muted);text-decoration:none;' . ($active ? 'border-color:var(--cyber-accent);color:var(--cyber-accent);' : '') . '">' . htmlspecialchars($label) . '</a>';
};

renderAdminPageStart('Company Content', 'company-content', 'fa-building');
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div class="filter-bar" style="margin-bottom:1.25rem">
<?php
$tab('trust', 'Trust & Legal');
$tab('services', 'Corporate Services');
$tab('portfolio', 'Portfolio');
$tab('case-studies', 'Case Studies');
echo '<a href="' . htmlspecialchars(adminUrl('admin/team-profiles.php')) . '" class="filter-bar-link" style="padding:.4rem .85rem;border-radius:6px;font-size:.82rem;border:1px solid var(--cyber-border);color:var(--cyber-muted);text-decoration:none">Team Profiles</a>';
$tab('awards', 'Awards');
$tab('badges', 'Trust Badges');
?>
</div>

<?php if ($section === 'trust'): ?>
<div class="form-card">
  <div class="form-card-header">GST / CIN</div>
  <div class="form-card-body">
    <form method="POST" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
      <input type="hidden" name="action" value="save_trust">
      <div class="col-md-6">
        <label class="form-label">GST Number</label>
        <input type="text" name="gst_number" class="form-control" value="<?= htmlspecialchars((string) ($trust['gst_number'] ?? '')) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">CIN Number</label>
        <input type="text" name="cin_number" class="form-control" value="<?= htmlspecialchars((string) ($trust['cin_number'] ?? '')) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Company Profile PDF</label>
        <input type="file" name="profile_pdf" class="form-control" accept="application/pdf,.pdf">
        <?php if (!empty($trust['profile_pdf_path'])): ?>
        <p class="form-text" style="color:var(--cyber-muted);margin-top:.5rem">
          Current file:
          <a href="<?= htmlspecialchars(companyProfileDownloadUrl(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
            <?= htmlspecialchars(basename((string) $trust['profile_pdf_path'])) ?>
          </a>
        </p>
        <?php else: ?>
        <p class="form-text" style="color:var(--cyber-muted);margin-top:.5rem">Upload a PDF to enable the Company Profile download link in the navigation menu.</p>
        <?php endif; ?>
      </div>
      <div class="col-12">
        <button type="submit" class="btn-submit"><i class="fas fa-save me-1"></i>Save</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$renderListDelete = static function (string $sec, array $rows, array $cols): void {
    echo '<div class="table-responsive"><table class="data-table"><thead><tr>';
    foreach ($cols as $c) {
        echo '<th>' . htmlspecialchars($c) . '</th>';
    }
    echo '<th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach (array_keys($cols) as $key) {
            $val = $row[$key] ?? '';
            if ($key === 'is_active') {
                $val = $val ? 'Yes' : 'No';
            }
            echo '<td>' . htmlspecialchars((string) $val) . '</td>';
        }
        echo '<td class="admin-action-btns d-flex">';
        echo '<a href="' . htmlspecialchars(adminUrl('admin/company-content.php?section=' . rawurlencode($sec) . '&edit=' . (int) $row['id'])) . '" class="btn-sm-cyber btn-edit"><i class="fas fa-edit"></i></a>';
        echo '<form method="POST" onsubmit="return confirm(\'Delete this record?\');" style="display:inline">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
        echo '<input type="hidden" name="action" value="delete">';
        echo '<input type="hidden" name="section" value="' . htmlspecialchars($sec) . '">';
        echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '">';
        echo '<button type="submit" class="btn-sm-cyber btn-delete"><i class="fas fa-trash"></i></button></form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
};
?>

<?php if ($section === 'services'): ?>
<div class="form-card"><div class="form-card-header"><?= $editRow ? 'Edit' : 'Add' ?> Corporate Service</div><div class="form-card-body">
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>"><input type="hidden" name="action" value="save_service"><input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
<div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?= htmlspecialchars((string) ($editRow['title'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label">Icon class</label><input name="icon_class" class="form-control" value="<?= htmlspecialchars((string) ($editRow['icon_class'] ?? 'fa-code')) ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string) ($editRow['description'] ?? '')) ?></textarea></div>
<div class="col-md-4"><label class="form-label">Sort order</label><input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>"></div>
<div class="col-md-4"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
<div class="col-md-4"><label style="display:flex;gap:.5rem;align-items:center;margin-top:1.75rem"><input type="checkbox" name="is_active" value="1" <?= !isset($editRow['is_active']) || $editRow['is_active'] ? 'checked' : '' ?>> Active</label></div>
<div class="col-12"><button class="btn-submit" type="submit">Save Service</button></div>
</form></div></div>
<?php $renderListDelete('services', $services, ['title' => 'Title', 'icon_class' => 'Icon', 'sort_order' => 'Order', 'is_active' => 'Active']); ?>
<?php endif; ?>

<?php if ($section === 'portfolio'): ?>
<div class="form-card"><div class="form-card-header"><?= $editRow ? 'Edit' : 'Add' ?> Portfolio Item</div><div class="form-card-body">
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>"><input type="hidden" name="action" value="save_portfolio"><input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
<div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?= htmlspecialchars((string) ($editRow['title'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label">Icon class</label><input name="icon_class" class="form-control" placeholder="fa-cubes" value="<?= htmlspecialchars((string) ($editRow['icon_class'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label">Tech stack</label><input name="tech_stack" class="form-control" value="<?= htmlspecialchars((string) ($editRow['tech_stack'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars((string) ($editRow['description'] ?? '')) ?></textarea></div>
<div class="col-md-4"><label class="form-label">Sort order</label><input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>"></div>
<div class="col-md-4"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
<div class="col-md-4"><label style="display:flex;gap:.5rem;align-items:center;margin-top:1.75rem"><input type="checkbox" name="is_active" value="1" <?= !isset($editRow['is_active']) || $editRow['is_active'] ? 'checked' : '' ?>> Active</label></div>
<div class="col-12"><button class="btn-submit" type="submit">Save Portfolio Item</button></div>
</form></div></div>
<?php $renderListDelete('portfolio', $portfolio, ['title' => 'Title', 'icon_class' => 'Icon', 'tech_stack' => 'Stack', 'sort_order' => 'Order', 'is_active' => 'Active']); ?>
<?php endif; ?>

<?php if ($section === 'case-studies'): ?>
<div class="form-card"><div class="form-card-header"><?= $editRow ? 'Edit' : 'Add' ?> Case Study</div><div class="form-card-body">
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>"><input type="hidden" name="action" value="save_case_study"><input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
<div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?= htmlspecialchars((string) ($editRow['title'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label">Client name</label><input name="client_name" class="form-control" value="<?= htmlspecialchars((string) ($editRow['client_name'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Summary</label><textarea name="summary" class="form-control" rows="2"><?= htmlspecialchars((string) ($editRow['summary'] ?? '')) ?></textarea></div>
<div class="col-md-6"><label class="form-label">Projects Delivered (one per line)</label><textarea name="projects_delivered" class="form-control" rows="4"><?= htmlspecialchars((string) ($editRow['projects_delivered'] ?? '')) ?></textarea></div>
<div class="col-md-6"><label class="form-label">Client Outcomes (one per line)</label><textarea name="client_outcomes" class="form-control" rows="4"><?= htmlspecialchars((string) ($editRow['client_outcomes'] ?? '')) ?></textarea></div>
<div class="col-md-6"><label class="form-label">Before/After Metrics (one per line)</label><textarea name="before_after_metrics" class="form-control" rows="4"><?= htmlspecialchars((string) ($editRow['before_after_metrics'] ?? '')) ?></textarea></div>
<div class="col-md-6"><label class="form-label">Performance Improvements (one per line)</label><textarea name="performance_improvements" class="form-control" rows="4"><?= htmlspecialchars((string) ($editRow['performance_improvements'] ?? '')) ?></textarea></div>
<div class="col-12"><label class="form-label">Success Story</label><textarea name="success_story" class="form-control" rows="3"><?= htmlspecialchars((string) ($editRow['success_story'] ?? '')) ?></textarea></div>
<div class="col-12"><label class="form-label">Results &amp; Achievements (one per line)</label><textarea name="results_achievements" class="form-control" rows="3"><?= htmlspecialchars((string) ($editRow['results_achievements'] ?? '')) ?></textarea></div>
<div class="col-md-4"><label class="form-label">Cover image</label><input type="file" name="cover_image" class="form-control" accept="image/*"></div>
<div class="col-md-4"><label class="form-label">Add screenshot</label><input type="file" name="screenshot" class="form-control" accept="image/*"></div>
<div class="col-md-4"><label class="form-label">Screenshot caption</label><input name="screenshot_caption" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Sort order</label><input type="number" name="sort_order" class="form-control" value="<?= (int) ($editRow['sort_order'] ?? 0) ?>"></div>
<div class="col-md-4"><label style="display:flex;gap:.5rem;align-items:center;margin-top:1.75rem"><input type="checkbox" name="is_active" value="1" <?= !isset($editRow['is_active']) || $editRow['is_active'] ? 'checked' : '' ?>> Active</label></div>
<div class="col-12"><button class="btn-submit" type="submit">Save Case Study</button></div>
</form>
<?php if ($screenshots !== []): ?>
<h4 style="margin-top:1.5rem;font-family:'Rajdhani',sans-serif">Screenshots</h4>
<div class="row g-2"><?php foreach ($screenshots as $shot): ?>
<div class="col-md-3"><img src="<?= htmlspecialchars(companyPublicImageUrl((string) $shot['image_path'])) ?>" style="width:100%;border-radius:8px;border:1px solid var(--cyber-border)">
<form method="POST" class="mt-1" onsubmit="return confirm('Remove screenshot?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>"><input type="hidden" name="action" value="delete_screenshot"><input type="hidden" name="id" value="<?= (int) $editId ?>"><input type="hidden" name="screenshot_id" value="<?= (int) $shot['id'] ?>"><button class="btn-sm-cyber btn-delete" type="submit">Remove</button></form></div>
<?php endforeach; ?></div>
<?php endif; ?>
</div></div>
<?php $renderListDelete('case-studies', $caseStudies, ['title' => 'Title', 'client_name' => 'Client', 'sort_order' => 'Order', 'is_active' => 'Active']); ?>
<?php endif; ?>

<?php if ($section === 'awards'): ?>
<div class="form-card"><div class="form-card-header"><?= $editRow ? 'Edit' : 'Add' ?> Award</div><div class="form-card-body">
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>"><input type="hidden" name="action" value="save_award"><input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
<div class="col-md-6"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?= htmlspecialchars((string) ($editRow['title'] ?? '')) ?>"></div>
<div class="col-md-6"><label class="form-label">Year</label><input name="award_year" class="form-control" value="<?= htmlspecialchars((string) ($editRow['award_year'] ?? '')) ?>"></div>
<div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= htmlspecialchars((string) ($editRow['description'] ?? '')) ?></textarea></div>
<div class="col-md-4"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
<div class="col-12"><button class="btn-submit" type="submit">Save</button></div>
</form></div></div>
<?php $renderListDelete('awards', $awards, ['title' => 'Title', 'award_year' => 'Year', 'is_active' => 'Active']); ?>
<?php endif; ?>

<?php if ($section === 'badges'): ?>
<div class="form-card"><div class="form-card-header"><?= $editRow ? 'Edit' : 'Add' ?> Trust Badge</div><div class="form-card-body">
<form method="POST" enctype="multipart/form-data" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>"><input type="hidden" name="action" value="save_badge"><input type="hidden" name="id" value="<?= (int) ($editRow['id'] ?? 0) ?>">
<div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" required value="<?= htmlspecialchars((string) ($editRow['title'] ?? '')) ?>"></div>
<div class="col-md-4"><label class="form-label">Badge image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
<div class="col-12"><button class="btn-submit" type="submit">Save</button></div>
</form></div></div>
<?php $renderListDelete('badges', $badges, ['title' => 'Title', 'sort_order' => 'Order', 'is_active' => 'Active']); ?>
<?php endif; ?>

<?php renderAdminPageEnd(); ?>
