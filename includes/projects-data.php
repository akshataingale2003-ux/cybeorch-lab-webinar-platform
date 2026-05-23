<?php
declare(strict_types=1);

/** Live project listings for the public Projects page */
function getLiveProjects(): array
{
    return [
        [
            'title'       => 'Vulnerability Assessment Platform',
            'category'    => 'Cybersecurity',
            'description' => 'Web-based VA scanner for SMEs with automated reporting, CVE mapping, and remediation guidance.',
            'stack'       => 'PHP, React, MySQL',
            'duration'    => '8 weeks',
            'team'        => '4 engineers',
            'status'      => 'In Progress',
        ],
        [
            'title'       => 'College Cyber Lab Portal',
            'category'    => 'EdTech',
            'description' => 'Hands-on lab environment for ethical hacking modules with instructor dashboards and user registration & trainee progress tracking.',
            'stack'       => 'Laravel, Vue.js',
            'duration'    => '12 weeks',
            'team'        => '6 engineers',
            'status'      => 'Open for Collaboration',
        ],
        [
            'title'       => 'Secure E-Commerce MVP',
            'category'    => 'FinTech',
            'description' => 'Payment-integrated storefront with OWASP-hardened checkout, Razorpay, and admin analytics.',
            'stack'       => 'PHP, Bootstrap, MySQL',
            'duration'    => '6 weeks',
            'team'        => '3 engineers',
            'status'      => 'Completed',
        ],
        [
            'title'       => 'SIEM Dashboard POC',
            'category'    => 'Security Ops',
            'description' => 'Real-time log ingestion dashboard with alert rules for web apps and cloud workloads.',
            'stack'       => 'Python, Elastic, React',
            'duration'    => '10 weeks',
            'team'        => '5 engineers',
            'status'      => 'In Progress',
        ],
        [
            'title'       => 'API Security Gateway',
            'category'    => 'DevSecOps',
            'description' => 'Rate limiting, JWT validation, and threat logging layer for microservice APIs.',
            'stack'       => 'Node.js, Redis, Docker',
            'duration'    => '5 weeks',
            'team'        => '3 engineers',
            'status'      => 'Open for Collaboration',
        ],
        [
            'title'       => 'IoT Device Pen-Test Toolkit',
            'category'    => 'IoT Security',
            'description' => 'Firmware analysis workflows and network fuzzing tools for connected device assessments.',
            'stack'       => 'Python, Bash',
            'duration'    => '7 weeks',
            'team'        => '4 engineers',
            'status'      => 'Planning',
        ],
    ];
}

/** CYBEORCH Studio product listings */
function getStudioProducts(): array
{
    return [
        [
            'title'       => 'NxL Wallet System',
            'category'    => 'Fintech',
            'description' => 'Token rewards, referrals, and redemption engine for education and loyalty platforms.',
            'features'    => ['Referral bonuses', 'Leaderboards', 'Admin credit tools', 'Transaction history'],
            'status'      => 'Live',
        ],
        [
            'title'       => 'CYBEORCH LMS Module',
            'category'    => 'EdTech',
            'description' => 'Webinar and bootcamp management with enrollments, payments, and certificates.',
            'features'    => ['Razorpay integration', 'Attendance tracking', 'NxL rewards', 'User Registration & Trainee dashboard'],
            'status'      => 'Live',
        ],
        [
            'title'       => 'POS & Billing Suite',
            'category'    => 'Retail',
            'description' => 'Point-of-sale with inventory, GST invoicing, and multi-branch reporting.',
            'features'    => ['Barcode billing', 'Stock alerts', 'ERP sync', 'Role-based access'],
            'status'      => 'Beta',
        ],
        [
            'title'       => 'Marketplace Platform',
            'category'    => 'E-Commerce',
            'description' => 'Multi-vendor marketplace with vendor dashboards, commissions, and secure payouts.',
            'features'    => ['Vendor onboarding', 'Order management', 'Commission rules', 'Mobile-ready UI'],
            'status'      => 'In Development',
        ],
        [
            'title'       => 'Cyber Range Simulator',
            'category'    => 'Security Training',
            'description' => 'CTF-style lab scenarios for red-team and blue-team skill development.',
            'features'    => ['Scenario builder', 'Scoring engine', 'Team rooms', 'Progress analytics'],
            'status'      => 'Planning',
        ],
        [
            'title'       => 'HR & Internship Portal',
            'category'    => 'HR Tech',
            'description' => 'Applicant tracking and internship project assignment for colleges and startups.',
            'features'    => ['Application forms', 'Mentor matching', 'Project milestones', 'Certificates'],
            'status'      => 'Beta',
        ],
    ];
}

function projectStatusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'completed', 'live' => 'badge-free',
        'in progress', 'in development' => 'badge-live',
        'open for collaboration' => 'badge-paid',
        default => 'badge-paid',
    };
}
