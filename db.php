<?php
/**
 * Database connection entry point (PDO + prepared statements).
 * Loads platform config and returns the shared Database instance.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
/** @return Database */
function db()
{
    return Database::getInstance();
}

