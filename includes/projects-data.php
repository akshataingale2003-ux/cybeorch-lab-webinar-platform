<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog-content.php';
require_once __DIR__ . '/public-catalog.php';

/** Live project listings for the public Projects page */
function getLiveProjects(): array
{
    return publicFetchLiveProjects();
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
            'category'    => 'Security Software Development Company',
            'description' => 'CTF-style lab scenarios for red-team and blue-team skill development.',
            'features'    => ['Scenario builder', 'Scoring engine', 'Team rooms', 'Progress analytics'],
            'status'      => 'Planning',
        ],
        [
            'title'       => 'HR & Internship Portal',
            'category'    => 'HR Tech',
            'description' => 'Applicant tracking and internship project matching for colleges and startups.',
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

function getLiveProjectBySlug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    return publicFetchLiveProjectBySlug($slug);
}

/** @param array<string, mixed> $project */
function liveProjectDetailUrl(array $project): string
{
    $slug = (string) ($project['slug'] ?? '');
    return $slug !== '' ? url('live-projects.php?slug=' . urlencode($slug)) : url('live-projects.php');
}

function liveProjectIconClasses(string $icon): string
{
    $icon = trim($icon);
    if ($icon === '') {
        return 'fas fa-code-branch';
    }
    if (str_contains($icon, 'fa-brands') || str_contains($icon, 'fab ') || str_contains($icon, 'fas ') || str_contains($icon, 'far ')) {
        return $icon;
    }
    if (str_starts_with($icon, 'fa-')) {
        return 'fas ' . $icon;
    }
    return 'fas fa-' . ltrim($icon, 'fa-');
}
