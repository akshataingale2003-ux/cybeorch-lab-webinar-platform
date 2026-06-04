<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/contact-messages.php';
require_once __DIR__ . '/admin-schema.php';
require_once __DIR__ . '/training-catalog.php';
require_once __DIR__ . '/catalog-content.php';
require_once __DIR__ . '/admin-actions.php';
require_once __DIR__ . '/demo-requests.php';
require_once __DIR__ . '/collaboration-inquiries.php';
require_once __DIR__ . '/get-started-inquiries.php';
require_once __DIR__ . '/form-submissions.php';

/**
 * Ensure all runtime tables used by the public site and admin exist.
 */
function ensureSiteDatabaseSchemas(): void
{
    ensureContactMessagesSchema();
    if (function_exists('ensureWebsiteUsersSchema')) {
        require_once __DIR__ . '/website-registration.php';
        ensureWebsiteUsersSchema();
    }
    ensureFreelancerRegistrationsSchema();
    ensureDemoRequestsSchema();
    ensureCollaborationInquiriesSchema();
    ensureFormSubmissionsSchema();
    ensureTrainingCatalog();
    ensureCatalogContentSchemas();
    ensureAdminActionsSchema();
    if (function_exists('ensureWebinarRegistrationIntakeSchema')) {
        require_once __DIR__ . '/webinar-registration-service.php';
        ensureWebinarRegistrationIntakeSchema();
    }
    if (function_exists('ensureSupportTicketSchema')) {
        require_once __DIR__ . '/support-tickets.php';
        ensureSupportTicketSchema();
    }
    if (function_exists('ensureNxlWalletSchema')) {
        require_once __DIR__ . '/nxl-wallet.php';
        ensureNxlWalletSchema();
    }
}

/**
 * @return array{connected: bool, message: string}
 */
function getDatabaseConnectionStatus(): array
{
    if (hasDbError()) {
        return [
            'connected' => false,
            'message'   => dbErrorMessage(),
        ];
    }

    $ok = (bool) dbTry(static function () {
        ensureSiteDatabaseSchemas();
        db()->fetchOne('SELECT 1 AS ok');
        return true;
    }, false);

    if (!$ok) {
        return [
            'connected' => false,
            'message'   => hasDbError() ? dbErrorMessage() : 'Database connection failed.',
        ];
    }

    return [
        'connected' => true,
        'message'   => 'Connected to ' . DB_NAME . ' on ' . DB_HOST,
    ];
}

/**
 * @return array<int, array{label: string, table: string, count: int, admin_page: string}>
 */
function getSiteTableStats(): array
{
    $defs = [
        ['label' => 'User Registrations & Trainees (users)', 'table' => 'users', 'admin_page' => 'admin/students.php'],
        ['label' => 'Webinars', 'table' => 'webinars', 'admin_page' => 'admin/webinars.php'],
        ['label' => 'Bootcamps', 'table' => 'bootcamps', 'admin_page' => 'admin/bootcamps.php'],
        ['label' => 'Live projects', 'table' => 'live_projects', 'admin_page' => 'admin/live-projects.php'],
        ['label' => 'Hands-on Projects', 'table' => 'assignments', 'admin_page' => 'admin/assignments.php'],
        ['label' => 'Freelance projects', 'table' => 'freelance_projects', 'admin_page' => 'admin/freelance-projects.php'],
        ['label' => 'Webinar registrations', 'table' => 'webinar_registrations', 'admin_page' => 'admin/registrations.php'],
        ['label' => 'Bootcamp enrollments', 'table' => 'bootcamp_enrollments', 'admin_page' => 'admin/registrations.php'],
        ['label' => 'Payments', 'table' => 'payments', 'admin_page' => 'admin/payments.php'],
        ['label' => 'NxL wallets', 'table' => 'wallet', 'admin_page' => 'admin/wallet.php'],
        ['label' => 'Referrals', 'table' => 'referrals', 'admin_page' => 'admin/referrals.php'],
        ['label' => 'Form submission log', 'table' => 'form_submissions', 'admin_page' => 'admin/form-submissions.php'],
        ['label' => 'Form messages', 'table' => 'contact_messages', 'admin_page' => 'admin/messages.php'],
        ['label' => 'Product demo requests', 'table' => 'demo_requests', 'admin_page' => 'admin/demo-requests.php'],
        ['label' => 'Collaboration inquiries', 'table' => 'collaboration_inquiries', 'admin_page' => 'admin/collaboration-inquiries.php'],
        ['label' => 'Get Started inquiries', 'table' => 'get_started_inquiries', 'admin_page' => 'admin/get-started-inquiries.php'],
        ['label' => 'Website popup registrations', 'table' => 'website_users', 'admin_page' => 'admin/website-registrations.php'],
        ['label' => 'Freelancer applications', 'table' => 'freelancer_registrations', 'admin_page' => 'admin/freelancers.php'],
        ['label' => 'Support tickets', 'table' => 'support_tickets', 'admin_page' => 'admin/tickets.php'],
        ['label' => 'Attendance', 'table' => 'attendance', 'admin_page' => 'admin/attendance.php'],
    ];

    $stats = [];
    foreach ($defs as $def) {
        $count = (int) dbTry(
            fn () => db()->fetchOne('SELECT COUNT(*) AS c FROM `' . $def['table'] . '`')['c'] ?? 0,
            0
        );
        $stats[] = [
            'label'      => $def['label'],
            'table'      => $def['table'],
            'count'      => $count,
            'admin_page' => $def['admin_page'],
        ];
    }
    return $stats;
}

/**
 * Public forms that save into contact_messages (visible in admin Messages).
 *
 * @return array<int, array{form: string, page: string, subject: string, admin_filter: string}>
 */
function getPublicFormAdminLinks(): array
{
    $links = [];
    foreach (getFormSubmissionRegistry() as $row) {
        $links[] = [
            'form'         => $row['form'],
            'page'         => $row['page'],
            'subject'      => $row['table'],
            'admin_filter' => $row['admin'],
        ];
    }
    return $links;
}
