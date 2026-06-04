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
                'instructor' => 'Priya Nair',
                'category' => 'Cyber Security',
                'start_offset' => 14,
                'duration_weeks' => 4,
                'total_seats' => 32,
                'original_fee' => $tier399['original_fee_inr'],
                'discounted_fee' => $tier399['fee_inr'],
                'fee_usd' => $tier399['fee_usd'],
            ],
            [
                'title' => 'Web Application Security Bootcamp',
                'slug' => 'web-app-security-bootcamp',
                'description' => 'Learn to identify and exploit web vulnerabilities: OWASP Top 10, SQL injection, XSS, CSRF and more.',
                'short_desc' => '3-week web security bootcamp with live practice labs.',
                'instructor' => 'Priya Nair',
                'category' => 'Web Security',
                'start_offset' => 20,
                'duration_weeks' => 3,
                'total_seats' => 25,
                'original_fee' => $tier399['original_fee_inr'],
                'discounted_fee' => $tier399['fee_inr'],
                'fee_usd' => $tier399['fee_usd'],
            ],
            [
                'title' => 'Blockchain Development',
                'slug' => 'blockchain-development-bootcamp',
                'description' => 'Build decentralized applications with smart contracts, wallets, and Web3 integrations—from fundamentals to deployment on testnets.',
                'short_desc' => 'Hands-on blockchain & smart contract bootcamp with real project labs.',
                'instructor' => 'Amit Verma',
                'category' => 'Blockchain',
                'start_offset' => 25,
                'duration_weeks' => 4,
                'total_seats' => 28,
                'original_fee' => $tier399['original_fee_inr'],
                'discounted_fee' => $tier399['fee_inr'],
                'fee_usd' => $tier399['fee_usd'],
            ],
            [
                'title' => 'Mobile Application Security Bootcamp',
                'slug' => 'mobile-app-security-bootcamp',
                'description' => 'Secure Android and iOS apps: OWASP MASVS, reverse engineering basics, API hardening, and mobile pentesting workflows.',
                'short_desc' => '4-week mobile app security bootcamp with device labs & assessments.',
                'instructor' => 'Priya Nair',
                'category' => 'Mobile Security',
                'start_offset' => 18,
                'duration_weeks' => 4,
                'total_seats' => 24,
                'original_fee' => $tier399['original_fee_inr'],
                'discounted_fee' => $tier399['fee_inr'],
                'fee_usd' => $tier399['fee_usd'],
            ],
            [
                'title' => 'Full Stack Development Bootcamp',
                'slug' => 'full-stack-development-bootcamp',
                'description' => 'End-to-end web development: React/Next.js frontends, Node.js APIs, databases, auth, and deployment pipelines.',
                'short_desc' => '6-week intensive full stack program with capstone project.',
                'instructor' => 'Rahul Sharma',
                'category' => 'Full Stack',
                'start_offset' => 30,
                'duration_weeks' => 6,
                'total_seats' => 35,
                'original_fee' => $tier499['original_fee_inr'],
                'discounted_fee' => $tier499['fee_inr'],
                'fee_usd' => $tier499['fee_usd'],
            ],
            [
                'title' => 'DevOps Bootcamp',
                'slug' => 'devops-bootcamp',
                'description' => 'Master CI/CD pipelines, Docker, Kubernetes, infrastructure as code, and cloud deployment workflows with hands-on labs.',
                'short_desc' => '4-week DevOps bootcamp covering Docker, K8s, and automation pipelines.',
                'instructor' => 'Amit Verma',
                'category' => 'DevOps',
                'start_offset' => 22,
                'duration_weeks' => 4,
                'total_seats' => 30,
                'original_fee' => $tier399['original_fee_inr'],
                'discounted_fee' => $tier399['fee_inr'],
                'fee_usd' => $tier399['fee_usd'],
            ],
        ];

        foreach ($bootcamps as $b) {
            $exists = db()->fetchOne('SELECT id FROM bootcamps WHERE slug = ?', [$b['slug']]);
            if ($exists) {
                continue;
            }
            $start = date('Y-m-d', strtotime('+' . (int) $b['start_offset'] . ' days'));
            $end = date('Y-m-d', strtotime($start . ' +' . ((int) $b['duration_weeks'] * 7 - 1) . ' days'));
            db()->execute(
                'INSERT INTO bootcamps (title, slug, description, short_desc, instructor, category, start_date, end_date, duration_weeks, total_seats, original_fee, discounted_fee, fee_usd, certificate, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $b['title'], $b['slug'], $b['description'], $b['short_desc'], $b['instructor'], $b['category'],
                    $start, $end, $b['duration_weeks'], $b['total_seats'], $b['original_fee'], $b['discounted_fee'], $b['fee_usd'], 1, 'open',
                ]
            );
        }

        $webinars = [
            [
                'title' => 'AI Tools & Automation Webinar',
                'slug' => 'ai-tools-automation-webinar',
                'description' => 'Practical session on AI assistants, workflow automation, and integrating LLM tools safely into engineering and security workflows.',
                'short_desc' => 'Live webinar on AI tooling, automation, and responsible adoption.',
                'instructor' => 'Amit Verma',
                'category' => 'AI & Automation',
                'scheduled_at' => '2026-06-14 11:00:00',
                'duration_mins' => 90,
                'max_seats' => 180,
                'fee' => 1999.00,
                'is_free' => 0,
            ],
            [
                'title' => 'Cybersecurity and Awareness Webinar',
                'slug' => 'cybersecurity-awareness-webinar',
                'description' => 'Essential security hygiene for teams: phishing awareness, password managers, MFA, and incident reporting best practices.',
                'short_desc' => 'Free awareness webinar for user registrations & trainees, startups, and corporate teams.',
                'instructor' => 'Rahul Sharma',
                'category' => 'Cybersecurity',
                'scheduled_at' => '2026-06-28 11:00:00',
                'duration_mins' => 75,
                'max_seats' => 250,
                'fee' => 0.00,
                'is_free' => 1,
            ],
            [
                'title' => 'DevOps & Pipeline Security Webinar',
                'slug' => 'devops-pipeline-security-webinar',
                'description' => 'Secure CI/CD pipelines, container images, secrets management, and Kubernetes hardening for modern DevOps teams.',
                'short_desc' => 'Live webinar on DevOps security, Docker, and secure deployment pipelines.',
                'instructor' => 'Amit Verma',
                'category' => 'DevOps',
                'scheduled_at' => '2026-07-05 11:00:00',
                'duration_mins' => 90,
                'max_seats' => 150,
                'fee' => 1999.00,
                'is_free' => 0,
            ],
        ];

        foreach ($webinars as $w) {
            $exists = db()->fetchOne('SELECT id FROM webinars WHERE slug = ?', [$w['slug']]);
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
        $webinarSchedule = [
            'cybersec-fundamentals' => '2026-05-31 11:00:00',
            'ethical-hacking-pentest' => '2026-06-07 11:00:00',
            'ai-tools-automation-webinar' => '2026-06-14 11:00:00',
            'cloud-security-aws' => '2026-06-21 11:00:00',
            'cybersecurity-awareness-webinar' => '2026-06-28 11:00:00',
            'devops-pipeline-security-webinar' => '2026-07-05 11:00:00',
        ];
        foreach ($webinarSchedule as $slug => $scheduledAt) {
            db()->execute(
                'UPDATE webinars SET scheduled_at = ? WHERE slug = ?',
                [$scheduledAt, $slug]
            );
        }
        db()->execute(
            'UPDATE webinars SET fee = 2999.00 WHERE slug = ? AND fee < 2999',
            ['ethical-hacking-pentest']
        );
        db()->execute(
            'UPDATE webinars SET fee = 1999.00, is_free = 0 WHERE slug = ?',
            ['ai-tools-automation-webinar']
        );
        syncCybeorchCatalogBootcampPricing();
        syncCybeorchProgramPathBootcamps();
    } catch (Throwable $e) {
        // DB offline — pages use dbTry fallbacks
    }
}
