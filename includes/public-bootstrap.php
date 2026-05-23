<?php
declare(strict_types=1);

/**
 * Optional one-line bootstrap for public PHP pages.
 * config.php already loads the database for web requests.
 */
require_once __DIR__ . '/config.php';

function initPublicPage(): void
{
    startSession();
}
