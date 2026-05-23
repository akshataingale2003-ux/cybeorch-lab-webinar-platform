<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function ensureFreelancerRegistrationsSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    db()->execute("CREATE TABLE IF NOT EXISTS freelancer_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(15) DEFAULT NULL,
        primary_role VARCHAR(50) NOT NULL,
        experience_level ENUM('fresher','1-2','3-5','5+') NOT NULL,
        skills TEXT NOT NULL,
        portfolio_url VARCHAR(500) DEFAULT NULL,
        github_url VARCHAR(500) DEFAULT NULL,
        linkedin_url VARCHAR(500) DEFAULT NULL,
        availability ENUM('full_time','part_time','project_based') NOT NULL,
        location VARCHAR(100) DEFAULT NULL,
        about TEXT,
        status ENUM('pending','reviewed','shortlisted','rejected','active') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_freelancer_email (email)
    )");
}

function getPendingFreelancerCount(): int
{
    ensureFreelancerRegistrationsSchema();
    return (int) (db()->fetchOne("SELECT COUNT(*) AS c FROM freelancer_registrations WHERE status='pending'")['c'] ?? 0);
}
