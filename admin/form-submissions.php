<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/form-submissions.php';
require_once __DIR__ . '/../includes/admin-users.php';
requireAdminLogin();

/** @return array<string, string> */
function adminFormSubmissionsQuery(array $overrides = []): array
{
    global $categoryFilter, $formKeyFilter, $readFilter, $recordStatus, $searchQ, $page, $perPage, $sortOrder;

    $q = [
        'record_status' => $recordStatus,
        'page'          => (string) $page,
        'per_page'      => (string) $perPage,
        'sort'          => $sortOrder,
    ];
    if ($categoryFilter !== '' && $categoryFilter !== 'all') {
        $q['category'] = $categoryFilter;
    }
    if ($formKeyFilter !== '') {
        $q['form_key'] = $formKeyFilter;
    }
    if ($readFilter !== '') {
        $q['read_filter'] = $readFilter;
    }
    if ($searchQ !== '') {
        $q['q'] = $searchQ;
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = (string) $v;
        }
    }
    return $q;
}

function adminFormSubmissionsHiddenFields(): void
{
    foreach (adminFormSubmissionsQuery() as $k => $v) {
        if ($k === 'page') {
            continue;
        }
        echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
    }
}

function adminFormSubmissionsUrl(array $overrides = [], ?int $viewId = null): string
{
    $q = adminFormSubmissionsQuery($overrides);
    if ($viewId !== null && $viewId > 0) {
        $q['view'] = (string) $viewId;
        unset($q['page']);
    }
    $qs = http_build_query($q);
    return adminUrl('admin/form-submissions.php') . ($qs !== '' ? '?' . $qs : '');
}

$viewId = (int) ($_REQUEST['view'] ?? 0);
$categoryFilter = adminFormSubmissionParam((string) ($_REQUEST['category'] ?? 'all'));
if ($categoryFilter === '') {
    $categoryFilter = 'all';
}
$formKeyFilter = adminFormSubmissionParam((string) ($_REQUEST['form_key'] ?? ''));
$readFilter = adminParseFormSubmissionReadFilter();
$recordStatus = adminParseFormSubmissionRecordStatus();
$sortOrder = adminFormSubmissionParam((string) ($_REQUEST['sort'] ?? 'newest'));
if (!in_array($sortOrder, ['newest', 'oldest', 'name', 'name-desc'], true)) {
    $sortOrder = 'newest';
}
$searchQ = trim((string) ($_REQUEST['q'] ?? ''));
$page = max(1, (int) ($_REQUEST['page'] ?? 1));
$perPage = max(10, min(100, (int) ($_REQUEST['per_page'] ?? 25)));

$flashMsg = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCSRF($_POST['csrf_token'] ?? '')) {
    $postAction = sanitize($_POST['action'] ?? '');
    $postId = (int) ($_POST['id'] ?? 0);

    if ($postId > 0 && $postAction === 'mark_read') {
        setFormSubmissionReadState($postId, true);
        $flashMsg = 'Marked as read.';
    } elseif ($postId > 0 && $postAction === 'mark_unread') {
        setFormSubmissionReadState($postId, false);
        $flashMsg = 'Marked as unread.';
    } elseif ($postAction === 'mark_read' || $postAction === 'mark_unread') {
        $flashMsg = 'Invalid submission.';
        $flashType = 'error';
    }

    if ($flashMsg !== '' && empty($_POST['stay_on_view'])) {
        header('Location: ' . adminFormSubmissionsUrl(['page' => (string) $page]));
        exit;
    }
    if ($flashMsg !== '' && !empty($_POST['stay_on_view'])) {
        header('Location: ' . adminFormSubmissionsUrl([], $postId));
        exit;
    }
}

$viewRow = $viewId > 0 ? getFormSubmissionById($viewId) : null;
if ($viewRow && !(int) ($viewRow['is_read'] ?? 0)) {
    markFormSubmissionRead($viewId);
    $viewRow['is_read'] = 1;
}

$categories = getAdminFormSubmissionCategories();
$categoryCounts = getFormSubmissionCountsByCategory();

$typeFilter = resolveFormSubmissionAdminFilter(
    $formKeyFilter !== '' ? null : $categoryFilter,
    $formKeyFilter !== '' ? $formKeyFilter : null
);

$unreadTotal = (int) dbTry(fn () => getUnreadFormSubmissionCount($typeFilter), 0);
$readTotal = (int) dbTry(fn () => getReadFormSubmissionCount($typeFilter), 0);

$paginated = getFormSubmissionsPaginated(
    $typeFilter,
    in_array($readFilter, ['read', 'unread'], true) ? $readFilter : null,
    $searchQ,
    $recordStatus,
    $page,
    $perPage,
    $sortOrder
);

$activeCategoryLabel = $categories[$categoryFilter]['label'] ?? 'All form submissions';
if ($readFilter === 'read') {
    $activeCategoryLabel .= ' — Read only';
} elseif ($readFilter === 'unread') {
    $activeCategoryLabel .= ' — Unread only';
}
if ($formKeyFilter !== '') {
    foreach (getFormSubmissionRegistryDefinitions() as $def) {
        if ($def['form_key'] === $formKeyFilter) {
            $activeCategoryLabel = $def['form_label'];
            break;
        }
    }
}
$logRows = $paginated['rows'];
$page = $paginated['page'];
$totalPages = $paginated['total_pages'];
$totalMatching = $paginated['total'];

$paginationQuery = adminFormSubmissionsQuery();

renderAdminPageStart('Form Submissions', 'form-submissions', 'fa-clipboard-list');
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($flashMsg) ?></div>
<?php endif; ?>

<?php if ($viewRow):
    $inTrash = adminUserRecordStatus($viewRow) === 'deleted';
    $isRead = (int) ($viewRow['is_read'] ?? 0) === 1;
    $detailMsg = formSubmissionDisplayMessage($viewRow);
?>
<div class="section-card">
  <div class="section-card-header" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;justify-content:space-between">
    <span>Submission #<?= (int) $viewRow['id'] ?> — <?= htmlspecialchars($viewRow['form_label']) ?></span>
    <span>
      <?php if ($isRead): ?><span class="badge-status badge-read">Read</span><?php else: ?><span class="badge-status badge-unread">Unread</span><?php endif; ?>
      <?php adminRenderUserStatusBadge($viewRow); ?>
    </span>
  </div>
  <div class="section-card-body">
    <div class="detail-grid">
      <div class="detail-item"><label>Form type</label><span><?= htmlspecialchars($viewRow['form_label']) ?> <code><?= htmlspecialchars($viewRow['form_key']) ?></code></span></div>
      <div class="detail-item"><label>Full name</label><span><?= htmlspecialchars($viewRow['full_name'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Email</label><span><?php if ($viewRow['email']): ?><a href="mailto:<?= htmlspecialchars($viewRow['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($viewRow['email']) ?></a><?php else: ?>—<?php endif; ?></span></div>
      <div class="detail-item"><label>Phone</label><span><?php if ($viewRow['phone']): ?><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $viewRow['phone']) ?? '') ?>" class="btn-sm-link"><?= htmlspecialchars($viewRow['phone']) ?></a><?php else: ?>—<?php endif; ?></span></div>
      <div class="detail-item"><label>Source page</label><span><?= htmlspecialchars($viewRow['source_page'] ?: '—') ?></span></div>
      <div class="detail-item"><label>Date &amp; time</label><span><?= date('d M Y, h:i A', strtotime($viewRow['created_at'])) ?></span></div>
      <div class="detail-item"><label>Stored record</label><span><?= htmlspecialchars(($viewRow['storage_table'] ?: '—') . ($viewRow['storage_record_id'] ? ' #' . $viewRow['storage_record_id'] : '')) ?></span></div>
    </div>

    <?php if ($detailMsg !== ''): ?>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin:1rem 0 0.35rem">Message / details</p>
    <div class="msg-body mb-3"><?= nl2br(htmlspecialchars($detailMsg)) ?></div>
    <?php endif; ?>

    <?php if (!empty($viewRow['payload_json'])): ?>
    <p style="font-size:0.82rem;color:var(--cyber-muted);margin-bottom:0.35rem">All submitted fields</p>
    <pre class="msg-body" style="font-size:0.78rem;max-height:360px;overflow:auto"><?= htmlspecialchars(json_encode(json_decode($viewRow['payload_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: $viewRow['payload_json']) ?></pre>
    <?php endif; ?>

    <div class="submission-card-actions mt-3">
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" name="id" value="<?= (int) $viewRow['id'] ?>">
        <input type="hidden" name="stay_on_view" value="1">
        <?php adminFormSubmissionsHiddenFields(); ?>
        <?php if ($isRead): ?>
        <button type="submit" name="action" value="mark_unread" class="btn-sm-cyber btn-mark-unread"><i class="fas fa-envelope"></i><span>Mark unread</span></button>
        <?php else: ?>
        <button type="submit" name="action" value="mark_read" class="btn-sm-cyber btn-mark-read"><i class="fas fa-envelope-open"></i><span>Mark read</span></button>
        <?php endif; ?>
      </form>
      <?php renderAdminRecordActions('form_submission', (int) $viewRow['id'], false, $inTrash); ?>
      <a href="<?= htmlspecialchars(adminFormSubmissionsUrl()) ?>" class="btn-cancel">← Back to list</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="section-card-header"><?= htmlspecialchars($activeCategoryLabel) ?> (<?= number_format($totalMatching) ?>)</div>
  <div class="section-card-body">
    <div class="admin-toolbar" style="margin-bottom:0.75rem">
      <div class="filter-bar" style="margin-bottom:0">
        <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['record_status' => 'active', 'page' => '1'])) ?>" class="<?= $recordStatus === 'active' ? 'active' : '' ?>">Active</a>
        <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['record_status' => 'deleted', 'page' => '1'])) ?>" class="<?= $recordStatus === 'deleted' ? 'active' : '' ?>">Deleted</a>
      </div>
      <form method="get" action="<?= adminUrl('admin/form-submissions.php') ?>" class="admin-search-form">
        <input type="hidden" name="record_status" value="<?= htmlspecialchars($recordStatus) ?>">
        <?php if ($categoryFilter !== 'all' && $formKeyFilter === ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>"><?php endif; ?>
        <?php if ($formKeyFilter !== ''): ?><input type="hidden" name="form_key" value="<?= htmlspecialchars($formKeyFilter) ?>"><?php endif; ?>
        <?php if ($readFilter !== ''): ?><input type="hidden" name="read_filter" value="<?= htmlspecialchars($readFilter) ?>"><?php endif; ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sortOrder) ?>">
        <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
        <input type="search" name="q" class="form-control" placeholder="Search name, email, phone, message…" value="<?= htmlspecialchars($searchQ) ?>" aria-label="Search submissions">
        <button type="submit" class="btn-search" title="Search"><i class="fas fa-search"></i></button>
        <?php if ($searchQ !== ''): ?>
        <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['q' => null, 'page' => '1'])) ?>" class="btn-cancel" style="padding:0.5rem 0.75rem;font-size:0.8rem">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="filter-bar mb-2">
      <?php
      $primaryCategories = ['all', 'messages-enquiries', 'contact', 'webinar-registration', 'bootcamp-registration', 'payment', 'support-desk'];
      foreach ($primaryCategories as $slug):
          if (!isset($categories[$slug])) {
              continue;
          }
          $cfg = $categories[$slug];
          $cnt = $categoryCounts[$slug] ?? 0;
          $isActive = $formKeyFilter === '' && $readFilter === '' && $categoryFilter === $slug;
      ?>
      <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['category' => $slug === 'all' ? null : $slug, 'form_key' => null, 'read_filter' => null, 'page' => '1'])) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= htmlspecialchars($cfg['label']) ?><?php if ($cnt > 0): ?><span class="filter-count">(<?= $cnt ?>)</span><?php endif; ?></a>
      <?php endforeach; ?>
      <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['read_filter' => 'unread', 'form_key' => null, 'category' => $categoryFilter !== 'all' ? $categoryFilter : null, 'page' => '1'])) ?>" class="<?= $readFilter === 'unread' ? 'active' : '' ?>">Unread<?php if ($unreadTotal > 0): ?><span class="filter-count">(<?= $unreadTotal ?>)</span><?php endif; ?></a>
      <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['read_filter' => 'read', 'form_key' => null, 'category' => $categoryFilter !== 'all' ? $categoryFilter : null, 'page' => '1'])) ?>" class="<?= $readFilter === 'read' ? 'active' : '' ?>">Read<?php if ($readTotal > 0): ?><span class="filter-count">(<?= $readTotal ?>)</span><?php endif; ?></a>
    </div>

    <div class="filter-bar mb-2" style="opacity:0.92">
      <span style="font-size:0.72rem;color:var(--cyber-muted);align-self:center;margin-right:0.35rem">More:</span>
      <?php
      $moreCategories = ['product-demo', 'collaborate-project', 'website-registration', 'platform-signup', 'freelancer-registration'];
      foreach ($moreCategories as $slug):
          if (!isset($categories[$slug])) {
              continue;
          }
          $cnt = $categoryCounts[$slug] ?? 0;
          $isActive = $formKeyFilter === '' && $readFilter === '' && $categoryFilter === $slug;
      ?>
      <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['category' => $slug, 'form_key' => null, 'read_filter' => $readFilter !== '' ? $readFilter : null, 'page' => '1'])) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= htmlspecialchars($categories[$slug]['label']) ?><?php if ($cnt > 0): ?><span class="filter-count">(<?= $cnt ?>)</span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>

    <form method="get" action="<?= adminUrl('admin/form-submissions.php') ?>" class="mb-3" style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;font-size:0.82rem">
      <?php foreach (adminFormSubmissionsQuery() as $k => $v): ?>
        <?php if (!in_array($k, ['sort', 'per_page', 'page'], true)): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <label style="color:var(--cyber-muted)">Sort</label>
      <select name="sort" class="form-select" style="width:auto;padding:0.35rem 0.65rem!important;font-size:0.82rem!important" onchange="this.form.submit()">
        <option value="newest" <?= $sortOrder === 'newest' ? 'selected' : '' ?>>Newest first</option>
        <option value="oldest" <?= $sortOrder === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
        <option value="name" <?= $sortOrder === 'name' ? 'selected' : '' ?>>Name A–Z</option>
        <option value="name-desc" <?= $sortOrder === 'name-desc' ? 'selected' : '' ?>>Name Z–A</option>
      </select>
    </form>

    <?php
    $renderRowActions = static function (array $row): void {
        $id = (int) $row['id'];
        $read = (int) ($row['is_read'] ?? 0) === 1;
        $inTrash = adminUserRecordStatus($row) === 'deleted';
        ?>
      <a href="<?= htmlspecialchars(adminFormSubmissionsUrl([], $id)) ?>" class="btn-sm-cyber btn-edit"><i class="fas fa-eye"></i><span>View</span></a>
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php adminFormSubmissionsHiddenFields(); ?>
        <input type="hidden" name="page" value="<?= (int) $page ?>">
        <?php if ($read): ?>
        <button type="submit" name="action" value="mark_unread" class="btn-sm-cyber btn-mark-unread" title="Mark unread"><i class="fas fa-envelope"></i><span>Unread</span></button>
        <?php else: ?>
        <button type="submit" name="action" value="mark_read" class="btn-sm-cyber btn-mark-read" title="Mark read"><i class="fas fa-envelope-open"></i><span>Read</span></button>
        <?php endif; ?>
      </form>
      <?php renderAdminRecordActions('form_submission', $id, false, $inTrash);
    };
    ?>

    <div class="table-responsive submission-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Date &amp; time</th>
            <th>Form type</th>
            <th>Full name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Message / details</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$logRows): ?>
          <tr><td colspan="8" style="color:var(--cyber-muted);padding:1.5rem"><?php if ($readFilter === 'read'): ?>No read submissions yet. Open a submission to mark it read, or use the <strong>Mark read</strong> button on unread items.<?php elseif ($readFilter === 'unread'): ?>No unread submissions — you are all caught up.<?php else: ?>No submissions in this category yet. Try <a href="<?= htmlspecialchars(adminFormSubmissionsUrl(['category' => null, 'form_key' => null, 'read_filter' => null])) ?>" class="btn-sm-link">All form submissions</a>.<?php endif; ?></td></tr>
          <?php else: foreach ($logRows as $row):
            $inTrash = adminUserRecordStatus($row) === 'deleted';
            $read = (int) ($row['is_read'] ?? 0) === 1;
            $msg = formSubmissionDisplayMessage($row);
          ?>
          <tr<?= renderAdminRecordRowAttrs('form_submission', (int) $row['id'], false) ?> class="<?= !$read && !$inTrash ? 'admin-row-unread' : '' ?>">
            <td style="white-space:nowrap"><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></td>
            <td><?= htmlspecialchars($row['form_label']) ?></td>
            <td><?= htmlspecialchars($row['full_name'] ?: '—') ?></td>
            <td><?php if ($row['email']): ?><a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="btn-sm-link"><?= htmlspecialchars($row['email']) ?></a><?php else: ?>—<?php endif; ?></td>
            <td><?= htmlspecialchars($row['phone'] ?: '—') ?></td>
            <td style="max-width:240px;color:var(--cyber-muted);font-size:0.82rem" title="<?= htmlspecialchars($msg) ?>"><?= htmlspecialchars(formSubmissionExcerpt($msg, 100)) ?></td>
            <td>
              <?php if ($inTrash): ?>
                <?php adminRenderUserStatusBadge($row); ?>
              <?php elseif ($read): ?>
                <span class="badge-status badge-read">Read</span>
              <?php else: ?>
                <span class="badge-status badge-unread">Unread</span>
              <?php endif; ?>
            </td>
            <td><div class="admin-action-btns d-inline-flex flex-wrap gap-1"><?php $renderRowActions($row); ?></div></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="submission-cards">
      <?php if (!$logRows): ?>
      <p style="color:var(--cyber-muted)">No submissions match this filter.</p>
      <?php else: foreach ($logRows as $row):
        $read = (int) ($row['is_read'] ?? 0) === 1;
        $msg = formSubmissionDisplayMessage($row);
      ?>
      <article class="submission-card <?= !$read ? 'unread' : '' ?>">
        <div class="submission-card-head">
          <strong><?= htmlspecialchars($row['full_name'] ?: 'No name') ?></strong>
          <?php if ($read): ?><span class="badge-status badge-read">Read</span><?php else: ?><span class="badge-status badge-unread">Unread</span><?php endif; ?>
        </div>
        <div class="submission-card-meta">
          <div><i class="fas fa-tag"></i> <?= htmlspecialchars($row['form_label']) ?></div>
          <div><i class="fas fa-clock"></i> <?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></div>
          <?php if ($row['email']): ?><div><i class="fas fa-envelope"></i> <?= htmlspecialchars($row['email']) ?></div><?php endif; ?>
          <?php if ($row['phone']): ?><div><i class="fas fa-phone"></i> <?= htmlspecialchars($row['phone']) ?></div><?php endif; ?>
        </div>
        <p class="submission-card-msg"><?= htmlspecialchars(formSubmissionExcerpt($msg, 160)) ?></p>
        <div class="submission-card-actions"><?php $renderRowActions($row); ?></div>
      </article>
      <?php endforeach; endif; ?>
    </div>

    <?php renderAdminPagination($page, $totalPages, $totalMatching, 'admin/form-submissions.php', $paginationQuery); ?>

    <form method="get" action="<?= adminUrl('admin/form-submissions.php') ?>" class="mt-2" style="font-size:0.82rem;color:var(--cyber-muted)">
      <?php foreach (adminFormSubmissionsQuery() as $k => $v): ?>
        <?php if ($k !== 'per_page' && $k !== 'page'): ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      Per page:
      <select name="per_page" class="form-select d-inline-block" style="width:auto;display:inline-block;padding:0.25rem 0.5rem!important;font-size:0.82rem!important" onchange="this.form.submit()">
        <?php foreach ([10, 25, 50, 100] as $n): ?>
        <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php renderAdminPageEnd(); ?>
