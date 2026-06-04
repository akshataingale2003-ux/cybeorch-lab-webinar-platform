<?php
declare(strict_types=1);

require_once __DIR__ . '/catalog-content.php';
require_once __DIR__ . '/public-catalog.php';

/** Public site label for the hands-on projects catalog. */
const HANDS_ON_PROJECTS_LABEL = 'Hands-on Projects';

const HANDS_ON_PROJECTS_REGISTRATION_SUBJECT = 'Hands-on Projects Registration';

/** Open hands-on project listings for the public catalog page */
function getAssignments(): array
{
    return publicFetchAssignments();
}

function getAssignmentBySlug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    ensurePublicCatalogSchemas();
    $row = dbTry(static fn () => catalogGetAssignmentBySlug($slug), null);
    return is_array($row) ? $row : null;
}

function assignmentRegisterUrl(array $assignment): string
{
    return url('assignment-register.php?assignment=' . urlencode((string) ($assignment['slug'] ?? '')));
}

function assignmentStatusBadgeClass(string $status): string
{
    return match (strtolower($status)) {
        'open' => 'badge-free',
        'closing soon' => 'badge-live',
        'filled', 'closed' => 'badge-paid',
        default => 'badge-paid',
    };
}

function assignmentTypeBadgeClass(string $type): string
{
    return match (strtolower($type)) {
        'freelance' => 'badge-paid',
        'internship' => 'badge-free',
        default => 'badge-paid',
    };
}
