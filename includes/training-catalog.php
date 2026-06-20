<?php
declare(strict_types=1);

require_once __DIR__ . '/program-pricing.php';

/**
 * Ensures default bootcamps & webinars exist (by slug) for catalog pages.
 */
function ensureTrainingCatalog(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        ensureBootcampPricingSchema();

        $tier399 = cybeorchBootcampTier399();
        $tier499 = cybeorchBootcampTier499();

        $bootcamps = [
            [
                'title' => 'Cyber Security Bootcamp',
                'slug' => 'cyber-security-bootcamp',
                'description' => 'Comprehensive cybersecurity software development company: network defense, ethical hacking fundamentals, SOC workflows, cloud security, and incident response with hands-on labs and CTF challenges.',
                'short_desc' => '4-week cyber security bootcamp with live labs, mentorship, and certification prep.',
                'instructor' => 'Kanchan',
                'category' => 'Cyber Security',
                'start_offset' => 14,
                'duration_weeks' => 4,
                'total_seats' => 32,
                'fee_inr' => $tier399['fee_inr'],
            ],
            [
                'title' => 'Web Application Security Bootcamp',
                'slug' => 'web-app-security-bootcamp',
                'description' => 'Learn to identify and exploit web vulnerabilities: OWASP Top 10, SQL injection, XSS, CSRF and more.',
                'short_desc' => '3-week web security bootcamp with live practice labs.',
                'instructor' => 'Kanchan',
                'category' => 'Web Security',
                'start_offset' => 20,
                'duration_weeks' => 3,
                'total_seats' => 25,
                'fee_inr' => $tier399['fee_inr'],
            ],
            [
                'title' => 'Blockchain Development',
                'slug' => 'blockchain-development-bootcamp',
                'description' => 'Build decentralized applications with smart contracts, wallets, and Web3 integrations—from fundamentals to deployment on testnets.',
                'short_desc' => 'Hands-on blockchain & smart contract bootcamp with real project labs.',
                'instructor' => 'Monali Patil',
                'category' => 'Blockchain',
                'start_offset' => 25,
                'duration_weeks' => 4,
                'total_seats' => 28,
                'fee_inr' => $tier399['fee_inr'],
            ],
            [
                'title' => 'Mobile Application Security Bootcamp',
                'slug' => 'mobile-app-security-bootcamp',
                'description' => 'Secure Android and iOS apps: OWASP MASVS, reverse engineering basics, API hardening, and mobile pentesting workflows.',
                'short_desc' => '4-week mobile app security bootcamp with device labs & assessments.',
                'instructor' => 'Kanchan',
                'category' => 'Mobile Security',
                'start_offset' => 18,
                'duration_weeks' => 4,
                'total_seats' => 24,
                'fee_inr' => $tier399['fee_inr'],
            ],
            [
                'title' => 'Full Stack Development Bootcamp',
                'slug' => 'full-stack-development-bootcamp',
                'description' => 'End-to-end web development: React/Next.js frontends, Node.js APIs, databases, auth, and deployment pipelines.',
                'short_desc' => '6-week intensive full stack program with capstone project.',
                'instructor' => 'Kalpesh Patil',
                'category' => 'Full Stack',
                'start_offset' => 30,
                'duration_weeks' => 6,
                'total_seats' => 35,
                'fee_inr' => $tier499['fee_inr'],
            ],
            [
                'title' => 'DevOps Bootcamp',
                'slug' => 'devops-bootcamp',
                'description' => 'Master CI/CD pipelines, Docker, Kubernetes, infrastructure as code, and cloud deployment workflows with hands-on labs.',
                'short_desc' => '4-week DevOps bootcamp covering Docker, K8s, and automation pipelines.',
                'instructor' => 'Akshata Ingale',
                'category' => 'DevOps',
                'start_offset' => 22,
                'duration_weeks' => 4,
                'total_seats' => 30,
                'fee_inr' => $tier399['fee_inr'],
            ],
        ];

        foreach ($bootcamps as $b) {
            $exists = db()->fetchOne('SELECT id FROM bootcamps WHERE slug = ?', [$b['slug']]);
            if ($exists) {
                continue;
            }
            $start = date('Y-m-d', strtotime('+' . (int) $b['start_offset'] . ' days'));
            $end = date('Y-m-d', strtotime($start . ' +' . ((int) $b['duration_weeks'] * 7 - 1) . ' days'));
            $fees = bootcampPrepareFeeSave((float) ($b['fee_inr'] ?? 0));
            db()->execute(
                'INSERT INTO bootcamps (title, slug, description, short_desc, instructor, category, start_date, end_date, duration_weeks, total_seats, original_fee, discounted_fee, fee_usd, certificate, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $b['title'], $b['slug'], $b['description'], $b['short_desc'], $b['instructor'], $b['category'],
                    $start, $end, $b['duration_weeks'], $b['total_seats'], $fees['original_fee'], $fees['discounted_fee'], $fees['fee_usd'], 1, 'open',
                ]
            );
        }

        syncUpcomingWebinarCatalog();
        syncLiveWebinarCatalog();
        syncCybeorchProgramPathBootcamps();
    } catch (Throwable $e) {
        // DB offline — pages use dbTry fallbacks
    }
}

/**
 * Canonical upcoming webinars shown on homepage and webinars listing.
 *
 * @return list<array<string, mixed>>
 */
function upcomingWebinarCatalogDefinitions(): array
{
    return [
        [
            'title'        => 'Cybersecurity',
            'slug'         => 'cybersecurity',
            'description'  => 'Learn cybersecurity fundamentals, threat detection, network security, and best practices.',
            'short_desc'   => 'Learn cybersecurity fundamentals, threat detection, network security, and best practices.',
            'instructor'   => 'Kalpesh Patil',
            'category'     => 'Cybersecurity',
            'scheduled_at' => '2026-06-07 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 200,
            'fee'          => 0.00,
            'is_free'      => 1,
        ],
        [
            'title'        => 'AI / ML',
            'slug'         => 'ai-ml',
            'description'  => 'Learn Artificial Intelligence, Machine Learning, automation, and real-world applications.',
            'short_desc'   => 'Learn Artificial Intelligence, Machine Learning, automation, and real-world applications.',
            'instructor'   => 'Kanchan',
            'category'     => 'AI / ML',
            'scheduled_at' => '2026-06-14 11:00:00',
            'duration_mins'=> 120,
            'max_seats'    => 150,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
        [
            'title'        => 'Blockchain',
            'slug'         => 'blockchain',
            'description'  => 'Understand blockchain technology, smart contracts, Web3, and decentralized systems.',
            'short_desc'   => 'Understand blockchain technology, smart contracts, Web3, and decentralized systems.',
            'instructor'   => 'Monali Patil',
            'category'     => 'Blockchain',
            'scheduled_at' => '2026-06-21 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 180,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
        [
            'title'        => 'DevOps',
            'slug'         => 'devops',
            'description'  => 'Learn CI/CD, Docker, Kubernetes, Infrastructure as Code, and DevOps best practices.',
            'short_desc'   => 'Learn CI/CD, Docker, Kubernetes, Infrastructure as Code, and DevOps best practices.',
            'instructor'   => 'Akshata Ingale',
            'category'     => 'DevOps',
            'scheduled_at' => '2026-06-28 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 100,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
    ];
}

/** Seed default upcoming webinars when missing. Never cancels or overwrites admin-created rows. */
function syncUpcomingWebinarCatalog(): void
{
    foreach (upcomingWebinarCatalogDefinitions() as $w) {
        $exists = db()->fetchOne('SELECT id FROM webinars WHERE slug = ? LIMIT 1', [$w['slug']]);
        if ($exists) {
            continue;
        }

        db()->execute(
            'INSERT INTO webinars (title, slug, description, short_desc, instructor, category, scheduled_at, duration_mins, max_seats, fee, is_free, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $w['title'], $w['slug'], $w['description'], $w['short_desc'], $w['instructor'], $w['category'],
                $w['scheduled_at'], $w['duration_mins'], $w['max_seats'], $w['fee'], $w['is_free'], 'upcoming',
            ]
        );
    }
}

/**
 * Canonical live webinars shown on the Live Webinars page (assets/images/live_webinars/).
 *
 * @return list<array<string, mixed>>
 */
function liveWebinarCatalogDefinitions(): array
{
    return [
        [
            'title'        => 'Cybersecurity Fundamentals for Beginners',
            'slug'         => 'cybersec-fundamentals',
            'description'  => 'Foundational cybersecurity concepts, threat landscapes, and practical awareness for beginners.',
            'short_desc'   => 'Foundational cybersecurity concepts, threat landscapes, and practical awareness for beginners.',
            'instructor'   => 'Kalpesh Patil',
            'category'     => 'Cybersecurity',
            'scheduled_at' => '2026-07-05 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 200,
            'fee'          => 0.00,
            'is_free'      => 1,
        ],
        [
            'title'        => 'Ethical Hacking & Penetration Testing',
            'slug'         => 'ethical-hacking-pentest',
            'description'  => 'Hands-on ethical hacking methodology, reconnaissance, exploitation basics, and reporting.',
            'short_desc'   => 'Hands-on ethical hacking methodology, reconnaissance, exploitation basics, and reporting.',
            'instructor'   => 'Prakash',
            'category'     => 'Ethical Hacking',
            'scheduled_at' => '2026-07-12 11:00:00',
            'duration_mins'=> 120,
            'max_seats'    => 150,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
        [
            'title'        => 'AI Tools & Automation Webinar',
            'slug'         => 'ai-tools-automation-webinar',
            'description'  => 'Practical AI tools, workflow automation, and productivity patterns for modern teams.',
            'short_desc'   => 'Practical AI tools, workflow automation, and productivity patterns for modern teams.',
            'instructor'   => 'Kanchan',
            'category'     => 'AI / Automation',
            'scheduled_at' => '2026-07-19 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 180,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
        [
            'title'        => 'Cloud Security & AWS Essentials',
            'slug'         => 'cloud-security-aws',
            'description'  => 'Cloud security fundamentals, AWS shared responsibility, IAM hardening, and monitoring.',
            'short_desc'   => 'Cloud security fundamentals, AWS shared responsibility, IAM hardening, and monitoring.',
            'instructor'   => 'Monali Patil',
            'category'     => 'Cloud Security',
            'scheduled_at' => '2026-07-26 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 160,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
        [
            'title'        => 'Cybersecurity and Awareness Webinar',
            'slug'         => 'cybersecurity-awareness-webinar',
            'description'  => 'Security awareness training for teams: phishing, passwords, device hygiene, and incident reporting.',
            'short_desc'   => 'Security awareness training for teams: phishing, passwords, device hygiene, and incident reporting.',
            'instructor'   => 'Vaibhav',
            'category'     => 'Cybersecurity',
            'scheduled_at' => '2026-08-02 11:00:00',
            'duration_mins'=> 60,
            'max_seats'    => 250,
            'fee'          => 0.00,
            'is_free'      => 1,
        ],
        [
            'title'        => 'DevOps & Pipeline Security Webinar',
            'slug'         => 'devops-pipeline-security-webinar',
            'description'  => 'Secure CI/CD pipelines, secrets management, container security, and DevSecOps practices.',
            'short_desc'   => 'Secure CI/CD pipelines, secrets management, container security, and DevSecOps practices.',
            'instructor'   => 'Akshata Ingale',
            'category'     => 'DevOps',
            'scheduled_at' => '2026-08-09 11:00:00',
            'duration_mins'=> 90,
            'max_seats'    => 120,
            'fee'          => 4750.00,
            'is_free'      => 0,
        ],
    ];
}

/** Seed default live webinars when missing. Never cancels or overwrites admin-created rows. */
function syncLiveWebinarCatalog(): void
{
    require_once __DIR__ . '/training-public.php';

    foreach (liveWebinarCatalogDefinitions() as $w) {
        $exists = db()->fetchOne('SELECT id FROM webinars WHERE slug = ? LIMIT 1', [$w['slug']]);
        if ($exists) {
            continue;
        }

        $thumbnail = webinarLiveThumbPathForTitle((string) $w['title']);
        db()->execute(
            'INSERT INTO webinars (title, slug, description, short_desc, instructor, category, scheduled_at, duration_mins, max_seats, fee, is_free, status, thumbnail) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $w['title'], $w['slug'], $w['description'], $w['short_desc'], $w['instructor'], $w['category'],
                $w['scheduled_at'], $w['duration_mins'], $w['max_seats'], $w['fee'], $w['is_free'], 'live',
                $thumbnail,
            ]
        );
    }
}
