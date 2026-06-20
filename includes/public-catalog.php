<?php
declare(strict_types=1);

/**
 * Public-site catalog fetchers — list/detail pages use per-type filters; homepage uses show_on_homepage only.
 */

require_once __DIR__ . '/catalog-content.php';
require_once __DIR__ . '/admin-actions.php';

/** Ensure training/catalog tables and admin visibility columns (deleted_at, is_blocked) exist. */
function ensurePublicCatalogSchemas(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (function_exists('ensureSiteDatabaseSchemas')) {
        ensureSiteDatabaseSchemas();
    }
    ensureAdminActionsSchema();
}

/** Active, non-blocked rows for a catalog table. */
function publicCatalogVisibilitySql(string $table, string $alias = ''): string
{
    ensurePublicCatalogSchemas();
    $p = $alias !== '' ? rtrim($alias, '.') . '.' : '';
    $parts = [];
    if (adminTableHasColumn($table, 'deleted_at')) {
        $parts[] = '(' . $p . 'deleted_at IS NULL OR ' . $p . "deleted_at = '0000-00-00 00:00:00')";
    }
    if (adminTableHasColumn($table, 'is_blocked')) {
        $parts[] = $p . 'is_blocked = 0';
    }
    if (adminTableHasColumn($table, 'record_status')) {
        $parts[] = $p . "record_status = 'active'";
    }
    return $parts === [] ? '1=1' : implode(' AND ', $parts);
}

/** Active (non-deleted, non-blocked) webinar registration rows. */
function publicWebinarRegistrationActiveSql(string $alias = 'wr'): string
{
    return publicCatalogVisibilitySql('webinar_registrations', $alias);
}

/** Subquery: active registration count for seat availability. */
function publicWebinarRegisteredSeatsSubquery(): string
{
    $active = publicWebinarRegistrationActiveSql('wr');

    return "(SELECT COUNT(*) FROM webinar_registrations wr WHERE wr.webinar_id = w.id AND {$active})";
}

/** @return list<string> */
function publicWebinarListStatuses(): array
{
    return ['upcoming', 'live', 'completed'];
}

/** @return list<string> */
function publicBootcampListStatuses(): array
{
    return ['open', 'ongoing'];
}

/**
 * @param list<string>|null $statusFilter When set, only rows with these statuses are returned.
 * @return list<array<string, mixed>>
 */
function publicFetchWebinars(?int $limit = null, ?array $statusFilter = null): array
{
    $statuses = $statusFilter ?? publicWebinarListStatuses();
    $statuses = array_values(array_filter($statuses, static fn ($s) => is_string($s) && $s !== ''));
    if ($statuses === []) {
        return [];
    }
    $in = implode(',', array_fill(0, count($statuses), '?'));
    // IMPORTANT: Seats booked must always be derived from webinar_registrations (no caching).
    $sql = 'SELECT w.*,
            ' . publicWebinarRegisteredSeatsSubquery() . ' AS registered_seats
            FROM webinars w WHERE ' . publicCatalogVisibilitySql('webinars', 'w')
        . " AND w.status IN ({$in}) ORDER BY w.scheduled_at ASC";

    $rows = dbTry(static fn () => db()->fetchAll($sql, $statuses), null);
    if (!is_array($rows)) {
        return [];
    }

    $seenSlugs = [];
    $deduped = [];
    foreach ($rows as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '' || isset($seenSlugs[$slug])) {
            continue;
        }
        $seenSlugs[$slug] = true;
        $deduped[] = $row;
    }

    if ($limit !== null && $limit > 0) {
        return array_slice($deduped, 0, $limit);
    }

    return $deduped;
}

/**
 * Fetch webinars for a single public section (strict status match).
 *
 * @return list<array<string, mixed>>
 */
function publicFetchWebinarsByStatus(string $status, ?int $limit = null): array
{
    $status = strtolower(trim($status));
    if (!in_array($status, ['upcoming', 'live'], true)) {
        return [];
    }

    $sql = 'SELECT w.*,
            ' . publicWebinarRegisteredSeatsSubquery() . ' AS registered_seats
            FROM webinars w WHERE ' . publicCatalogVisibilitySql('webinars', 'w')
        . ' AND w.status = ? ORDER BY w.scheduled_at ASC';

    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $rows = dbTry(static fn () => db()->fetchAll($sql, [$status]), null);
    if (!is_array($rows)) {
        return [];
    }

    $seenSlugs = [];
    $deduped = [];
    foreach ($rows as $row) {
        if (strtolower((string) ($row['status'] ?? '')) !== $status) {
            continue;
        }
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '' || isset($seenSlugs[$slug])) {
            continue;
        }
        $seenSlugs[$slug] = true;
        $deduped[] = $row;
    }

    return $deduped;
}

/** Homepage Upcoming Webinars — WHERE status = 'upcoming'. */
function publicFetchUpcomingWebinars(?int $limit = null): array
{
    return publicFetchWebinarsByStatus('upcoming', $limit);
}

/** Live Webinars page — WHERE status = 'live'. */
function publicFetchLiveWebinars(?int $limit = null): array
{
    return publicFetchWebinarsByStatus('live', $limit);
}

function publicFetchWebinarById(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $statuses = publicWebinarListStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$id], $statuses);
    $row = dbTry(
        static fn () => db()->fetchOne(
            // IMPORTANT: Seats booked must always be derived from webinar_registrations (no caching).
            'SELECT w.*,
                    ' . publicWebinarRegisteredSeatsSubquery() . ' AS registered_seats
             FROM webinars w WHERE w.id = ? AND ' . publicCatalogVisibilitySql('webinars', 'w')
            . " AND w.status IN ({$in}) LIMIT 1",
            $params
        ),
        null
    );

    return is_array($row) ? $row : null;
}

function publicFetchWebinarBySlug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $statuses = publicWebinarListStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$slug], $statuses);
    $row = dbTry(
        static fn () => db()->fetchOne(
            // IMPORTANT: Seats booked must always be derived from webinar_registrations (no caching).
            'SELECT w.*,
                    ' . publicWebinarRegisteredSeatsSubquery() . ' AS registered_seats
             FROM webinars w WHERE w.slug = ? AND ' . publicCatalogVisibilitySql('webinars', 'w')
            . " AND w.status IN ({$in}) LIMIT 1",
            $params
        ),
        null
    );

    return is_array($row) ? $row : null;
}

/**
 * @return list<array<string, mixed>>
 */
function publicFetchBootcamps(?int $limit = null): array
{
    $statuses = publicBootcampListStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    // IMPORTANT: Enrollment counts must be derived from bootcamp_enrollments (no caching).
    $sql = 'SELECT b.*,
            (SELECT COUNT(*) FROM bootcamp_enrollments be WHERE be.bootcamp_id = b.id) AS enroll_count
            FROM bootcamps b WHERE ' . publicCatalogVisibilitySql('bootcamps', 'b')
        . " AND b.status IN ({$in}) ORDER BY b.start_date ASC";
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $rows = dbTry(static fn () => db()->fetchAll($sql, $statuses), null);
    return is_array($rows) ? $rows : [];
}

function publicFetchBootcampById(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $statuses = publicBootcampListStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$id], $statuses);
    $row = dbTry(
        static fn () => db()->fetchOne(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM bootcamp_enrollments be WHERE be.bootcamp_id = b.id) AS enroll_count
             FROM bootcamps b WHERE b.id = ? AND ' . publicCatalogVisibilitySql('bootcamps', 'b')
            . " AND b.status IN ({$in}) LIMIT 1",
            $params
        ),
        null
    );

    return is_array($row) ? $row : null;
}

function publicFetchBootcampBySlug(string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }
    $statuses = publicBootcampListStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $params = array_merge([$slug], $statuses);
    $row = dbTry(
        static fn () => db()->fetchOne(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM bootcamp_enrollments be WHERE be.bootcamp_id = b.id) AS enroll_count
             FROM bootcamps b WHERE b.slug = ? AND ' . publicCatalogVisibilitySql('bootcamps', 'b')
            . " AND b.status IN ({$in}) LIMIT 1",
            $params
        ),
        null
    );

    return is_array($row) ? $row : null;
}

/** @return list<array<string, mixed>> */
function publicFetchLiveProjects(): array
{
    ensurePublicCatalogSchemas();
    $rows = dbTry(static fn () => catalogGetLiveProjects(false), null);
    if (!is_array($rows)) {
        return [];
    }

    $seenSlugs = [];
    $deduped = [];
    foreach ($rows as $row) {
        $slug = trim((string) ($row['slug'] ?? ''));
        if ($slug === '' || isset($seenSlugs[$slug])) {
            continue;
        }
        $seenSlugs[$slug] = true;
        $deduped[] = $row;
    }

    return $deduped;
}

function publicFetchLiveProjectBySlug(string $slug): ?array
{
    ensurePublicCatalogSchemas();
    $row = dbTry(static fn () => catalogGetLiveProjectBySlug($slug), null);
    return is_array($row) ? $row : null;
}

/** @return list<array<string, mixed>> */
function publicFetchFreelanceProjects(): array
{
    ensurePublicCatalogSchemas();
    $rows = dbTry(static fn () => catalogGetFreelanceProjects(false), null);
    return is_array($rows) ? $rows : [];
}

function publicFetchFreelanceProjectBySlug(string $slug): ?array
{
    ensurePublicCatalogSchemas();
    $row = dbTry(static fn () => catalogGetFreelanceProjectBySlug($slug), null);
    return is_array($row) ? $row : null;
}

/** @return list<array<string, mixed>> */
function publicFetchAssignments(): array
{
    ensurePublicCatalogSchemas();
    $rows = dbTry(static fn () => catalogGetAssignments(false), null);
    return is_array($rows) ? $rows : [];
}
