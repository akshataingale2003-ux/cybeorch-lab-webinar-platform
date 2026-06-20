<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-init.php';
require_once __DIR__ . '/../includes/catalog-content.php';

ensureCatalogContentSchemas();

/**
 * @return array{ok:bool, error?:string}
 */
function assertTrue(bool $condition, string $error): array
{
    return $condition ? ['ok' => true] : ['ok' => false, 'error' => $error];
}

try {
    db()->beginTransaction();

    $token = substr(str_replace('.', '', (string) microtime(true)), -8);

    // ---- Live Projects ----
    $liveBefore = count(catalogGetLiveProjects(true));
    $liveSlug = 'crud-live-' . $token;
    $liveId = (int) db()->insert(
        'INSERT INTO live_projects (title, slug, short_desc, description, category, icon_class, card_theme, image_path, features_json, stack, duration, team_size, status, project_type, show_on_homepage, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            'CRUD Live ' . $token,
            $liveSlug,
            'Short',
            'Description',
            'Category',
            'fa-code-branch',
            'theme-default',
            '',
            '',
            'PHP',
            '2 weeks',
            '2',
            'Open for Collaboration',
            CATALOG_PROJECT_TYPE_LIVE,
            0,
            999,
        ]
    );
    $liveAfterInsert = count(catalogGetLiveProjects(true));
    $check = assertTrue($liveAfterInsert === $liveBefore + 1, 'Live add count mismatch.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    db()->execute('UPDATE live_projects SET title = ?, updated_at = NOW() WHERE id = ?', ['CRUD Live Updated ' . $token, $liveId]);
    $liveAfterUpdate = count(catalogGetLiveProjects(true));
    $check = assertTrue($liveAfterUpdate === $liveAfterInsert, 'Live update changed count (duplicate risk).');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    $liveRow = db()->fetchOne('SELECT title FROM live_projects WHERE id = ?', [$liveId]);
    $check = assertTrue((string) ($liveRow['title'] ?? '') === 'CRUD Live Updated ' . $token, 'Live update did not apply to same row.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    db()->execute('UPDATE live_projects SET deleted_at = NOW() WHERE id = ?', [$liveId]);
    $liveAfterDelete = count(catalogGetLiveProjects(true));
    $check = assertTrue($liveAfterDelete === $liveBefore, 'Live delete did not reduce admin list count.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    $livePublic = catalogGetLiveProjects(false);
    foreach ($livePublic as $row) {
        if ((int) ($row['id'] ?? 0) === $liveId) {
            throw new RuntimeException('Deleted live project still visible on website list.');
        }
    }

    // ---- Assignments / Hands-on ----
    $assignBefore = count(catalogGetAssignments(true));
    $assignSlug = 'crud-assignment-' . $token;
    $assignId = (int) db()->insert(
        'INSERT INTO assignments (title, slug, short_desc, description, category, type, skills, duration, mode, status, apply_route, project_type, show_on_homepage, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            'CRUD Assignment ' . $token,
            $assignSlug,
            'Short',
            'Description',
            'Category',
            'Project',
            'PHP, SQL',
            '2 weeks',
            'Remote',
            'Open',
            '',
            CATALOG_PROJECT_TYPE_HANDS_ON,
            0,
            999,
        ]
    );
    $assignAfterInsert = count(catalogGetAssignments(true));
    $check = assertTrue($assignAfterInsert === $assignBefore + 1, 'Assignment add count mismatch.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    db()->execute('UPDATE assignments SET title = ?, updated_at = NOW() WHERE id = ?', ['CRUD Assignment Updated ' . $token, $assignId]);
    $assignAfterUpdate = count(catalogGetAssignments(true));
    $check = assertTrue($assignAfterUpdate === $assignAfterInsert, 'Assignment update changed count (duplicate risk).');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    db()->execute('UPDATE assignments SET deleted_at = NOW() WHERE id = ?', [$assignId]);
    $assignAfterDelete = count(catalogGetAssignments(true));
    $check = assertTrue($assignAfterDelete === $assignBefore, 'Assignment delete did not reduce admin list count.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    $assignPublic = catalogGetAssignments(false);
    foreach ($assignPublic as $row) {
        if ((int) ($row['id'] ?? 0) === $assignId) {
            throw new RuntimeException('Deleted assignment still visible on website list.');
        }
    }

    // ---- Freelance Projects ----
    $freeBefore = count(catalogGetFreelanceProjects(true));
    $freeSlug = 'crud-freelance-' . $token;
    $freeId = (int) db()->insert(
        'INSERT INTO freelance_projects (title, slug, short_desc, description, category, skills, duration, mode, budget_label, client_name, image_path, status, apply_route, project_type, show_on_homepage, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            'CRUD Freelance ' . $token,
            $freeSlug,
            'Short',
            'Description',
            'Category',
            'PHP, SQL',
            '2 weeks',
            'Remote',
            '1000',
            'Client',
            '',
            'Active',
            'apply-freelance.php',
            CATALOG_PROJECT_TYPE_FREELANCER,
            0,
            999,
        ]
    );
    $freeAfterInsert = count(catalogGetFreelanceProjects(true));
    $check = assertTrue($freeAfterInsert === $freeBefore + 1, 'Freelance add count mismatch.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    db()->execute('UPDATE freelance_projects SET title = ?, updated_at = NOW() WHERE id = ?', ['CRUD Freelance Updated ' . $token, $freeId]);
    $freeAfterUpdate = count(catalogGetFreelanceProjects(true));
    $check = assertTrue($freeAfterUpdate === $freeAfterInsert, 'Freelance update changed count (duplicate risk).');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    db()->execute('UPDATE freelance_projects SET deleted_at = NOW() WHERE id = ?', [$freeId]);
    $freeAfterDelete = count(catalogGetFreelanceProjects(true));
    $check = assertTrue($freeAfterDelete === $freeBefore, 'Freelance delete did not reduce admin list count.');
    if (!$check['ok']) {
        throw new RuntimeException($check['error']);
    }
    $freePublic = catalogGetFreelanceProjects(false);
    foreach ($freePublic as $row) {
        if ((int) ($row['id'] ?? 0) === $freeId) {
            throw new RuntimeException('Deleted freelance project still visible on website list.');
        }
    }

    echo "PASS\n";
    echo "Live: {$liveBefore} -> {$liveAfterInsert} -> {$liveAfterDelete}\n";
    echo "Assignments: {$assignBefore} -> {$assignAfterInsert} -> {$assignAfterDelete}\n";
    echo "Freelance: {$freeBefore} -> {$freeAfterInsert} -> {$freeAfterDelete}\n";

    db()->rollBack();
    exit(0);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
