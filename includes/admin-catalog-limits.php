<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-actions.php';

/** Maximum active (non-trashed) catalog rows an admin may create per entity. */
const ADMIN_CATALOG_ITEM_LIMIT = 100;

/**
 * @return array<string, array{table: string, label: string, singular: string}>
 */
function adminCatalogEntities(): array
{
    return [
        'webinars'           => ['table' => 'webinars', 'label' => 'Webinars', 'singular' => 'webinar'],
        'bootcamps'          => ['table' => 'bootcamps', 'label' => 'Bootcamps', 'singular' => 'bootcamp'],
        'recorded_sessions'  => ['table' => 'recorded_sessions', 'label' => 'Recorded Sessions', 'singular' => 'recorded session'],
        'live_projects'      => ['table' => 'live_projects', 'label' => 'Live Projects', 'singular' => 'live project'],
        'assignments'        => ['table' => 'assignments', 'label' => 'Hands-on Projects', 'singular' => 'hands-on project'],
        'freelance_projects' => ['table' => 'freelance_projects', 'label' => 'Freelance Projects', 'singular' => 'freelance project'],
    ];
}

/** @return array{table: string, label: string, singular: string}|null */
function adminCatalogEntity(string $entityKey): ?array
{
    return adminCatalogEntities()[$entityKey] ?? null;
}

/** Count active rows (not soft-deleted) for limit enforcement. */
function adminCatalogActiveCount(string $entityKey): int
{
    $entity = adminCatalogEntity($entityKey);
    if ($entity === null) {
        return 0;
    }

    $allowedTables = array_column(adminCatalogEntities(), 'table');
    if (!in_array($entity['table'], $allowedTables, true)) {
        return 0;
    }

    try {
        return (int) (db()->fetchOne(
            'SELECT COUNT(*) AS c FROM `' . $entity['table'] . '` WHERE ' . adminSqlActive()
        )['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function adminCatalogCanCreate(string $entityKey): bool
{
    return adminCatalogActiveCount($entityKey) < ADMIN_CATALOG_ITEM_LIMIT;
}

function adminCatalogUsageLabel(string $entityKey): string
{
    return adminCatalogActiveCount($entityKey) . ' / ' . ADMIN_CATALOG_ITEM_LIMIT;
}

function adminCatalogLimitReachedMessage(string $entityKey): string
{
    $entity = adminCatalogEntity($entityKey);
    $label = $entity['label'] ?? 'items';

    return sprintf(
        'You have reached the maximum of %d %s. Delete or unblock items, or empty trash, before adding more.',
        ADMIN_CATALOG_ITEM_LIMIT,
        strtolower($label)
    );
}

/** Block create POST when at limit (call before insert). */
function adminCatalogRejectCreateIfAtLimit(string $entityKey, string $redirectPath): void
{
    if (adminCatalogCanCreate($entityKey)) {
        return;
    }
    adminFlashRedirect($redirectPath, 'error', adminCatalogLimitReachedMessage($entityKey));
}

/** Block opening the add form when at limit. */
function adminCatalogRejectAddActionIfAtLimit(string $entityKey, string $action, int $editId, string $redirectPath): void
{
    if ($action === 'add' && $editId < 1 && !adminCatalogCanCreate($entityKey)) {
        adminFlashRedirect($redirectPath, 'error', adminCatalogLimitReachedMessage($entityKey));
    }
}

/**
 * Usage counter + add button or limit warning (list view).
 */
function adminCatalogRenderListToolbar(string $entityKey, string $addAdminPath, string $addButtonLabel): void
{
    $count = adminCatalogActiveCount($entityKey);
    $atLimit = $count >= ADMIN_CATALOG_ITEM_LIMIT;
    $usage = adminCatalogUsageLabel($entityKey);
    ?>
    <div class="admin-catalog-toolbar mb-3" style="display:flex;flex-wrap:wrap;align-items:center;gap:1rem;justify-content:space-between">
      <span style="color:var(--cyber-muted);font-size:.9rem">
        <i class="fas fa-layer-group me-1" style="color:var(--cyber-accent)"></i>
        <strong style="color:var(--cyber-accent)"><?= htmlspecialchars($usage) ?></strong> items
      </span>
      <?php if ($atLimit): ?>
      <div class="alert alert-error mb-0" style="flex:1;min-width:220px;padding:.55rem 1rem;font-size:.85rem">
        <i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars(adminCatalogLimitReachedMessage($entityKey)) ?>
      </div>
      <?php else: ?>
      <a href="<?= adminUrl($addAdminPath) ?>" class="btn-primary-cyber"><i class="fas fa-plus"></i><?= htmlspecialchars($addButtonLabel) ?></a>
      <?php endif; ?>
    </div>
    <?php
}

/** Prevent double form submission in admin catalog forms. */
function adminRenderFormSubmitGuard(string $formId, string $buttonId = ''): void
{
    $formIdEsc = htmlspecialchars($formId, ENT_QUOTES, 'UTF-8');
    $buttonIdJs = $buttonId !== '' ? "'" . addslashes($buttonId) . "'" : 'null';
    echo '<script>(function(){var f=document.getElementById("' . $formIdEsc . '");if(!f)return;var s=false;f.addEventListener("submit",function(e){if(s){e.preventDefault();return;}s=true;var b=' . $buttonIdJs . '?document.getElementById(' . $buttonIdJs . '):f.querySelector("[type=submit]");if(b){b.disabled=true;b.innerHTML=\'<i class="fas fa-spinner fa-spin me-1"></i>Saving…\';}});})();</script>';
}
