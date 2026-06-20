<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/public-catalog.php';
require_once __DIR__ . '/../includes/live-projects-public.php';
require_once __DIR__ . '/../includes/assignments-data.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function projectsApiOut(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    projectsApiOut(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$type = strtolower(trim((string) ($_GET['type'] ?? 'all')));
$limit = (int) ($_GET['limit'] ?? 0);
$homepageOnly = in_array(strtolower(trim((string) ($_GET['homepage'] ?? ''))), ['1', 'true', 'yes'], true);

/** @param list<array<string, mixed>> $rows */
function projectsApiMapLive(array $rows): array
{
    return array_map(static function (array $row): array {
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'slug'        => (string) ($row['slug'] ?? ''),
            'title'       => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['short_desc'] ?: $row['description'] ?? ''),
            'category'    => (string) ($row['category'] ?? ''),
            'project_type'=> (string) ($row['project_type'] ?? CATALOG_PROJECT_TYPE_LIVE),
            'skills'      => (string) ($row['stack'] ?? ''),
            'budget'      => '',
            'duration'    => (string) ($row['duration'] ?? ''),
            'image'       => liveProjectImageUrl($row),
            'status'      => (string) ($row['status'] ?? ''),
            'url'         => liveProjectDetailUrl($row),
        ];
    }, $rows);
}

/** @param list<array<string, mixed>> $rows */
function projectsApiMapHandsOn(array $rows): array
{
    return array_map(static function (array $row): array {
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'slug'        => (string) ($row['slug'] ?? ''),
            'title'       => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['short_desc'] ?: $row['description'] ?? ''),
            'category'    => (string) ($row['category'] ?? ''),
            'project_type'=> (string) ($row['project_type'] ?? CATALOG_PROJECT_TYPE_HANDS_ON),
            'skills'      => (string) ($row['skills'] ?? ''),
            'budget'      => (string) ($row['stipend'] ?? ''),
            'duration'    => (string) ($row['duration'] ?? ''),
            'image'       => '',
            'status'      => (string) ($row['status'] ?? ''),
            'url'         => assignmentRegisterUrl($row),
        ];
    }, $rows);
}

/** @param list<array<string, mixed>> $rows */
function projectsApiMapFreelance(array $rows): array
{
    return array_map(static function (array $row): array {
        return [
            'id'          => (int) ($row['id'] ?? 0),
            'slug'        => (string) ($row['slug'] ?? ''),
            'title'       => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['short_desc'] ?: $row['description'] ?? ''),
            'category'    => (string) ($row['category'] ?? ''),
            'project_type'=> (string) ($row['project_type'] ?? CATALOG_PROJECT_TYPE_FREELANCER),
            'skills'      => (string) ($row['skills'] ?? ''),
            'budget'      => (string) ($row['budget'] ?? ''),
            'duration'    => (string) ($row['duration'] ?? ''),
            'image'       => freelanceProjectImageUrl($row),
            'status'      => (string) ($row['status'] ?? ''),
            'url'         => freelanceProjectDetailUrl($row),
        ];
    }, $rows);
}

try {
    ensurePublicCatalogSchemas();
    $live = dbTry(static fn () => catalogGetLiveProjects(false, $homepageOnly), []);
    $handsOn = dbTry(static fn () => catalogGetAssignments(false, $homepageOnly), []);
    $freelance = dbTry(static fn () => catalogGetFreelanceProjects(false, $homepageOnly), []);

    if ($limit > 0) {
        $live = array_slice($live, 0, $limit);
        $handsOn = array_slice($handsOn, 0, $limit);
        $freelance = array_slice($freelance, 0, $limit);
    }

    $payload = match ($type) {
        'live' => ['live' => projectsApiMapLive($live)],
        'hands-on', 'hands_on', 'assignment', 'assignments' => ['hands_on' => projectsApiMapHandsOn($handsOn)],
        'freelance', 'freelancer' => ['freelance' => projectsApiMapFreelance($freelance)],
        'all', '' => [
            'live'      => projectsApiMapLive($live),
            'hands_on'  => projectsApiMapHandsOn($handsOn),
            'freelance' => projectsApiMapFreelance($freelance),
        ],
        default => null,
    };

    if ($payload === null) {
        projectsApiOut(400, [
            'ok'      => false,
            'message' => 'Invalid type. Use live, hands-on, freelance, or all.',
        ]);
    }

    projectsApiOut(200, [
        'ok'        => true,
        'generated' => gmdate('c'),
        'projects'  => $payload,
    ]);
} catch (Throwable $e) {
    projectsApiOut(500, ['ok' => false, 'message' => 'Could not load projects.']);
}
