<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/contact-messages.php';
require_once __DIR__ . '/admin-schema.php';
require_once __DIR__ . '/training-catalog.php';

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
    ensureTrainingCatalog();
    if (function_exists('ensureSupportTicketSchema')) {
        require_once __DIR__ . '/support-tickets.php';
        ensureSupportTicketSchema();
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
        ['label' => 'Webinar registrations', 'table' => 'webinar_registrations', 'admin_page' => 'admin/registrations.php'],
        ['label' => 'Bootcamp enrollments', 'table' => 'bootcamp_enrollments', 'admin_page' => 'admin/registrations.php'],
        ['label' => 'Payments', 'table' => 'payments', 'admin_page' => 'admin/payments.php'],
        ['label' => 'NxL wallets', 'table' => 'wallet', 'admin_page' => 'admin/wallet.php'],
        ['label' => 'Referrals', 'table' => 'referrals', 'admin_page' => 'admin/referrals.php'],
        ['label' => 'Form messages', 'table' => 'contact_messages', 'admin_page' => 'admin/messages.php'],
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
    return [
        [
            'form'         => 'Contact us',
            'page'         => 'contact.php',
            'subject'      => 'Contact',
            'admin_filter' => 'Contact',
        ],
        [
            'form'         => 'Free bootcamps & webinars',
            'page'         => 'enquire-enroll.php',
            'subject'      => 'Free Bootcamps & Webinars Registration',
            'admin_filter' => 'Free enrollment',
        ],
        [
            'form'         => 'Pro Learner trial',
            'page'         => 'enquire-enroll.php?plan=pro-trial',
            'subject'      => 'Pro Learner Trial Registration',
            'admin_filter' => 'Pro Learner trial',
        ],
        [
            'form'         => 'Assignment applications',
            'page'         => 'assignment-register.php',
            'subject'      => 'Assignment Registration',
            'admin_filter' => 'Assignment applications',
        ],
        [
            'form'         => 'Start your project',
            'page'         => 'start-project.php',
            'subject'      => 'Start Your Project',
            'admin_filter' => 'Start Your Project',
        ],
        [
            'form'         => 'Book consulting',
            'page'         => 'book-consulting.php',
            'subject'      => 'Book Consulting',
            'admin_filter' => 'Book Consulting',
        ],
        [
            'form'         => 'User Registration & Trainee signup',
            'page'         => 'index.php',
            'subject'      => 'users table',
            'admin_filter' => 'admin/students.php',
        ],
        [
            'form'         => 'Freelancer signup',
            'page'         => 'register-freelancer.php',
            'subject'      => 'freelancer_registrations',
            'admin_filter' => 'admin/freelancers.php',
        ],
    ];
}
