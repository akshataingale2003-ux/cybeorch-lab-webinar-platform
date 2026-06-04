<?php
declare(strict_types=1);

define('CYBEORCH_JSON_API', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/demo-requests.php';
require_once __DIR__ . '/../includes/mailer.php';

startSession();

header('Content-Type: application/json; charset=UTF-8');

function jsonOut(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if (!verifyCSRF((string) ($_POST['csrf_token'] ?? ''))) {
    jsonOut(400, ['ok' => false, 'message' => 'Invalid request. Please refresh and try again.']);
}

// Honeypot
if (!empty($_POST['website'])) {
    jsonOut(400, ['ok' => false, 'message' => 'Blocked.']);
}

// Simple time-based anti-spam
$minSeconds = 2;
$ts = (int) ($_SESSION['demo_form_ts'] ?? 0);
if ($ts > 0 && (time() - $ts) < $minSeconds) {
    jsonOut(429, ['ok' => false, 'message' => 'Please try again.']);
}
$_SESSION['demo_form_ts'] = time();

$fullName = sanitize($_POST['full_name'] ?? '');
$email = trim((string) ($_POST['email'] ?? ''));
$phone = sanitize($_POST['phone'] ?? '');
$org = sanitize($_POST['org_name'] ?? '');
$productName = sanitize($_POST['product_name'] ?? '');
$projectName = sanitize($_POST['project_name'] ?? '');
$tech = sanitize($_POST['interested_technology'] ?? '');
$date = trim((string) ($_POST['preferred_date'] ?? ''));
$time = trim((string) ($_POST['preferred_time'] ?? ''));
$message = trim((string) sanitize($_POST['message'] ?? ''));
$source = sanitize($_POST['source_page'] ?? 'products');

$errors = [];
if (strlen($fullName) < 2) $errors[] = 'Full name is required.';
if (!isValidEmail($email)) $errors[] = 'Valid email is required.';
if (strlen(preg_replace('/\D+/', '', $phone) ?? '') < 8) $errors[] = 'Phone number is required.';
if (strlen($org) < 2) $errors[] = 'Company/College name is required.';
if (strlen($productName) < 2 && strlen($projectName) < 2) $errors[] = 'Product/Project name is required.';
if (strlen($tech) < 2) $errors[] = 'Interested technology is required.';

if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $errors[] = 'Preferred demo date is invalid.';
}
if ($time !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
    $errors[] = 'Preferred demo time is invalid.';
}
if (strlen($message) < 5) {
    $errors[] = 'Message / requirements is required.';
}

if ($errors !== []) {
    jsonOut(422, ['ok' => false, 'message' => implode(' ', $errors)]);
}

// Store in DB
$id = dbTry(fn () => insertDemoRequest([
    'source_page' => $source,
    'product_name' => $productName !== '' ? $productName : null,
    'project_name' => $projectName !== '' ? $projectName : null,
    'interested_technology' => $tech,
    'preferred_date' => $date !== '' ? $date : null,
    'preferred_time' => $time !== '' ? substr($time, 0, 8) : null,
    'message' => $message,
    'full_name' => $fullName,
    'email' => $email,
    'phone' => $phone,
    'org_name' => $org,
    'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
]), 0);

// Email admin (best-effort)
$title = $productName !== '' ? $productName : $projectName;
$subj = 'New demo request — ' . ($title !== '' ? $title : 'CYBEORCH');
$html = '<div style="font-family:Arial,sans-serif;max-width:720px;margin:0 auto;padding:18px">'
    . '<h2 style="margin:0 0 10px;color:#0a1628">New demo request</h2>'
    . '<p><strong>Name:</strong> ' . htmlspecialchars($fullName) . '<br>'
    . '<strong>Email:</strong> ' . htmlspecialchars($email) . '<br>'
    . '<strong>Phone:</strong> ' . htmlspecialchars($phone) . '<br>'
    . '<strong>Org:</strong> ' . htmlspecialchars($org) . '</p>'
    . '<p><strong>Product:</strong> ' . htmlspecialchars($productName) . '<br>'
    . '<strong>Project:</strong> ' . htmlspecialchars($projectName) . '<br>'
    . '<strong>Tech:</strong> ' . htmlspecialchars($tech) . '<br>'
    . '<strong>Date:</strong> ' . htmlspecialchars($date) . '<br>'
    . '<strong>Time:</strong> ' . htmlspecialchars($time) . '</p>'
    . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:14px 0">'
    . '<pre style="white-space:pre-wrap;background:#f7f7f7;padding:12px;border-radius:8px">' . htmlspecialchars($message) . '</pre>'
    . '<p style="color:#64748b;font-size:12px;margin-top:12px">ID: ' . (int) $id . ' • Source: ' . htmlspecialchars($source) . '</p>'
    . '</div>';

$text = $subj . "\n\n"
    . "Name: {$fullName}\nEmail: {$email}\nPhone: {$phone}\nOrg: {$org}\n"
    . "Product: {$productName}\nProject: {$projectName}\nTech: {$tech}\nDate: {$date}\nTime: {$time}\n\n"
    . $message . "\n";

try {
    sendAdminNotificationEmail($subj, $html, $text, ADMIN_EMAIL);
} catch (Throwable $e) {
    // ignore email errors
}

jsonOut(200, ['ok' => true, 'message' => 'Demo request submitted successfully. We will contact you soon.']);

