<?php
declare(strict_types=1);

/**
 * Public-site catalog fetchers — same visibility rules for list pages, homepage, and detail routes.
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
    return $parts === [] ? '1=1' : implode(' AND ', $parts);
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
 * @return list<array<string, mixed>>
 */
function publicFetchWebinars(?int $limit = null): array
{
    $statuses = publicWebinarListStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    // IMPORTANT: Seats booked must always be derived from webinar_registrations (no caching).
    $sql = 'SELECT w.*,
            (SELECT COUNT(*) FROM webinar_registrations wr WHERE wr.webinar_id = w.id) AS registered_seats
            FROM webinars w WHERE ' . publicCatalogVisibilitySql('webinars', 'w')
        . " AND w.status IN ({$in}) ORDER BY w.scheduled_at ASC";
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }

    $rows = dbTry(static fn () => db()->fetchAll($sql, $statuses), null);
    return is_array($rows) ? $rows : [];
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
                    (SELECT COUNT(*) FROM webinar_registrations wr WHERE wr.webinar_id = w.id) AS registered_seats
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
                    (SELECT COUNT(*) FROM webinar_registrations wr WHERE wr.webinar_id = w.id) AS registered_seats
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
    $sql = 'SELECT b.* FROM bootcamps b WHERE ' . publicCatalogVisibilitySql('bootcamps', 'b')
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
            'SELECT b.* FROM bootcamps b WHERE b.id = ? AND ' . publicCatalogVisibilitySql('bootcamps', 'b')
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
            'SELECT b.* FROM bootcamps b WHERE b.slug = ? AND ' . publicCatalogVisibilitySql('bootcamps', 'b')
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
    return is_array($rows) ? $rows : [];
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
