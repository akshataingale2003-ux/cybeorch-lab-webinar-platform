<?php
declare(strict_types=1);

/**
 * Live projects, hands-on projects, and freelance project listings (DB-backed).
 */

const CATALOG_PROJECT_TYPE_LIVE = 'live_project';
const CATALOG_PROJECT_TYPE_HANDS_ON = 'hands_on_project';
const CATALOG_PROJECT_TYPE_FREELANCER = 'freelancer_project';

/** @return array<string, string> */
function catalogProjectTypeLabels(): array
{
    return [
        CATALOG_PROJECT_TYPE_LIVE       => 'Live Project',
        CATALOG_PROJECT_TYPE_HANDS_ON   => 'Hands-On Project',
        CATALOG_PROJECT_TYPE_FREELANCER => 'Freelancer Project',
    ];
}

function catalogParseShowOnHomepage(mixed $value): int
{
    if ($value === null || $value === '' || $value === false) {
        return 0;
    }
    if (is_string($value)) {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    return !empty($value) ? 1 : 0;
}

function catalogSqlProjectType(string $type, string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $safe = str_replace("'", "''", $type);

    return $p . "project_type = '" . $safe . "'";
}

function catalogSqlHomepageFeatured(string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';

    return '(' . $p . 'show_on_homepage = 1)';
}

function ensureCatalogContentSchemas(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute(
        'CREATE TABLE IF NOT EXISTS live_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            short_desc VARCHAR(500) DEFAULT NULL,
            description TEXT,
            category VARCHAR(100) DEFAULT NULL,
            stack VARCHAR(255) DEFAULT NULL,
            duration VARCHAR(64) DEFAULT NULL,
            team_size VARCHAR(64) DEFAULT NULL,
            status VARCHAR(64) NOT NULL DEFAULT \'Open for Collaboration\',
            project_type VARCHAR(32) NOT NULL DEFAULT \'live_project\',
            show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL DEFAULT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_live_projects_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    db()->execute(
        'CREATE TABLE IF NOT EXISTS assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            short_desc VARCHAR(500) DEFAULT NULL,
            description TEXT,
            category VARCHAR(100) DEFAULT NULL,
            type VARCHAR(64) NOT NULL DEFAULT \'Project\',
            skills VARCHAR(500) DEFAULT NULL,
            duration VARCHAR(64) DEFAULT NULL,
            mode VARCHAR(64) DEFAULT NULL,
            status VARCHAR(64) NOT NULL DEFAULT \'Open\',
            apply_route VARCHAR(255) DEFAULT NULL,
            project_type VARCHAR(32) NOT NULL DEFAULT \'hands_on_project\',
            show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL DEFAULT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_assignments_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    db()->execute(
        'CREATE TABLE IF NOT EXISTS freelance_projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            short_desc VARCHAR(500) DEFAULT NULL,
            description TEXT,
            category VARCHAR(100) DEFAULT NULL,
            skills VARCHAR(500) DEFAULT NULL,
            duration VARCHAR(64) DEFAULT NULL,
            mode VARCHAR(64) DEFAULT NULL,
            budget_label VARCHAR(120) DEFAULT NULL,
            client_name VARCHAR(120) DEFAULT NULL,
            image_path VARCHAR(255) NULL DEFAULT NULL,
            status VARCHAR(64) NOT NULL DEFAULT \'Open\',
            apply_route VARCHAR(255) DEFAULT \'register-freelancer.php\',
            project_type VARCHAR(32) NOT NULL DEFAULT \'freelancer_project\',
            show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL DEFAULT NULL,
            is_blocked TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_freelance_projects_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    catalogMigrateLiveProjectColumns();
    catalogMigrateFreelanceProjectColumns();
    catalogMigrateProjectDisplayColumns();
    catalogApplyLiveProjectsCatalogRevision();
    catalogSeedDefaultsIfEmpty();
}

/** @var int Bump when default live-project catalog must be re-applied (wipes & reseeds). */
const CATALOG_LIVE_PROJECTS_REVISION = 2;

function catalogMigrateLiveProjectColumns(): void
{
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    $columns = [
        'icon_class'    => "VARCHAR(80) NULL DEFAULT 'fa-code-branch' AFTER category",
        'card_theme'    => "VARCHAR(32) NULL DEFAULT 'theme-default' AFTER icon_class",
        'image_path'    => 'VARCHAR(255) NULL DEFAULT NULL AFTER card_theme',
        'features_json' => 'TEXT NULL AFTER image_path',
    ];

    foreach ($columns as $col => $definition) {
        if (!catalogTableHasColumn('live_projects', $col)) {
            try {
                db()->execute("ALTER TABLE live_projects ADD COLUMN {$col} {$definition}");
            } catch (Throwable $e) {
                // ignore if table missing
            }
        }
    }
}

function catalogMigrateFreelanceProjectColumns(): void
{
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    if (!catalogTableHasColumn('freelance_projects', 'image_path')) {
        try {
            db()->execute('ALTER TABLE freelance_projects ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL AFTER client_name');
        } catch (Throwable $e) {
        }
    }
}

function catalogMigrateProjectDisplayColumns(): void
{
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    $tableDefaults = [
        'live_projects'       => CATALOG_PROJECT_TYPE_LIVE,
        'assignments'           => CATALOG_PROJECT_TYPE_HANDS_ON,
        'freelance_projects'    => CATALOG_PROJECT_TYPE_FREELANCER,
    ];

    foreach ($tableDefaults as $table => $defaultType) {
        if (!catalogTableHasColumn($table, 'project_type')) {
            try {
                db()->execute(
                    "ALTER TABLE {$table} ADD COLUMN project_type VARCHAR(32) NOT NULL DEFAULT '{$defaultType}' AFTER status"
                );
            } catch (Throwable $e) {
            }
        }
        if (!catalogTableHasColumn($table, 'show_on_homepage')) {
            try {
                db()->execute(
                    "ALTER TABLE {$table} ADD COLUMN show_on_homepage TINYINT(1) NOT NULL DEFAULT 0 AFTER project_type"
                );
            } catch (Throwable $e) {
            }
        }
        if (!catalogTableHasColumn($table, 'sort_order')) {
            try {
                db()->execute(
                    "ALTER TABLE {$table} ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER show_on_homepage"
                );
            } catch (Throwable $e) {
            }
        }
        try {
            db()->execute(
                "UPDATE {$table} SET project_type = ? WHERE project_type IS NULL OR project_type = ''",
                [$defaultType]
            );
            db()->execute("UPDATE {$table} SET show_on_homepage = 0 WHERE show_on_homepage IS NULL");
        } catch (Throwable $e) {
        }
    }
}

function catalogTableHasColumn(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $row = db()->fetchOne(
            'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $cache[$key] = ((int) ($row['c'] ?? 0)) > 0;
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function catalogEnsureCatalogRevisionsTable(): void
{
    db()->execute(
        'CREATE TABLE IF NOT EXISTS catalog_revisions (
            revision_key VARCHAR(64) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function catalogRevisionApplied(string $key): bool
{
    catalogEnsureCatalogRevisionsTable();
    $row = db()->fetchOne('SELECT revision_key FROM catalog_revisions WHERE revision_key = ?', [$key]);
    return $row !== null;
}

function catalogMarkRevisionApplied(string $key): void
{
    catalogEnsureCatalogRevisionsTable();
    db()->execute(
        'INSERT INTO catalog_revisions (revision_key) VALUES (?) ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP',
        [$key]
    );
}

/**
 * Wipe legacy live projects and seed the built-in catalog once per revision key.
 * Runs only the first time a revision is applied — admin-added rows are never removed.
 */
function catalogApplyLiveProjectsCatalogRevision(): void
{
    $revKey = 'live_projects_v' . CATALOG_LIVE_PROJECTS_REVISION;
    if (catalogRevisionApplied($revKey)) {
        return;
    }

    try {
        db()->execute('DELETE FROM live_projects');
        $order = 0;
        foreach (catalogDefaultLiveProjects() as $row) {
            catalogInsertLiveProject($row, $order++);
        }
        catalogMarkRevisionApplied($revKey);
    } catch (Throwable $e) {
        // DB offline
    }
}

function catalogSqlPublic(string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return '(' . $p . 'deleted_at IS NULL) AND (' . $p . 'is_blocked = 0)';
}

function catalogSqlFreelancePublicActive(string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "LOWER({$p}status) IN ('active', 'open', 'closing soon')";
}

function catalogSqlAssignmentPublicActive(string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "LOWER({$p}status) IN ('open', 'closing soon')";
}

function catalogSqlLiveProjectPublicActive(string $alias = ''): string
{
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    return "LOWER({$p}status) NOT IN ('inactive', 'cancelled', 'completed', 'closed', 'filled', 'paused')";
}

function catalogSeedDefaultsIfEmpty(): void
{
    $liveCount = (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM live_projects')['c'] ?? 0);
    if ($liveCount === 0) {
        $order = 0;
        foreach (catalogDefaultLiveProjects() as $row) {
            catalogInsertLiveProject($row, $order++);
        }
    }

    $assignCount = (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM assignments')['c'] ?? 0);
    if ($assignCount === 0) {
        $order = 0;
        foreach (catalogDefaultAssignments() as $row) {
            catalogInsertAssignment($row, $order++);
        }
    }

    $freelanceCount = (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM freelance_projects')['c'] ?? 0);
    if ($freelanceCount === 0) {
        $order = 0;
        foreach (catalogDefaultFreelanceProjects() as $row) {
            catalogInsertFreelanceProject($row, $order++);
        }
    }

    // Do not auto-reseed or auto-sync rows during normal runtime.
    // Admin CRUD should remain the single source of truth.
}

/** @return list<string> */
function catalogLiveProjectCanonicalSlugs(): array
{
    return array_values(array_filter(array_map(
        static fn (array $row): string => trim((string) ($row['slug'] ?? '')),
        catalogDefaultLiveProjects()
    )));
}

/** Upsert canonical live projects by slug; retire duplicate title rows (no duplicate inserts). */
function catalogSyncLiveProjectCatalog(): void
{
    try {
        $canonical = catalogDefaultLiveProjects();

        foreach ($canonical as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            if ($slug === '' || $title === '') {
                continue;
            }
            db()->execute(
                "UPDATE live_projects SET deleted_at = NOW()
                 WHERE slug != ? AND title = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')",
                [$slug, $title]
            );
        }

        foreach ($canonical as $order => $row) {
            catalogUpsertLiveProject($row, $order);
        }
    } catch (Throwable $e) {
        // DB offline
    }
}

/** Insert or update a single live project row keyed by slug. */
function catalogUpsertLiveProject(array $row, int $sortOrder = 0): void
{
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        return;
    }

    $featuresJson = '';
    if (!empty($row['features']) && is_array($row['features'])) {
        $featuresJson = catalogEncodeLiveProjectFeatures($row['features']);
    } elseif (!empty($row['features_json'])) {
        $featuresJson = (string) $row['features_json'];
    }

    $params = [
        $row['title'] ?? '',
        $row['short_desc'] ?? '',
        $row['description'] ?? '',
        $row['category'] ?? '',
        $row['icon_class'] ?? 'fa-code-branch',
        $row['card_theme'] ?? 'theme-default',
        $row['image_path'] ?? '',
        $featuresJson,
        $row['stack'] ?? '',
        $row['duration'] ?? '',
        $row['team_size'] ?? ($row['team'] ?? ''),
        $row['status'] ?? 'Open for Collaboration',
        $sortOrder,
        $slug,
    ];

    $exists = db()->fetchOne('SELECT id FROM live_projects WHERE slug = ? LIMIT 1', [$slug]);
    if ($exists) {
        db()->execute(
            'UPDATE live_projects SET title = ?, short_desc = ?, description = ?, category = ?,
             icon_class = ?, card_theme = ?, image_path = ?, features_json = ?, stack = ?,
             duration = ?, team_size = ?, status = ?, sort_order = ?, deleted_at = NULL, is_blocked = 0
             WHERE slug = ?',
            $params
        );
        return;
    }

    catalogInsertLiveProject($row, $sortOrder);
}

/**
 * Insert default live projects if missing by slug.
 *
 * @param list<string> $slugs
 */
function catalogEnsureLiveProjectsExist(array $slugs): void
{
    ensureCatalogContentSchemas();
    $defaults = catalogDefaultLiveProjects();
    $bySlug = [];
    foreach ($defaults as $row) {
        $s = (string) ($row['slug'] ?? '');
        if ($s !== '') {
            $bySlug[$s] = $row;
        }
    }

    $order = (int) (db()->fetchOne('SELECT COALESCE(MAX(sort_order),0) AS m FROM live_projects')['m'] ?? 0) + 1;
    foreach ($slugs as $slug) {
        $slug = trim((string) $slug);
        if ($slug === '' || !isset($bySlug[$slug])) {
            continue;
        }
        $exists = (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM live_projects WHERE slug = ?', [$slug])['c'] ?? 0);
        if ($exists > 0) {
            continue;
        }
        catalogInsertLiveProject($bySlug[$slug], $order++);
    }
}

/**
 * Insert default freelance projects if missing by slug.
 *
 * @param list<string> $slugs
 */
function catalogEnsureFreelanceProjectsExist(array $slugs): void
{
    ensureCatalogContentSchemas();
    $defaults = catalogDefaultFreelanceProjects();
    $bySlug = [];
    foreach ($defaults as $row) {
        $s = (string) ($row['slug'] ?? '');
        if ($s !== '') {
            $bySlug[$s] = $row;
        }
    }

    $order = (int) (db()->fetchOne('SELECT COALESCE(MAX(sort_order),0) AS m FROM freelance_projects')['m'] ?? 0) + 1;
    foreach ($slugs as $slug) {
        $slug = trim((string) $slug);
        if ($slug === '' || !isset($bySlug[$slug])) {
            continue;
        }
        $exists = (int) (db()->fetchOne('SELECT COUNT(*) AS c FROM freelance_projects WHERE slug = ?', [$slug])['c'] ?? 0);
        if ($exists > 0) {
            continue;
        }
        catalogInsertFreelanceProject($bySlug[$slug], $order++);
    }
}

/** @return list<array<string, mixed>> */
function catalogGetLiveProjects(bool $adminList = false, bool $homepageOnly = false): array
{
    ensureCatalogContentSchemas();
    $parts = [
        $adminList ? adminSqlActive('lp') : catalogSqlPublic('lp') . ' AND ' . catalogSqlLiveProjectPublicActive('lp'),
        catalogSqlProjectType(CATALOG_PROJECT_TYPE_LIVE, 'lp'),
    ];
    if ($homepageOnly) {
        $parts[] = catalogSqlHomepageFeatured('lp');
    }
    $where = implode(' AND ', $parts);
    $rows = db()->fetchAll(
        "SELECT lp.* FROM live_projects lp WHERE {$where} ORDER BY lp.sort_order ASC, lp.title ASC"
    );
    return array_map('catalogMapLiveProjectPublic', $rows);
}

function catalogGetLiveProjectBySlug(string $slug, bool $admin = false): ?array
{
    ensureCatalogContentSchemas();
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $parts = ['slug = ?', catalogSqlProjectType(CATALOG_PROJECT_TYPE_LIVE)];
    if (!$admin) {
        $parts[] = catalogSqlPublic();
        $parts[] = catalogSqlLiveProjectPublicActive();
    }
    $where = implode(' AND ', $parts);
    $row = db()->fetchOne("SELECT * FROM live_projects WHERE {$where} LIMIT 1", [$slug]);
    return $row ? catalogMapLiveProjectPublic($row) : null;
}

function catalogGetLiveProjectById(int $id): ?array
{
    ensureCatalogContentSchemas();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM live_projects WHERE id = ?', [$id]);
    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function catalogGetAssignments(bool $adminList = false, bool $homepageOnly = false): array
{
    ensureCatalogContentSchemas();
    $parts = [
        $adminList ? adminSqlActive('a') : catalogSqlPublic('a') . ' AND ' . catalogSqlAssignmentPublicActive('a'),
        catalogSqlProjectType(CATALOG_PROJECT_TYPE_HANDS_ON, 'a'),
    ];
    if ($homepageOnly) {
        $parts[] = catalogSqlHomepageFeatured('a');
    }
    $where = implode(' AND ', $parts);
    $rows = db()->fetchAll(
        "SELECT a.* FROM assignments a WHERE {$where} ORDER BY a.sort_order ASC, a.title ASC"
    );
    return array_map('catalogMapAssignmentPublic', $rows);
}

function catalogGetAssignmentBySlug(string $slug, bool $admin = false): ?array
{
    ensureCatalogContentSchemas();
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $parts = ['slug = ?', catalogSqlProjectType(CATALOG_PROJECT_TYPE_HANDS_ON)];
    if (!$admin) {
        $parts[] = catalogSqlPublic();
        $parts[] = catalogSqlAssignmentPublicActive();
    }
    $where = implode(' AND ', $parts);
    $row = db()->fetchOne("SELECT * FROM assignments WHERE {$where} LIMIT 1", [$slug]);
    return $row ? catalogMapAssignmentPublic($row) : null;
}

function catalogGetAssignmentById(int $id): ?array
{
    ensureCatalogContentSchemas();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM assignments WHERE id = ?', [$id]);
    return $row ?: null;
}

/** @return list<array<string, mixed>> */
function catalogGetFreelanceProjects(bool $adminList = false, bool $homepageOnly = false): array
{
    ensureCatalogContentSchemas();
    $parts = [
        $adminList ? adminSqlActive('fp') : catalogSqlPublic('fp') . ' AND ' . catalogSqlFreelancePublicActive('fp'),
        catalogSqlProjectType(CATALOG_PROJECT_TYPE_FREELANCER, 'fp'),
    ];
    if ($homepageOnly) {
        $parts[] = catalogSqlHomepageFeatured('fp');
    }
    $where = implode(' AND ', $parts);
    $rows = db()->fetchAll(
        "SELECT fp.* FROM freelance_projects fp WHERE {$where} ORDER BY fp.sort_order ASC, fp.title ASC"
    );
    return array_map('catalogMapFreelanceProjectPublic', $rows);
}

function catalogGetFreelanceProjectBySlug(string $slug, bool $admin = false): ?array
{
    ensureCatalogContentSchemas();
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $parts = ['slug = ?', catalogSqlProjectType(CATALOG_PROJECT_TYPE_FREELANCER)];
    if (!$admin) {
        $parts[] = catalogSqlPublic();
        $parts[] = catalogSqlFreelancePublicActive();
    }
    $where = implode(' AND ', $parts);
    $row = db()->fetchOne("SELECT * FROM freelance_projects WHERE {$where} LIMIT 1", [$slug]);
    return $row ? catalogMapFreelanceProjectPublic($row) : null;
}

function catalogGetFreelanceProjectById(int $id): ?array
{
    ensureCatalogContentSchemas();
    if ($id < 1) {
        return null;
    }
    $row = db()->fetchOne('SELECT * FROM freelance_projects WHERE id = ?', [$id]);
    return $row ?: null;
}

function catalogMapLiveProjectPublic(array $row): array
{
    return [
        'id'            => (int) ($row['id'] ?? 0),
        'slug'          => (string) ($row['slug'] ?? ''),
        'title'         => (string) ($row['title'] ?? ''),
        'short_desc'    => (string) ($row['short_desc'] ?? ''),
        'description'   => (string) ($row['description'] ?? ''),
        'category'      => (string) ($row['category'] ?? ''),
        'stack'         => (string) ($row['stack'] ?? ''),
        'duration'      => (string) ($row['duration'] ?? ''),
        'team'          => (string) ($row['team_size'] ?? ''),
        'status'            => (string) ($row['status'] ?? ''),
        'project_type'      => (string) ($row['project_type'] ?? CATALOG_PROJECT_TYPE_LIVE),
        'show_on_homepage'  => (int) ($row['show_on_homepage'] ?? 0),
        'icon_class'    => (string) ($row['icon_class'] ?? 'fa-code-branch'),
        'card_theme'    => (string) ($row['card_theme'] ?? 'theme-default'),
        'image_path'    => (string) ($row['image_path'] ?? ''),
        'features'      => catalogDecodeLiveProjectFeatures((string) ($row['features_json'] ?? '')),
        'sort_order'    => (int) ($row['sort_order'] ?? 0),
        'is_blocked'    => (int) ($row['is_blocked'] ?? 0),
        'deleted_at'    => $row['deleted_at'] ?? null,
    ];
}

/** @return list<array{icon:string,label:string}> */
function catalogDecodeLiveProjectFeatures(?string $json): array
{
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        if (is_string($item)) {
            $out[] = ['icon' => 'fa-check', 'label' => $item];
            continue;
        }
        if (is_array($item) && !empty($item['label'])) {
            $out[] = [
                'icon'  => (string) ($item['icon'] ?? 'fa-check'),
                'label' => (string) $item['label'],
            ];
        }
    }
    return $out;
}

/** @param list<string|array{icon?:string,label:string}> $features */
function catalogEncodeLiveProjectFeatures(array $features): string
{
    $normalized = [];
    foreach ($features as $item) {
        if (is_string($item)) {
            $label = trim($item);
            if ($label !== '') {
                $normalized[] = ['icon' => 'fa-check', 'label' => $label];
            }
            continue;
        }
        if (is_array($item) && trim((string) ($item['label'] ?? '')) !== '') {
            $normalized[] = [
                'icon'  => trim((string) ($item['icon'] ?? 'fa-check')) ?: 'fa-check',
                'label' => trim((string) $item['label']),
            ];
        }
    }
    return $normalized === [] ? '' : json_encode($normalized, JSON_THROW_ON_ERROR);
}

/** Parse admin textarea: one feature per line, optional "icon|Label" format. */
function catalogParseFeaturesTextarea(string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $features = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (str_contains($line, '|')) {
            [$icon, $label] = array_map('trim', explode('|', $line, 2));
            $features[] = ['icon' => $icon !== '' ? $icon : 'fa-check', 'label' => $label];
        } else {
            $features[] = ['icon' => 'fa-check', 'label' => $line];
        }
    }
    return $features;
}

function catalogFormatFeaturesForTextarea(array $features): string
{
    $lines = [];
    foreach ($features as $item) {
        if (is_string($item)) {
            $lines[] = $item;
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $label = trim((string) ($item['label'] ?? ''));
        if ($label === '') {
            continue;
        }
        $icon = trim((string) ($item['icon'] ?? 'fa-check'));
        $lines[] = ($icon !== '' && $icon !== 'fa-check') ? $icon . '|' . $label : $label;
    }
    return implode("\n", $lines);
}

function catalogMapAssignmentPublic(array $row): array
{
    return [
        'id'          => (int) ($row['id'] ?? 0),
        'slug'        => (string) ($row['slug'] ?? ''),
        'title'       => (string) ($row['title'] ?? ''),
        'short_desc'  => (string) ($row['short_desc'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'category'    => (string) ($row['category'] ?? ''),
        'type'        => (string) ($row['type'] ?? 'Project'),
        'skills'      => (string) ($row['skills'] ?? ''),
        'duration'    => (string) ($row['duration'] ?? ''),
        'mode'        => (string) ($row['mode'] ?? ''),
        'status'           => (string) ($row['status'] ?? 'Open'),
        'project_type'     => (string) ($row['project_type'] ?? CATALOG_PROJECT_TYPE_HANDS_ON),
        'show_on_homepage' => (int) ($row['show_on_homepage'] ?? 0),
        'apply'            => (string) ($row['apply_route'] ?? ''),
        'sort_order'       => (int) ($row['sort_order'] ?? 0),
        'is_blocked'       => (int) ($row['is_blocked'] ?? 0),
        'deleted_at'       => $row['deleted_at'] ?? null,
    ];
}

function catalogMapFreelanceProjectPublic(array $row): array
{
    return [
        'id'           => (int) ($row['id'] ?? 0),
        'slug'         => (string) ($row['slug'] ?? ''),
        'title'        => (string) ($row['title'] ?? ''),
        'short_desc'   => (string) ($row['short_desc'] ?? ''),
        'description'  => (string) ($row['description'] ?? ''),
        'category'     => (string) ($row['category'] ?? ''),
        'skills'       => (string) ($row['skills'] ?? ''),
        'duration'     => (string) ($row['duration'] ?? ''),
        'mode'         => (string) ($row['mode'] ?? ''),
        'budget'       => (string) ($row['budget_label'] ?? ''),
        'client_name'  => (string) ($row['client_name'] ?? ''),
        'image_path'   => (string) ($row['image_path'] ?? ''),
        'status'           => (string) ($row['status'] ?? 'Open'),
        'project_type'     => (string) ($row['project_type'] ?? CATALOG_PROJECT_TYPE_FREELANCER),
        'show_on_homepage' => (int) ($row['show_on_homepage'] ?? 0),
        'apply'            => (string) ($row['apply_route'] ?? 'register-freelancer.php'),
        'sort_order'       => (int) ($row['sort_order'] ?? 0),
        'is_blocked'       => (int) ($row['is_blocked'] ?? 0),
        'deleted_at'       => $row['deleted_at'] ?? null,
    ];
}

function catalogInsertLiveProject(array $data, int $sortOrder = 0): int
{
    $slug = $data['slug'] ?? slugify((string) ($data['title'] ?? ''));
    $featuresJson = '';
    if (!empty($data['features']) && is_array($data['features'])) {
        $featuresJson = catalogEncodeLiveProjectFeatures($data['features']);
    } elseif (!empty($data['features_json'])) {
        $featuresJson = (string) $data['features_json'];
    }

    return (int) db()->insert(
        'INSERT INTO live_projects (title, slug, short_desc, description, category, icon_class, card_theme, image_path, features_json, stack, duration, team_size, status, project_type, show_on_homepage, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $data['title'] ?? '',
            $slug,
            $data['short_desc'] ?? '',
            $data['description'] ?? '',
            $data['category'] ?? '',
            $data['icon_class'] ?? 'fa-code-branch',
            $data['card_theme'] ?? 'theme-default',
            $data['image_path'] ?? '',
            $featuresJson,
            $data['stack'] ?? '',
            $data['duration'] ?? '',
            $data['team_size'] ?? ($data['team'] ?? ''),
            $data['status'] ?? 'Open for Collaboration',
            $data['project_type'] ?? CATALOG_PROJECT_TYPE_LIVE,
            catalogParseShowOnHomepage($data['show_on_homepage'] ?? 0),
            $sortOrder,
        ]
    );
}

function catalogInsertAssignment(array $data, int $sortOrder = 0): int
{
    $slug = $data['slug'] ?? slugify((string) ($data['title'] ?? ''));
    return (int) db()->insert(
        'INSERT INTO assignments (title, slug, short_desc, description, category, type, skills, duration, mode, status, apply_route, project_type, show_on_homepage, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $data['title'] ?? '',
            $slug,
            $data['short_desc'] ?? '',
            $data['description'] ?? '',
            $data['category'] ?? '',
            $data['type'] ?? 'Project',
            $data['skills'] ?? '',
            $data['duration'] ?? '',
            $data['mode'] ?? '',
            $data['status'] ?? 'Open',
            $data['apply_route'] ?? ($data['apply'] ?? ''),
            $data['project_type'] ?? CATALOG_PROJECT_TYPE_HANDS_ON,
            catalogParseShowOnHomepage($data['show_on_homepage'] ?? 0),
            $sortOrder,
        ]
    );
}

function catalogInsertFreelanceProject(array $data, int $sortOrder = 0): int
{
    $slug = $data['slug'] ?? slugify((string) ($data['title'] ?? ''));
    return (int) db()->insert(
        'INSERT INTO freelance_projects (title, slug, short_desc, description, category, skills, duration, mode, budget_label, client_name, image_path, status, apply_route, project_type, show_on_homepage, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $data['title'] ?? '',
            $slug,
            $data['short_desc'] ?? '',
            $data['description'] ?? '',
            $data['category'] ?? '',
            $data['skills'] ?? '',
            $data['duration'] ?? '',
            $data['mode'] ?? '',
            $data['budget_label'] ?? ($data['budget'] ?? ''),
            $data['client_name'] ?? '',
            $data['image_path'] ?? '',
            $data['status'] ?? 'Open',
            $data['apply_route'] ?? ($data['apply'] ?? 'register-freelancer.php'),
            $data['project_type'] ?? CATALOG_PROJECT_TYPE_FREELANCER,
            catalogParseShowOnHomepage($data['show_on_homepage'] ?? 0),
            $sortOrder,
        ]
    );
}

function catalogLiveProjectStatusOptions(): array
{
    return ['Planning', 'Open for Collaboration', 'In Progress', 'Completed', 'Cancelled'];
}

function catalogAssignmentStatusOptions(): array
{
    return ['Open', 'Closing Soon', 'Filled', 'Closed'];
}

function catalogAssignmentTypeOptions(): array
{
    return ['Internship', 'Project', 'Freelance'];
}

function catalogFreelanceProjectStatusOptions(): array
{
    return ['Active', 'Inactive'];
}

function catalogLiveProjectImage(string $filename): string
{
    return 'assets/images/live_projects/' . $filename;
}

/** @return list<array<string, mixed>> */
function catalogDefaultLiveProjects(): array
{
    return [
        [
            'slug'        => 'ai-and-ml',
            'title'       => 'AI & ML1',
            'category'    => 'Artificial Intelligence',
            'short_desc'  => 'LLM integrations, automation, and ML prototypes for business workflows.',
            'description' => 'AI & ML live projects including chatbots, document intelligence, recommendation systems, and responsible AI guardrails for production pilots.',
            'stack'       => 'Python, TensorFlow, APIs, LLMs',
            'duration'    => '6–10 weeks',
            'team'        => '3–5 engineers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-brain',
            'card_theme'  => 'theme-ai',
            'image_path'  => catalogLiveProjectImage('AI & ML1.png'),
            'features'    => [
                ['icon' => 'fa-robot', 'label' => 'AI assistants & chatbots'],
                ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Workflow automation'],
                ['icon' => 'fa-image', 'label' => 'Vision & NLP pilots'],
                ['icon' => 'fa-scale-balanced', 'label' => 'Responsible AI practices'],
            ],
        ],
        [
            'slug'        => 'blockchain-technology',
            'title'       => 'blockchain_technology',
            'category'    => 'Blockchain',
            'short_desc'  => 'Decentralized apps, smart contracts, and Web3 integrations on testnets.',
            'description' => 'Blockchain technology live projects covering smart contract development, wallet integrations, token utilities, and secure Web3 deployment workflows.',
            'stack'       => 'Solidity, Web3.js, Ethereum, Polygon',
            'duration'    => '8–12 weeks',
            'team'        => '3–5 developers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-link',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('blockchain_technology.png'),
            'features'    => [
                ['icon' => 'fa-cube', 'label' => 'Smart contract development'],
                ['icon' => 'fa-network-wired', 'label' => 'Decentralized architecture'],
                ['icon' => 'fa-shield-halved', 'label' => 'Secure Web3 deployment'],
                ['icon' => 'fa-code', 'label' => 'DApp integrations'],
            ],
        ],
        [
            'slug'        => 'buy-credits-for-shopping-system',
            'title'       => 'Buy Credits for Shopping System',
            'category'    => 'FinTech',
            'short_desc'  => 'Purchase NxL credits securely for shopping, rewards, and loyalty redemption.',
            'description' => 'Live project for buying credits in a shopping system — secure checkout, instant credit delivery, and balance management for e-commerce and loyalty platforms.',
            'stack'       => 'PHP, Razorpay, MySQL, REST APIs',
            'duration'    => 'Ongoing platform',
            'team'        => 'Product squad',
            'status'      => 'In Progress',
            'icon_class'  => 'fa-cart-shopping',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('Buy Credits for Shopping System.png'),
            'features'    => [
                ['icon' => 'fa-credit-card', 'label' => 'Secure credit purchase'],
                ['icon' => 'fa-bolt', 'label' => 'Instant delivery'],
                ['icon' => 'fa-wallet', 'label' => 'Balance management'],
                ['icon' => 'fa-shield', 'label' => 'Encrypted transactions'],
            ],
        ],
        [
            'slug'        => 'cafe-merchant-redemption-pos-bridge',
            'title'       => 'Cafe Merchant Redemption & POS Bridge',
            'category'    => 'FinTech',
            'short_desc'  => 'Seamless credit redemption integrated with cafe POS workflows.',
            'description' => 'Bridge live project connecting merchant redemption flows with POS systems — QR redemption, real-time sync, and accurate reporting for cafe partners.',
            'stack'       => 'POS APIs, PHP, MySQL, QR',
            'duration'    => 'Ongoing platform',
            'team'        => 'Integration squad',
            'status'      => 'In Progress',
            'icon_class'  => 'fa-mug-hot',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('Cafe Merchant Redemption & POS Bridge.png'),
            'features'    => [
                ['icon' => 'fa-qrcode', 'label' => 'QR redemption'],
                ['icon' => 'fa-cash-register', 'label' => 'POS integration'],
                ['icon' => 'fa-arrows-rotate', 'label' => 'Real-time sync'],
                ['icon' => 'fa-chart-bar', 'label' => 'Merchant reporting'],
            ],
        ],
        [
            'slug'        => 'cyber-security',
            'title'       => 'Cybersecurity',
            'category'    => 'Security',
            'short_desc'  => 'Ethical hacking, SOC workflows, and secure deployment for real-world systems.',
            'description' => 'Hands-on cybersecurity live projects covering vulnerability assessment, penetration testing, incident response, and security hardening for web and cloud workloads.',
            'stack'       => 'OWASP, Burp Suite, Linux, SIEM',
            'duration'    => 'Flexible cohorts',
            'team'        => 'Mentor-led squads',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-shield-halved',
            'card_theme'  => 'theme-cyber',
            'image_path'  => catalogLiveProjectImage('Cybersecurity.png'),
            'features'    => [
                ['icon' => 'fa-bug', 'label' => 'Vulnerability Assessment'],
                ['icon' => 'fa-user-secret', 'label' => 'Ethical Hacking Labs'],
                ['icon' => 'fa-server', 'label' => 'SOC & Incident Response'],
                ['icon' => 'fa-lock', 'label' => 'Secure Deployment'],
            ],
        ],
        [
            'slug'        => 'data-bases-secutity',
            'title'       => 'data bases secutity',
            'category'    => 'Cybersecurity',
            'short_desc'  => 'Database access control, encryption, audit monitoring, and threat prevention.',
            'description' => 'Database security live project focused on access control, authentication, encryption at rest and in transit, audit monitoring, and SQL injection protection.',
            'stack'       => 'PostgreSQL, MySQL, Vault, RBAC',
            'duration'    => '6–10 weeks',
            'team'        => 'Security engineers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-database',
            'card_theme'  => 'theme-cyber',
            'image_path'  => catalogLiveProjectImage('data bases secutity.png'),
            'features'    => [
                ['icon' => 'fa-user-lock', 'label' => 'Access control'],
                ['icon' => 'fa-lock', 'label' => 'Data encryption'],
                ['icon' => 'fa-eye', 'label' => 'Audit & monitoring'],
                ['icon' => 'fa-shield-virus', 'label' => 'SQL injection protection'],
            ],
        ],
        [
            'slug'        => 'data-science',
            'title'       => 'Data Science',
            'category'    => 'Analytics',
            'short_desc'  => 'Analytics pipelines, visualization, and predictive models on real datasets.',
            'description' => 'Data science live projects spanning data cleaning, exploratory analysis, dashboards, and introductory machine learning with Python ecosystems.',
            'stack'       => 'Python, Pandas, SQL, Jupyter',
            'duration'    => '6–8 weeks',
            'team'        => '3–4 analysts',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-chart-line',
            'card_theme'  => 'theme-data',
            'image_path'  => catalogLiveProjectImage('Data Science.png'),
            'features'    => [
                ['icon' => 'fa-table', 'label' => 'Data wrangling & ETL'],
                ['icon' => 'fa-chart-pie', 'label' => 'Dashboards & visualization'],
                ['icon' => 'fa-brain', 'label' => 'Predictive modeling'],
                ['icon' => 'fa-file-lines', 'label' => 'Insight reports'],
            ],
        ],
        [
            'slug'        => 'database-security-cryptographic-protection-suite',
            'title'       => 'Database Security & Cryptographic Protection Suite',
            'category'    => 'Cybersecurity & Infrastructure',
            'short_desc'  => 'Enterprise database hardening with masking, encryption, and SQL injection defense.',
            'description' => 'An enterprise-grade database hardening system featuring automated masking, end-to-end encryption, and real-time SQL injection defense.',
            'stack'       => 'Python, PostgreSQL, MySQL, Redis, Vault, Docker',
            'duration'    => '8–12 weeks',
            'team'        => 'Security squad',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-database',
            'card_theme'  => 'theme-cyber',
            'image_path'  => catalogLiveProjectImage('Database Security & Cryptographic Protection Suite.png'),
            'features'    => [
                ['icon' => 'fa-mask', 'label' => 'Automated data masking'],
                ['icon' => 'fa-lock', 'label' => 'End-to-end encryption'],
                ['icon' => 'fa-shield-virus', 'label' => 'SQL injection defense'],
                ['icon' => 'fa-server', 'label' => 'Enterprise database hardening'],
            ],
        ],
        [
            'slug'        => 'fintech',
            'title'       => 'FinTech',
            'category'    => 'Financial Technology',
            'short_desc'  => 'Digital payments, blockchain, AI analytics, and secure financial platforms.',
            'description' => 'FinTech live project track — digital payments, blockchain technology, AI & ML analytics, and security-first financial product development.',
            'stack'       => 'Payments, Blockchain, Python, Razorpay',
            'duration'    => 'Ongoing platform',
            'team'        => 'Cross-functional squad',
            'status'      => 'In Progress',
            'icon_class'  => 'fa-coins',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('FinTech.png'),
            'features'    => [
                ['icon' => 'fa-credit-card', 'label' => 'Digital payments'],
                ['icon' => 'fa-link', 'label' => 'Blockchain technology'],
                ['icon' => 'fa-brain', 'label' => 'AI & ML analytics'],
                ['icon' => 'fa-shield-halved', 'label' => 'Security & privacy'],
            ],
        ],
        [
            'slug'        => 'java-full-stack-development',
            'title'       => 'Java Full Stack Development',
            'category'    => 'Enterprise',
            'short_desc'  => 'Build scalable enterprise apps with Java, Spring Boot, and modern frontends.',
            'description' => 'End-to-end Java full stack live projects: REST APIs, Spring Security, JPA/Hibernate, microservices basics, and integration with React or Thymeleaf dashboards.',
            'stack'       => 'Java, Spring Boot, MySQL, REST',
            'duration'    => '8–12 weeks',
            'team'        => '4–6 developers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-brands fa-java',
            'card_theme'  => 'theme-java',
            'image_path'  => catalogLiveProjectImage('Java Full Stack Development.png'),
            'features'    => [
                ['icon' => 'fa-leaf', 'label' => 'Spring Boot APIs'],
                ['icon' => 'fa-database', 'label' => 'JPA & MySQL'],
                ['icon' => 'fa-shield', 'label' => 'Spring Security'],
                ['icon' => 'fa-cloud', 'label' => 'Deployment & CI/CD'],
            ],
        ],
        [
            'slug'        => 'mern-full-stack-development',
            'title'       => 'MERN Full Stack Development',
            'category'    => 'Web',
            'short_desc'  => 'MongoDB, Express, React, and Node.js product builds from MVP to production.',
            'description' => 'MERN stack live projects with JWT auth, payment hooks, admin dashboards, and cloud-ready Node APIs — ideal for startups and product teams.',
            'stack'       => 'MongoDB, Express, React, Node.js',
            'duration'    => '6–10 weeks',
            'team'        => '3–5 developers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-layer-group',
            'card_theme'  => 'theme-mern',
            'image_path'  => catalogLiveProjectImage('MERN Full Stack Development.png'),
            'features'    => [
                ['icon' => 'fa-code', 'label' => 'React SPA / SSR'],
                ['icon' => 'fa-route', 'label' => 'Express REST APIs'],
                ['icon' => 'fa-database', 'label' => 'MongoDB data modeling'],
                ['icon' => 'fa-rocket', 'label' => 'MVP to production'],
            ],
        ],
        [
            'slug'        => 'own-nxl-credits-tokens-system',
            'title'       => 'Own NXL Credits Tokens System',
            'category'    => 'FinTech',
            'short_desc'  => 'Own, use, and grow with the CYBEORCH NxL credits token ecosystem.',
            'description' => 'Live project for the Own NXL Credits Tokens System — secure token ownership, rewards, community-driven growth, and blockchain-powered utility.',
            'stack'       => 'Blockchain, PHP, MySQL, Wallet APIs',
            'duration'    => 'Ongoing platform',
            'team'        => 'Token squad',
            'status'      => 'In Progress',
            'icon_class'  => 'fa-star',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('Own NXL Credits Tokens System.png'),
            'features'    => [
                ['icon' => 'fa-wallet', 'label' => 'Own & control tokens'],
                ['icon' => 'fa-cart-shopping', 'label' => 'Use everywhere'],
                ['icon' => 'fa-chart-line', 'label' => 'Earn rewards'],
                ['icon' => 'fa-users', 'label' => 'Community driven'],
            ],
        ],
        [
            'slug'        => 'payment-gateway',
            'title'       => 'payment gateway',
            'category'    => 'FinTech',
            'short_desc'  => 'Accept secure payments with PCI-compliant gateway integrations.',
            'description' => 'Payment gateway live project — encrypted transactions, multi-method checkout, fraud protection, and easy API integration for web and mobile apps.',
            'stack'       => 'Razorpay, PHP, REST APIs, Webhooks',
            'duration'    => 'Ongoing platform',
            'team'        => 'Payments squad',
            'status'      => 'In Progress',
            'icon_class'  => 'fa-credit-card',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('payment gateway.png'),
            'features'    => [
                ['icon' => 'fa-lock', 'label' => 'Encrypted transactions'],
                ['icon' => 'fa-bolt', 'label' => 'Instant payments'],
                ['icon' => 'fa-globe', 'label' => 'Global acceptance'],
                ['icon' => 'fa-shield-halved', 'label' => 'Fraud protection'],
            ],
        ],
        [
            'slug'        => 'token-creation',
            'title'       => 'Token creation',
            'category'    => 'Blockchain',
            'short_desc'  => 'Configure, deploy, verify, and launch custom tokens on major chains.',
            'description' => 'Token creation live project supporting ERC-20, BEP-20, Polygon, and Solana deployments with secure configuration and fast launch workflows.',
            'stack'       => 'Solidity, Web3, Ethereum, BSC',
            'duration'    => '4–8 weeks',
            'team'        => '3–4 developers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-coins',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('Token creation.png'),
            'features'    => [
                ['icon' => 'fa-sliders', 'label' => 'Configure token'],
                ['icon' => 'fa-rocket', 'label' => 'Deploy & launch'],
                ['icon' => 'fa-shield', 'label' => 'Secure & reliable'],
                ['icon' => 'fa-chart-line', 'label' => 'Grow your project'],
            ],
        ],
        [
            'slug'        => 'web3-crypto-wallet-creation',
            'title'       => 'Web3 Crypto Wallet Creation',
            'category'    => 'Web3',
            'short_desc'  => 'Self-custody Web3 wallets with secure key management and multi-chain support.',
            'description' => 'Web3 crypto wallet creation live project — self-custody design, secure private keys, portfolio dashboards, and Web3-ready transaction flows.',
            'stack'       => 'Web3.js, React, Ethereum, Solana',
            'duration'    => '6–10 weeks',
            'team'        => '3–5 engineers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-wallet',
            'card_theme'  => 'theme-fintech',
            'image_path'  => catalogLiveProjectImage('Web3 Crypto Wallet Creation.png'),
            'features'    => [
                ['icon' => 'fa-key', 'label' => 'Self-custody keys'],
                ['icon' => 'fa-shield-halved', 'label' => 'Secure by design'],
                ['icon' => 'fa-globe', 'label' => 'Web3 ready'],
                ['icon' => 'fa-mobile', 'label' => 'Mobile-friendly UI'],
            ],
        ],
        [
            'slug'        => 'web3-wallet-security-cryptographic-protection',
            'title'       => 'Web3 Wallet Security & Cryptographic Protection',
            'category'    => 'Web3 Security',
            'short_desc'  => 'Non-custodial wallet security with AES-256 encryption and phishing protection.',
            'description' => 'Web3 wallet security live project — non-custodial architecture, strong encryption, private key control, transaction safety, and phishing protection.',
            'stack'       => 'Cryptography, Web3, AES-256, HD Wallets',
            'duration'    => '6–10 weeks',
            'team'        => 'Security engineers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-shield-halved',
            'card_theme'  => 'theme-cyber',
            'image_path'  => catalogLiveProjectImage('Web3 Wallet Security & Cryptographic Protection.png'),
            'features'    => [
                ['icon' => 'fa-lock', 'label' => 'AES-256 encryption'],
                ['icon' => 'fa-key', 'label' => 'Private key control'],
                ['icon' => 'fa-user-shield', 'label' => 'Phishing protection'],
                ['icon' => 'fa-file-signature', 'label' => 'Transaction safety'],
            ],
        ],
    ];
}

/** @return list<array<string, mixed>> */
function catalogDefaultAssignments(): array
{
    return [
        [
            'slug' => 'full-stack-web-developer',
            'title' => 'Full Stack Web Developer',
            'category' => 'Software Development',
            'type' => 'Internship',
            'description' => 'Build and maintain PHP/React modules for client dashboards, APIs, and admin panels in a live product environment.',
            'skills' => 'PHP, MySQL, JavaScript, REST APIs',
            'duration' => '8–12 weeks',
            'mode' => 'Hybrid',
            'status' => 'Open',
            'apply' => 'index.php?register_required=1',
        ],
        [
            'slug' => 'cybersecurity-analyst',
            'title' => 'Cybersecurity Analyst',
            'category' => 'Cybersecurity',
            'type' => 'Project',
            'description' => 'Support vulnerability assessments, secure deployment reviews, and documentation for SME web applications.',
            'skills' => 'OWASP, Nmap, Burp Suite, Linux',
            'duration' => '6 weeks',
            'mode' => 'Remote',
            'status' => 'Open',
            'apply' => 'index.php?register_required=1',
        ],
        [
            'slug' => 'ui-ux-designer',
            'title' => 'UI/UX Designer',
            'category' => 'Design',
            'type' => 'Freelance',
            'description' => 'Design responsive interfaces and prototypes for EdTech and startup MVP products under mentor review.',
            'skills' => 'Figma, Wireframing, Design systems',
            'duration' => '4–8 weeks',
            'mode' => 'Remote',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'ai-automation-developer',
            'title' => 'AI Automation Developer',
            'category' => 'AI & Automation',
            'type' => 'Project',
            'description' => 'Integrate AI tools into internal workflows: chatbots, document processing, and automation scripts for operations teams.',
            'skills' => 'Python, APIs, Prompt engineering',
            'duration' => '6 weeks',
            'mode' => 'Remote',
            'status' => 'Open',
            'apply' => 'index.php?register_required=1',
        ],
        [
            'slug' => 'devops-cloud-intern',
            'title' => 'DevOps & Cloud Intern',
            'category' => 'DevOps',
            'type' => 'Internship',
            'description' => 'Assist with CI/CD pipelines, server hardening, Docker deployments, and monitoring for lab and client projects.',
            'skills' => 'Linux, Docker, AWS basics, Git',
            'duration' => '10 weeks',
            'mode' => 'On-site / Hybrid',
            'status' => 'Open',
            'apply' => 'index.php?register_required=1',
        ],
        [
            'slug' => 'mobile-app-developer',
            'title' => 'Mobile App Developer',
            'category' => 'Mobile',
            'type' => 'Freelance',
            'description' => 'Contribute to Flutter/React Native features for product MVPs with weekly sprint reviews and code mentorship.',
            'skills' => 'Flutter or React Native, Firebase',
            'duration' => 'Project-based',
            'mode' => 'Remote',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'qa-security-testing',
            'title' => 'QA & Security Testing',
            'category' => 'Quality Assurance',
            'type' => 'Project',
            'description' => 'Execute test plans, regression suites, and basic security checks before client releases and demo milestones.',
            'skills' => 'Manual testing, Selenium, OWASP basics',
            'duration' => '4 weeks',
            'mode' => 'Remote',
            'status' => 'Closing Soon',
            'apply' => 'index.php?register_required=1',
        ],
        [
            'slug' => 'blockchain-utility-developer',
            'title' => 'Blockchain Utility Developer',
            'category' => 'Web3',
            'type' => 'Freelance',
            'description' => 'Build smart contract utilities and Web3 integration prototypes for internal R&D and startup consulting engagements.',
            'skills' => 'Solidity, Web3.js, Ethereum',
            'duration' => 'Project-based',
            'mode' => 'Remote',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'soc-analyst-intern',
            'title' => 'SOC Analyst Intern',
            'category' => 'Cybersecurity',
            'type' => 'Internship',
            'description' => 'Monitor security alerts, triage incidents, and support SIEM log analysis and incident response playbooks in live lab environments.',
            'skills' => 'SIEM, Log analysis, Networking, Incident response',
            'duration' => '8 weeks',
            'mode' => 'Hybrid',
            'status' => 'Open',
            'apply' => 'index.php?register_required=1',
        ],
    ];
}

/** @return list<array<string, mixed>> */
function catalogDefaultFreelanceProjects(): array
{
    return [
        [
            'slug' => 'ui-ux-designer-freelance',
            'title' => 'UI/UX Designer — EdTech MVP',
            'category' => 'Design',
            'description' => 'Design responsive interfaces and prototypes for EdTech and startup MVP products under mentor review.',
            'skills' => 'Figma, Wireframing, Design systems',
            'duration' => '4–8 weeks',
            'mode' => 'Remote',
            'budget' => '₹25,000 – ₹60,000',
            'client_name' => 'CYBEORCH Studio',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'mobile-app-developer-freelance',
            'title' => 'Mobile App Developer — Product MVP',
            'category' => 'Mobile',
            'description' => 'Contribute to Flutter/React Native features for product MVPs with weekly sprint reviews and code mentorship.',
            'skills' => 'Flutter or React Native, Firebase',
            'duration' => 'Project-based',
            'mode' => 'Remote',
            'budget' => '₹40,000 – ₹1,20,000',
            'client_name' => 'Startup Partner',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'blockchain-utility-freelance',
            'title' => 'Blockchain Utility Developer',
            'category' => 'Web3',
            'description' => 'Build smart contract utilities and Web3 integration prototypes for internal R&D and startup consulting engagements.',
            'skills' => 'Solidity, Web3.js, Ethereum',
            'duration' => 'Project-based',
            'mode' => 'Remote',
            'budget' => '₹50,000 – ₹1,50,000',
            'client_name' => 'CYBEORCH R&D',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'wordpress-security-hardening',
            'title' => 'WordPress Security Hardening',
            'category' => 'Cybersecurity',
            'description' => 'Audit and harden SME WordPress sites: WAF rules, plugin review, backup strategy, and OWASP checklist.',
            'skills' => 'WordPress, OWASP, Linux',
            'duration' => '2–3 weeks',
            'mode' => 'Remote',
            'budget' => '₹15,000 – ₹35,000',
            'client_name' => 'SME Client',
            'status' => 'Closing Soon',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'api-integration-specialist',
            'title' => 'API Integration Specialist',
            'category' => 'Software Development',
            'description' => 'Connect Razorpay, CRM, and internal dashboards via secure REST APIs with logging and retry policies.',
            'skills' => 'PHP, REST, Webhooks',
            'duration' => '3 weeks',
            'mode' => 'Hybrid',
            'budget' => '₹20,000 – ₹45,000',
            'client_name' => 'CYBEORCH LABS',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
        ],
        [
            'slug' => 'database-security-engineer-freelance',
            'title' => 'Database Security Engineer — Protection Suite',
            'category' => 'Cybersecurity',
            'description' => 'Support enterprise database hardening with automated masking, end-to-end encryption, SQL injection monitoring, and security documentation for MySQL/PostgreSQL production stacks.',
            'skills' => 'Python, PostgreSQL, MySQL, Redis, Docker',
            'duration' => '4–6 weeks',
            'mode' => 'Remote',
            'budget' => '₹35,000 – ₹80,000',
            'client_name' => 'CYBEORCH LABS',
            'status' => 'Open',
            'apply' => 'register-freelancer.php',
            'image_path' => 'assets/images/Database Security & Cryptographic Protection Suite.png',
        ],
    ];
}

function freelanceProjectApplyUrl(array $project): string
{
    // Default to dedicated application page; admin can override per-project.
    $route = trim((string) ($project['apply'] ?? 'apply-freelance.php'));
    if ($route === '') {
        $route = 'apply-freelance.php';
    }
    if (str_contains($route, '://') || str_starts_with($route, '/')) {
        return $route;
    }
    $slug = (string) ($project['slug'] ?? '');
    $sep = str_contains($route, '?') ? '&' : '?';
    return url($route . ($slug !== '' ? $sep . 'project=' . urlencode($slug) : ''));
}

/** @param array<string, mixed> $project */
function freelanceProjectDetailUrl(array $project): string
{
    $slug = (string) ($project['slug'] ?? '');
    return $slug !== '' ? url('freelance-projects.php?slug=' . urlencode($slug)) : url('freelance-projects.php');
}

/** @param array<string, mixed> $project */
function freelanceProjectThumbPath(array $project): string
{
    $title = strtolower(trim((string) ($project['title'] ?? '')));
    $slug = strtolower(trim((string) ($project['slug'] ?? '')));
    $category = strtolower(trim((string) ($project['category'] ?? '')));
    $haystack = $slug . ' ' . $title . ' ' . $category;

    $map = [
        'ui/ux' => 'assets/images/Interactive Gaming & Engagement Engine.png',
        'design' => 'assets/images/Interactive Gaming & Engagement Engine.png',
        'mobile' => 'assets/images/MERN Full Stack Development.png',
        'flutter' => 'assets/images/MERN Full Stack Development.png',
        'react native' => 'assets/images/MERN Full Stack Development.png',
        'api' => 'assets/images/Java Full Stack Development.png',
        'php' => 'assets/images/Java Full Stack Development.png',
        'wordpress' => 'assets/images/Cyber Security.png',
        'security' => 'assets/images/Cyber Security.png',
        'cybersecurity' => 'assets/images/Cyber Security.png',
        'database security' => 'assets/images/Database Security & Cryptographic Protection Suite.png',
        'cryptographic' => 'assets/images/Database Security & Cryptographic Protection Suite.png',
        'web3' => 'assets/images/blockchain_technology.png',
        'blockchain' => 'assets/images/blockchain_technology.png',
        'solidity' => 'assets/images/blockchain_technology.png',
        'razorpay' => 'assets/images/Buy Credits for Shopping System.png',
    ];

    foreach ($map as $needle => $path) {
        if ($needle !== '' && str_contains($haystack, $needle)) {
            return $path;
        }
    }

    return 'assets/images/AI & ML.png';
}

function freelanceAssetUrl(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', ltrim($relativePath, '/'));
    $parts = array_map('rawurlencode', explode('/', $relativePath));
    return url(implode('/', $parts));
}

/** @param array<string, mixed> $project */
function freelanceProjectImageUrl(array $project): string
{
    $candidate = trim((string) ($project['image_path'] ?? ''));
    if ($candidate !== '') {
        return freelanceAssetUrl($candidate);
    }
    return freelanceAssetUrl(freelanceProjectThumbPath($project));
}
