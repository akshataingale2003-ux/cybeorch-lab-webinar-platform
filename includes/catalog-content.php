<?php
declare(strict_types=1);

/**
 * Live projects, hands-on projects, and freelance project listings (DB-backed).
 */

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

    // Ensure critical freelance projects exist even if table is not empty.
    catalogEnsureFreelanceProjectsExist([
        'ui-ux-designer-freelance',
        'mobile-app-developer-freelance',
        'blockchain-utility-freelance',
        'wordpress-security-hardening',
        'api-integration-specialist',
        'database-security-engineer-freelance',
    ]);

    catalogEnsureLiveProjectsExist(['database-security-cryptographic-protection-suite']);
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
function catalogGetLiveProjects(bool $adminList = false): array
{
    ensureCatalogContentSchemas();
    $where = $adminList ? adminSqlActive('lp') : catalogSqlPublic('lp');
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
    $where = $admin ? 'slug = ?' : 'slug = ? AND ' . catalogSqlPublic();
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
function catalogGetAssignments(bool $adminList = false): array
{
    ensureCatalogContentSchemas();
    $where = $adminList ? adminSqlActive('a') : catalogSqlPublic('a');
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
    $where = $admin ? 'slug = ?' : 'slug = ? AND ' . catalogSqlPublic();
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
function catalogGetFreelanceProjects(bool $adminList = false): array
{
    ensureCatalogContentSchemas();
    $where = $adminList ? adminSqlActive('fp') : catalogSqlPublic('fp');
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
    $where = $admin ? 'slug = ?' : 'slug = ? AND ' . catalogSqlPublic();
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
        'status'        => (string) ($row['status'] ?? ''),
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
        'status'      => (string) ($row['status'] ?? 'Open'),
        'apply'       => (string) ($row['apply_route'] ?? ''),
        'sort_order'  => (int) ($row['sort_order'] ?? 0),
        'is_blocked'  => (int) ($row['is_blocked'] ?? 0),
        'deleted_at'  => $row['deleted_at'] ?? null,
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
        'status'       => (string) ($row['status'] ?? 'Open'),
        'apply'        => (string) ($row['apply_route'] ?? 'register-freelancer.php'),
        'sort_order'   => (int) ($row['sort_order'] ?? 0),
        'is_blocked'   => (int) ($row['is_blocked'] ?? 0),
        'deleted_at'   => $row['deleted_at'] ?? null,
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
        'INSERT INTO live_projects (title, slug, short_desc, description, category, icon_class, card_theme, image_path, features_json, stack, duration, team_size, status, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
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
            $sortOrder,
        ]
    );
}

function catalogInsertAssignment(array $data, int $sortOrder = 0): int
{
    $slug = $data['slug'] ?? slugify((string) ($data['title'] ?? ''));
    return (int) db()->insert(
        'INSERT INTO assignments (title, slug, short_desc, description, category, type, skills, duration, mode, status, apply_route, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
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
            $sortOrder,
        ]
    );
}

function catalogInsertFreelanceProject(array $data, int $sortOrder = 0): int
{
    $slug = $data['slug'] ?? slugify((string) ($data['title'] ?? ''));
    return (int) db()->insert(
        'INSERT INTO freelance_projects (title, slug, short_desc, description, category, skills, duration, mode, budget_label, client_name, image_path, status, apply_route, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
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
    return ['Open', 'Closing Soon', 'Filled', 'Closed', 'Paused'];
}

/** @return list<array<string, mixed>> */
function catalogDefaultLiveProjects(): array
{
    return [
        [
            'slug'        => 'cyber-security',
            'title'       => 'Cyber Security',
            'category'    => 'Security',
            'short_desc'  => 'Ethical hacking, SOC workflows, and secure deployment for real-world systems.',
            'description' => 'Hands-on cyber security live projects covering vulnerability assessment, penetration testing, incident response, and security hardening for web and cloud workloads.',
            'stack'       => 'OWASP, Burp Suite, Linux, SIEM',
            'duration'    => 'Flexible cohorts',
            'team'        => 'Mentor-led squads',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-shield-halved',
            'card_theme'  => 'theme-cyber',
            'image_path'  => '',
            'features'    => [
                ['icon' => 'fa-bug', 'label' => 'Vulnerability Assessment'],
                ['icon' => 'fa-user-secret', 'label' => 'Ethical Hacking Labs'],
                ['icon' => 'fa-server', 'label' => 'SOC & Incident Response'],
                ['icon' => 'fa-lock', 'label' => 'Secure Deployment'],
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
            'image_path'  => '',
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
            'image_path'  => '',
            'features'    => [
                ['icon' => 'fa-code', 'label' => 'React SPA / SSR'],
                ['icon' => 'fa-route', 'label' => 'Express REST APIs'],
                ['icon' => 'fa-database', 'label' => 'MongoDB data modeling'],
                ['icon' => 'fa-rocket', 'label' => 'MVP to production'],
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
            'image_path'  => '',
            'features'    => [
                ['icon' => 'fa-table', 'label' => 'Data wrangling & ETL'],
                ['icon' => 'fa-chart-pie', 'label' => 'Dashboards & visualization'],
                ['icon' => 'fa-brain', 'label' => 'Predictive modeling'],
                ['icon' => 'fa-file-lines', 'label' => 'Insight reports'],
            ],
        ],
        [
            'slug'        => 'ai-and-ml',
            'title'       => 'AI & ML',
            'category'    => 'Artificial Intelligence',
            'short_desc'  => 'LLM integrations, automation, and ML prototypes for business workflows.',
            'description' => 'AI & ML live projects including chatbots, document intelligence, recommendation systems, and responsible AI guardrails for production pilots.',
            'stack'       => 'Python, TensorFlow, APIs, LLMs',
            'duration'    => '6–10 weeks',
            'team'        => '3–5 engineers',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-brain',
            'card_theme'  => 'theme-ai',
            'image_path'  => '',
            'features'    => [
                ['icon' => 'fa-robot', 'label' => 'AI assistants & chatbots'],
                ['icon' => 'fa-wand-magic-sparkles', 'label' => 'Workflow automation'],
                ['icon' => 'fa-image', 'label' => 'Vision & NLP pilots'],
                ['icon' => 'fa-scale-balanced', 'label' => 'Responsible AI practices'],
            ],
        ],
        [
            'slug'        => 'fintech',
            'title'       => 'FinTech',
            'category'    => 'Financial Technology',
            'short_desc'  => 'Payments, blockchain, wallets, and the CYBEORCH NxL credits ecosystem.',
            'description' => 'FinTech live project track — secure payment flows, blockchain utilities, wallet systems, database hardening, and CYBEORCH NxL credits for shopping, cafe redemption, and gaming.',
            'stack'       => 'Payments, Blockchain, PHP, Razorpay',
            'duration'    => 'Ongoing platform',
            'team'        => 'Cross-functional squad',
            'status'      => 'In Progress',
            'icon_class'  => 'fa-coins',
            'card_theme'  => 'theme-fintech',
            'image_path'  => '',
            'features'    => [
                ['icon' => 'fa-credit-card', 'label' => 'Payment Gateway Integration'],
                ['icon' => 'fa-link', 'label' => 'Blockchain Technology'],
                ['icon' => 'fa-coins', 'label' => 'Token Creation System'],
                ['icon' => 'fa-wallet', 'label' => 'Wallet Creation'],
                ['icon' => 'fa-shield-halved', 'label' => 'Wallet Security'],
                ['icon' => 'fa-database', 'label' => 'Database Security'],
                ['icon' => 'fa-star', 'label' => 'Own NXL Credits Token System'],
                ['icon' => 'fa-cart-shopping', 'label' => 'Buy Credits Feature'],
                ['icon' => 'fa-bag-shopping', 'label' => 'Use Credits for Shopping'],
                ['icon' => 'fa-mug-hot', 'label' => 'Redeem Credits at Cafe'],
                ['icon' => 'fa-gamepad', 'label' => 'Credits for Gaming/Playing'],
            ],
        ],
        [
            'slug'        => 'database-security-cryptographic-protection-suite',
            'title'       => 'Database Security & Cryptographic Protection Suite',
            'category'    => 'Cybersecurity & Infrastructure',
            'short_desc'  => 'An enterprise-grade database hardening system featuring automated masking, end-to-end encryption, and real-time SQL injection defense.',
            'description' => 'An enterprise-grade database hardening system featuring automated masking, end-to-end encryption, and real-time SQL injection defense.',
            'stack'       => 'Python, PostgreSQL, MySQL, Redis, Vault (HashiCorp), Wireshark, Docker',
            'duration'    => '',
            'team'        => '',
            'status'      => 'Open for Collaboration',
            'icon_class'  => 'fa-database',
            'card_theme'  => 'theme-cyber',
            'image_path'  => 'assets/images/Database Security & Cryptographic Protection Suite.png',
            'features'    => [
                ['icon' => 'fa-mask', 'label' => 'Automated data masking'],
                ['icon' => 'fa-lock', 'label' => 'End-to-end encryption'],
                ['icon' => 'fa-shield-virus', 'label' => 'SQL injection defense'],
                ['icon' => 'fa-server', 'label' => 'Enterprise database hardening'],
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
            'client_name' => 'CYBEORCH LAB',
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
            'client_name' => 'CYBEORCH LAB',
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
