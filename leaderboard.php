<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

header('Location: ' . url('leadership.php'), true, 302);
exit;
