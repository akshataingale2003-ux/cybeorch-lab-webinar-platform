<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function companyUploadsDir(): string
{
    return rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company';
}

function ensureCompanyUploadsDir(): void
{
    $dir = companyUploadsDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|phps)$\">\n  Require all denied\n</FilesMatch>\n");
    }
}

function ensureCompanyContentSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ensureCompanyUploadsDir();

    db()->execute('CREATE TABLE IF NOT EXISTS company_profile_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
        gst_number VARCHAR(50) DEFAULT NULL,
        cin_number VARCHAR(50) DEFAULT NULL,
        profile_pdf_path VARCHAR(500) DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS corporate_services (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(220) NOT NULL,
        short_desc TEXT,
        description TEXT,
        icon_class VARCHAR(80) DEFAULT "fa-code",
        image_path VARCHAR(500) DEFAULT NULL,
        features_json TEXT,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_corporate_services_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS portfolio_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(220) NOT NULL,
        short_desc TEXT,
        description TEXT,
        tech_stack VARCHAR(500) DEFAULT NULL,
        image_path VARCHAR(500) DEFAULT NULL,
        highlights_json TEXT,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_portfolio_items_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    try {
        if (db()->fetchOne("SHOW COLUMNS FROM portfolio_items LIKE 'icon_class'") === null) {
            db()->execute(
                'ALTER TABLE portfolio_items ADD COLUMN icon_class VARCHAR(80) DEFAULT NULL AFTER tech_stack'
            );
        }
    } catch (Throwable $e) {
        // column may already exist
    }

    db()->execute('CREATE TABLE IF NOT EXISTS case_studies (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        slug VARCHAR(220) NOT NULL,
        client_name VARCHAR(200) DEFAULT NULL,
        summary TEXT,
        projects_delivered TEXT,
        client_outcomes TEXT,
        before_after_metrics TEXT,
        performance_improvements TEXT,
        success_story TEXT,
        results_achievements TEXT,
        cover_image_path VARCHAR(500) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_case_studies_slug (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS case_study_screenshots (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        case_study_id INT UNSIGNED NOT NULL,
        image_path VARCHAR(500) NOT NULL,
        caption VARCHAR(255) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_case_study_screenshots_case (case_study_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS company_team_members (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        role_title VARCHAR(150) DEFAULT NULL,
        degree VARCHAR(150) DEFAULT NULL,
        profile_type ENUM("founder","team") NOT NULL DEFAULT "team",
        bio TEXT,
        photo_path VARCHAR(500) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    try {
        if (db()->fetchOne("SHOW COLUMNS FROM company_team_members LIKE 'degree'") === null) {
            db()->execute(
                'ALTER TABLE company_team_members ADD COLUMN degree VARCHAR(150) DEFAULT NULL AFTER role_title'
            );
        }
    } catch (Throwable $e) {
        // column may already exist
    }

    db()->execute('CREATE TABLE IF NOT EXISTS company_certifications (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        issuer VARCHAR(200) DEFAULT NULL,
        image_path VARCHAR(500) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS company_awards (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        award_year VARCHAR(20) DEFAULT NULL,
        description TEXT,
        image_path VARCHAR(500) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    db()->execute('CREATE TABLE IF NOT EXISTS company_trust_badges (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        image_path VARCHAR(500) DEFAULT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    seedCompanyContentDefaults();
}

function seedCompanyContentDefaults(): void
{
    if (!db()->fetchOne('SELECT id FROM company_profile_settings WHERE id = 1')) {
        db()->execute(
            'INSERT INTO company_profile_settings (id, gst_number, cin_number) VALUES (1, ?, ?)',
            ['27AABCU9603R1ZM', 'U62099MH2024PTC123456']
        );
    }

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM corporate_services')['c'] === 0) {
        $services = [
            ['Custom Software Development', 'fa-laptop-code', 'End-to-end product engineering for web, cloud, and enterprise workflows.'],
            ['Mobile App Development', 'fa-mobile-screen', 'Native and cross-platform apps with secure APIs and polished UX.'],
            ['SaaS Development', 'fa-cloud', 'Multi-tenant SaaS platforms with billing, RBAC, and scalable architecture.'],
            ['API Development', 'fa-plug', 'RESTful and event-driven APIs with documentation, versioning, and observability.'],
            ['AI Solutions', 'fa-robot', 'Automation, copilots, and ML pipelines integrated into business operations.'],
            ['Cybersecurity Services', 'fa-shield-halved', 'Assessments, hardening, monitoring, and secure SDLC practices.'],
            ['Cloud Solutions', 'fa-server', 'Migration, DevOps, and managed cloud infrastructure on AWS and beyond.'],
        ];
        $order = 0;
        foreach ($services as [$title, $icon, $desc]) {
            db()->execute(
                'INSERT INTO corporate_services (title, slug, short_desc, description, icon_class, sort_order) VALUES (?,?,?,?,?,?)',
                [$title, slugify($title), $desc, $desc, $icon, ++$order]
            );
        }
    }

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM portfolio_items')['c'] === 0) {
        $items = [
            ['Nexora', 'Unified digital platform for operations, analytics, and customer engagement.', 'fa-layer-group'],
            ['NXL Credit Engine', 'Tokenized credits, wallet flows, and redemption logic for loyalty ecosystems.', 'fa-coins'],
            ['Blockchain', 'We develop secure and scalable blockchain solutions, smart contracts, decentralized applications (DApps), crypto wallet integrations, NFT platforms, and enterprise blockchain systems.', 'fa-cubes'],
            ['Energeia Ecosystem', 'EV and energy operations stack with partner integrations and fleet visibility.', 'fa-bolt'],
            ['Event Management Platform', 'Registration, ticketing, live streaming, and sponsor management for hybrid events.', 'fa-calendar-days'],
            ['E-commerce Platforms', 'Multi-vendor storefronts with catalog, checkout, and fulfillment orchestration.', 'fa-cart-shopping'],
        ];
        $order = 0;
        foreach ($items as [$title, $desc, $icon]) {
            db()->execute(
                'INSERT INTO portfolio_items (title, slug, short_desc, description, icon_class, sort_order) VALUES (?,?,?,?,?,?)',
                [$title, slugify($title), $desc, $desc, $icon, ++$order]
            );
        }
    }

    ensureBlockchainPortfolioItem();

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM case_studies')['c'] === 0) {
        db()->execute(
            'INSERT INTO case_studies (title, slug, client_name, summary, projects_delivered, client_outcomes, before_after_metrics, performance_improvements, success_story, results_achievements, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                'FinTech Wallet Modernization',
                'fintech-wallet-modernization',
                'Regional Payments Partner',
                'Rebuilt a legacy wallet stack into a secure, API-first platform with faster settlement and improved user retention.',
                "Payment gateway integration\nWallet ledger & reconciliation\nAdmin operations console\nFraud-aware transaction rules",
                "40% faster settlement cycles\nImproved customer support resolution time\nHigher repeat transaction rate",
                "Transaction success rate: 91% → 98%\nSupport tickets: 320/mo → 140/mo\nDeployment frequency: monthly → weekly",
                "Reduced API latency by 55%\nCut infrastructure costs by 22%\nAchieved 99.9% uptime on core services",
                'The client needed to scale without rewriting everything at once. CYBEORCH delivered phased migrations, strong test coverage, and a rollout plan that kept production stable throughout.',
                "Delivered in 16 weeks across 3 release trains\nZero critical incidents during go-live\nClient NPS improved from 6.8 to 8.9",
                1,
            ]
        );
        $caseId = (int) db()->lastInsertId();
        if ($caseId > 0) {
            db()->execute(
                'INSERT INTO case_study_screenshots (case_study_id, image_path, caption, sort_order) VALUES (?,?,?,?)',
                [$caseId, 'assets/images/live_projects/FinTech.png', 'Dashboard overview', 1]
            );
        }
    }

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM company_team_members')['c'] === 0) {
        db()->execute(
            'INSERT INTO company_team_members (name, role_title, profile_type, bio, sort_order) VALUES (?,?,?,?,?)',
            [
                'Abhijeet Patil',
                'Founder & CEO',
                'founder',
                'Technology entrepreneur focused on cybersecurity, product engineering, and building industry-ready talent through practical execution.',
                1,
            ]
        );
    }

    removeLegacyEngineeringTeamPlaceholder();
    removeSoftwareDevelopmentExcellenceCert();

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM company_certifications')['c'] === 0) {
        db()->execute('INSERT INTO company_certifications (title, issuer, sort_order) VALUES (?,?,?)', ['ISO 27001 Aligned Practices', 'Information Security', 1]);
        db()->execute('INSERT INTO company_certifications (title, issuer, sort_order) VALUES (?,?,?)', ['AWS Cloud Practitioner Team', 'Amazon Web Services', 2]);
    }

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM company_awards')['c'] === 0) {
        db()->execute('INSERT INTO company_awards (title, award_year, description, sort_order) VALUES (?,?,?,?)', [
            'Emerging Tech Innovation',
            '2025',
            'Recognized for practical cybersecurity training and live project delivery models.',
            1,
        ]);
    }

    if ((int) db()->fetchOne('SELECT COUNT(*) AS c FROM company_trust_badges')['c'] === 0) {
        $badges = ['Enterprise Ready', 'Secure SDLC', 'Startup Friendly', '24/7 Support'];
        $order = 0;
        foreach ($badges as $badge) {
            db()->execute('INSERT INTO company_trust_badges (title, sort_order) VALUES (?,?)', [$badge, ++$order]);
        }
    }

    ensureAkshataIngaleTeamMember();
}

function blockchainPortfolioDescription(): string
{
    return 'We develop secure and scalable blockchain solutions, smart contracts, decentralized applications (DApps), crypto wallet integrations, NFT platforms, and enterprise blockchain systems.';
}

function ensureBlockchainPortfolioItem(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (db()->fetchOne('SELECT id FROM portfolio_items WHERE slug = ? LIMIT 1', ['blockchain'])) {
        return;
    }

    $desc = blockchainPortfolioDescription();
    $nxl = db()->fetchOne('SELECT sort_order FROM portfolio_items WHERE slug = ? LIMIT 1', ['nxl-credit-engine']);
    $sortOrder = $nxl ? (int) $nxl['sort_order'] + 1 : 3;

    db()->execute(
        'UPDATE portfolio_items SET sort_order = sort_order + 1 WHERE sort_order >= ?',
        [$sortOrder]
    );

    db()->execute(
        'INSERT INTO portfolio_items (title, slug, short_desc, description, icon_class, sort_order, is_active) VALUES (?,?,?,?,?,?,1)',
        ['Blockchain', 'blockchain', $desc, $desc, 'fa-cubes', $sortOrder]
    );
}

function portfolioItemIconClass(array $item): string
{
    $icon = trim((string) ($item['icon_class'] ?? ''));
    if ($icon !== '') {
        return $icon;
    }

    return 'fa-layer-group';
}

function removeLegacyEngineeringTeamPlaceholder(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute(
        'DELETE FROM company_team_members WHERE name = ? AND profile_type = ?',
        ['CYBEORCH Engineering Team', 'team']
    );
}

function removeSoftwareDevelopmentExcellenceCert(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute(
        'DELETE FROM company_certifications WHERE title = ?',
        ['Software Development Excellence']
    );
}

function akshataIngaleTeamBio(): string
{
    return 'Akshata Ingale is a passionate Software Developer focused on building modern, scalable, and user-friendly applications. She enjoys solving complex problems, learning new technologies, and developing innovative software solutions that create real-world impact.';
}

function akshataIngaleTeamPhotoPath(): string
{
    return 'assets/images/Team_Profile/Akshata_Ingale.jpeg';
}

/** @return list<array{name: string, role_title: string, degree: string, bio: string, photo_path: string|null, sort_order: int}> */
function defaultTeamProfileSeedData(): array
{
    return [
        [
            'name'       => 'Akshata Ingale',
            'role_title' => 'Software Developer',
            'degree'     => 'B.Tech',
            'bio'        => akshataIngaleTeamBio(),
            'photo_path' => akshataIngaleTeamPhotoPath(),
            'sort_order' => 1,
        ],
        [
            'name'       => 'Priya Sharma',
            'role_title' => 'UI/UX Designer',
            'degree'     => 'B.Sc',
            'bio'        => 'Priya Sharma crafts intuitive digital experiences with a focus on accessibility and clean visual design. She collaborates closely with engineering teams to turn product ideas into polished, user-centered interfaces.',
            'photo_path' => null,
            'sort_order' => 2,
        ],
        [
            'name'       => 'Rohan Deshmukh',
            'role_title' => 'DevOps Engineer',
            'degree'     => 'B.Tech',
            'bio'        => 'Rohan Deshmukh builds reliable CI/CD pipelines and cloud infrastructure that keep applications secure and scalable. He is passionate about automation, monitoring, and helping teams ship faster with confidence.',
            'photo_path' => null,
            'sort_order' => 3,
        ],
        [
            'name'       => 'Sneha Kulkarni',
            'role_title' => 'Cybersecurity Analyst',
            'degree'     => 'M.Tech',
            'bio'        => 'Sneha Kulkarni specializes in threat detection, vulnerability assessment, and security best practices for web and cloud systems. She helps teams build resilient products through proactive security reviews and awareness.',
            'photo_path' => null,
            'sort_order' => 4,
        ],
        [
            'name'       => 'Varun Mehta',
            'role_title' => 'Full Stack Developer',
            'degree'     => 'B.Tech',
            'bio'        => 'Varun Mehta develops end-to-end web applications using modern JavaScript and PHP stacks. He enjoys architecting APIs, optimizing performance, and delivering features that solve real business problems.',
            'photo_path' => null,
            'sort_order' => 5,
        ],
    ];
}

function teamProfilePhotoPathIfExists(?string $photoPath): ?string
{
    $photoPath = trim((string) $photoPath);
    if ($photoPath === '') {
        return null;
    }
    $fullPath = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photoPath);

    return is_file($fullPath) ? $photoPath : null;
}

function ensureDefaultTeamProfiles(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    foreach (defaultTeamProfileSeedData() as $member) {
        $existing = db()->fetchOne(
            'SELECT id FROM company_team_members WHERE name = ? AND profile_type = ? LIMIT 1',
            [$member['name'], 'team']
        );
        if ($existing) {
            db()->execute(
                'UPDATE company_team_members SET sort_order = ?, degree = ? WHERE id = ?',
                [$member['sort_order'], $member['degree'], (int) $existing['id']]
            );
            continue;
        }

        db()->execute(
            'INSERT INTO company_team_members (name, role_title, degree, profile_type, bio, photo_path, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)',
            [
                $member['name'],
                $member['role_title'],
                $member['degree'],
                'team',
                $member['bio'],
                teamProfilePhotoPathIfExists($member['photo_path']),
                $member['sort_order'],
                1,
            ]
        );
    }
}

function ensureAkshataIngaleTeamMember(): void
{
    ensureDefaultTeamProfiles();
}

/** @return array<string, mixed> */
function getCompanyProfileSettings(): array
{
    ensureCompanyContentSchema();
    $row = dbTry(fn () => db()->fetchOne('SELECT * FROM company_profile_settings WHERE id = 1'), null);

    return is_array($row) ? $row : ['gst_number' => '', 'cin_number' => '', 'profile_pdf_path' => ''];
}

/**
 * Whether GST/CIN numbers render on public marketing pages (About Us, etc.).
 * Admin panel and database storage are unaffected.
 * Re-enable later: set SHOW_COMPANY_GST_CIN_FRONTEND=1 in .env, or return true below.
 */
function showCompanyGstCinOnPublicSite(): bool
{
    if (function_exists('cybeorchEnv')) {
        $flag = strtolower(trim(cybeorchEnv('SHOW_COMPANY_GST_CIN_FRONTEND', '0')));

        return in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    return false;
}

/** @param array<string, mixed> $input */
function saveCompanyProfileSettings(array $input): array
{
    ensureCompanyContentSchema();
    $gst = trim((string) ($input['gst_number'] ?? ''));
    $cin = trim((string) ($input['cin_number'] ?? ''));
    db()->execute(
        'INSERT INTO company_profile_settings (id, gst_number, cin_number) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE gst_number = VALUES(gst_number), cin_number = VALUES(cin_number), updated_at = NOW()',
        [$gst, $cin]
    );

    return ['success' => true, 'message' => 'Company trust details saved.'];
}

/** @return list<array<string, mixed>> */
function publicFetchCorporateServices(): array
{
    ensureCompanyContentSchema();

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM corporate_services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

/** @return list<array<string, mixed>> */
function publicFetchPortfolioItems(): array
{
    ensureCompanyContentSchema();

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM portfolio_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

/** @return list<array<string, mixed>> */
function publicFetchCaseStudies(): array
{
    ensureCompanyContentSchema();

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM case_studies WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

/** @return array<string, mixed>|null */
function publicFetchCaseStudyBySlug(string $slug): ?array
{
    ensureCompanyContentSchema();
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $row = dbTry(fn () => db()->fetchOne('SELECT * FROM case_studies WHERE slug = ? AND is_active = 1 LIMIT 1', [$slug]), null);
    if (!is_array($row)) {
        return null;
    }
    $row['screenshots'] = dbTry(
        fn () => db()->fetchAll('SELECT * FROM case_study_screenshots WHERE case_study_id = ? ORDER BY sort_order ASC, id ASC', [(int) $row['id']]),
        []
    );

    return $row;
}

/** @return list<array<string, mixed>> */
function publicFetchTeamMembers(string $type = ''): array
{
    ensureCompanyContentSchema();
    if ($type === 'founder' || $type === 'team') {
        return dbTry(
            fn () => db()->fetchAll('SELECT * FROM company_team_members WHERE is_active = 1 AND profile_type = ? ORDER BY sort_order ASC, id ASC', [$type]),
            []
        );
    }

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM company_team_members WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

/** @return list<array<string, mixed>> */
function publicFetchCertifications(): array
{
    ensureCompanyContentSchema();

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM company_certifications WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

/** @return list<array<string, mixed>> */
function publicFetchAwards(): array
{
    ensureCompanyContentSchema();

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM company_awards WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

/** @return list<array<string, mixed>> */
function publicFetchTrustBadges(): array
{
    ensureCompanyContentSchema();

    return dbTry(
        fn () => db()->fetchAll('SELECT * FROM company_trust_badges WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'),
        []
    );
}

function companyPublicImageUrl(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return url(ltrim(str_replace('\\', '/', $path), '/'));
}

/** @return list<string> */
function companyLinesToList(?string $text): array
{
    $lines = preg_split('/\r\n|\r|\n/', (string) $text) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }

    return $out;
}

/**
 * @param array<string, mixed> $file
 * @return array{ok: bool, path: string, error: string}
 */
function companyStoreUpload(array $file, string $prefix = 'asset'): array
{
    ensureCompanyUploadsDir();
    $fail = static fn (string $msg): array => ['ok' => false, 'path' => '', 'error' => $msg];

    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fail('Upload failed. Please try again.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_SIZE) {
        return $fail('Invalid file size.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid upload.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        return $fail('Allowed types: JPG, PNG, WEBP, GIF.');
    }

    $safe = preg_replace('/[^a-z0-9_-]+/i', '-', $prefix) ?: 'asset';
    $filename = $safe . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = companyUploadsDir() . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return $fail('Could not save uploaded file.');
    }

    return ['ok' => true, 'path' => 'uploads/company/' . $filename, 'error' => ''];
}

/**
 * @param array<string, mixed> $file
 * @return array{ok: bool, path: string, error: string}
 */
function companyStorePdfUpload(array $file): array
{
    ensureCompanyUploadsDir();
    $fail = static fn (string $msg): array => ['ok' => false, 'path' => '', 'error' => $msg];

    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return $fail('PDF upload failed. Please try again.');
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_SIZE) {
        return $fail('Invalid PDF file size.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('Invalid PDF upload.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return $fail('Only PDF files are allowed.');
    }

    $filename = 'company-profile-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    $dest = companyUploadsDir() . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        return $fail('Could not save the PDF file.');
    }

    return ['ok' => true, 'path' => 'uploads/company/' . $filename, 'error' => ''];
}

function companyProfilePdfPath(): ?string
{
    $settings = getCompanyProfileSettings();
    $path = trim((string) ($settings['profile_pdf_path'] ?? ''));
    if ($path === '') {
        return null;
    }

    $full = rtrim(CYBEORCH_APP_ROOT, '\\/') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

    return is_file($full) ? $path : null;
}

function companyProfileDownloadUrl(): string
{
    return url('company-profile.php');
}

function companyProfilePdfAvailable(): bool
{
    return companyProfilePdfPath() !== null;
}
