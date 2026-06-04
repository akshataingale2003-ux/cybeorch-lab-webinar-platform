<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
rejectWhenPublicAuthDisabled();
redirectWith('login.php', 'info', 'Password reset is not configured yet. Please contact support.');
exit;
