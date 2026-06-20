<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/social-oauth.php';

rejectWhenPublicAuthDisabled();

startSession();

$email = strtolower(trim(sanitize($_GET['email'] ?? '')));
$popup = !empty($_GET['popup']);

oauthBeginGoogle($email, $popup);
