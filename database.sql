-- ============================================
-- CYBEORCH LABS - Complete Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS cybeorch_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cybeorch_db;

-- Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    referral_code VARCHAR(20) UNIQUE,
    referred_by VARCHAR(20) DEFAULT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admin Table
CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('super_admin','moderator') DEFAULT 'moderator',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Webinars Table
CREATE TABLE webinars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_desc VARCHAR(500),
    instructor VARCHAR(100),
    instructor_bio TEXT,
    instructor_img VARCHAR(255),
    thumbnail VARCHAR(255),
    category VARCHAR(100),
    tags VARCHAR(255),
    scheduled_at DATETIME NOT NULL,
    registration_closes_at DATETIME NULL DEFAULT NULL,
    closing_soon_enabled TINYINT(1) NOT NULL DEFAULT 0,
    duration_mins INT DEFAULT 60,
    max_seats INT DEFAULT 100,
    registered_seats INT DEFAULT 0,
    fee DECIMAL(10,2) DEFAULT 0.00,
    is_free TINYINT(1) DEFAULT 0,
    meeting_link VARCHAR(500),
    meeting_platform ENUM('zoom','google_meet','teams','other') DEFAULT 'zoom',
    status ENUM('upcoming','live','completed','cancelled') DEFAULT 'live',
    recording_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Recorded Sessions (linked to webinars/bootcamps; enrollment-gated playback)
CREATE TABLE recorded_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    link_type ENUM('webinar','bootcamp') NOT NULL,
    webinar_id INT NULL DEFAULT NULL,
    bootcamp_id INT NULL DEFAULT NULL,
    video_path VARCHAR(500) DEFAULT NULL,
    video_original VARCHAR(255) DEFAULT NULL,
    video_mime VARCHAR(120) DEFAULT NULL,
    video_size BIGINT UNSIGNED DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'Draft',
    sort_order INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_recorded_sessions_slug (slug),
    INDEX idx_rs_webinar (webinar_id),
    INDEX idx_rs_bootcamp (bootcamp_id),
    INDEX idx_rs_status (status),
    INDEX idx_rs_sort (sort_order)
);

-- Bootcamps Table
CREATE TABLE bootcamps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_desc VARCHAR(500),
    instructor VARCHAR(100),
    thumbnail VARCHAR(255),
    category VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    duration_weeks INT,
    total_seats INT DEFAULT 30,
    enrolled_seats INT DEFAULT 0,
    original_fee DECIMAL(10,2) DEFAULT 0.00,
    discounted_fee DECIMAL(10,2) DEFAULT 0.00,
    fee_usd DECIMAL(10,2) DEFAULT NULL,
    duration_label VARCHAR(80) DEFAULT NULL,
    curriculum TEXT,
    outcomes TEXT,
    prerequisites TEXT,
    certificate TINYINT(1) DEFAULT 1,
    status ENUM('open','closed','ongoing','completed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Webinar Registrations
CREATE TABLE webinar_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webinar_id INT NOT NULL,
    registration_no VARCHAR(50) UNIQUE NOT NULL,
    payment_id INT DEFAULT NULL,
    payment_status ENUM('pending','paid','free','refunded') DEFAULT 'pending',
    attended TINYINT(1) DEFAULT 0,
    feedback TEXT,
    rating TINYINT(1) DEFAULT NULL,
    nxl_credited TINYINT(1) DEFAULT 0,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (webinar_id) REFERENCES webinars(id) ON DELETE CASCADE
);

-- Bootcamp Enrollments
CREATE TABLE bootcamp_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    bootcamp_id INT NOT NULL,
    enrollment_no VARCHAR(50) UNIQUE NOT NULL,
    payment_id INT DEFAULT NULL,
    payment_status ENUM('pending','paid','refunded') DEFAULT 'pending',
    progress_pct INT DEFAULT 0,
    certificate_issued TINYINT(1) DEFAULT 0,
    certificate_url VARCHAR(255) DEFAULT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bootcamp_id) REFERENCES bootcamps(id) ON DELETE CASCADE
);

-- Payments Table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id VARCHAR(100) UNIQUE NOT NULL,
    razorpay_order_id VARCHAR(100) UNIQUE,
    razorpay_payment_id VARCHAR(100) UNIQUE,
    razorpay_signature VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    payment_for ENUM('webinar','bootcamp','wallet_topup') NOT NULL,
    reference_id INT NOT NULL,
    status ENUM('created','paid','failed','refunded') DEFAULT 'created',
    invoice_no VARCHAR(50) UNIQUE,
    receipt_no VARCHAR(50) UNIQUE,
    payment_type VARCHAR(20) DEFAULT NULL COMMENT 'online|nxl_token|mixed|free',
    nxl_tokens_used DECIMAL(10,2) NOT NULL DEFAULT 0,
    nxl_inr_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    cash_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    wallet_transaction_id INT DEFAULT NULL,
    nxl_transaction_ref VARCHAR(50) DEFAULT NULL,
    registration_no VARCHAR(50) DEFAULT NULL,
    payment_method VARCHAR(50),
    notes TEXT,
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- NXL Wallet (balance stored in NXL Credits)
CREATE TABLE wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    total_earned DECIMAL(10,2) DEFAULT 0.00,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wallet Transactions (amount = NXL Credits; inr_equivalent = credits × NXL_INR_VALUE)
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    inr_equivalent DECIMAL(10,2) DEFAULT NULL,
    reason ENUM('webinar_reward','referral_bonus','bootcamp_reward','admin_credit','admin_debit','redemption','cashback','signup_bonus','special_reward','transfer_in','transfer_out') NOT NULL,
    reference_id INT DEFAULT NULL,
    description VARCHAR(255),
    balance_after DECIMAL(10,2),
    reward_type VARCHAR(50) DEFAULT NULL,
    remarks VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL DEFAULT 'wallet',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    user_id INT DEFAULT NULL,
    amount DECIMAL(10,2) DEFAULT NULL,
    reward_type VARCHAR(50) DEFAULT NULL,
    reference_id INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_notif_read (is_read, created_at)
);

-- Referrals
CREATE TABLE referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    status ENUM('pending','rewarded') DEFAULT 'pending',
    bonus_amount DECIMAL(10,2) DEFAULT 50.00,
    rewarded_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Attendance
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webinar_id INT NOT NULL,
    joined_at DATETIME,
    left_at DATETIME,
    duration_mins INT DEFAULT 0,
    marked_by ENUM('self','admin','auto') DEFAULT 'auto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (webinar_id) REFERENCES webinars(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    type ENUM('registration','payment','reminder','attendance','wallet','system','referral') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    reference_id INT DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    sent_via_email TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Freelancer Registrations
CREATE TABLE freelancer_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(15),
    primary_role VARCHAR(50) NOT NULL,
    experience_level ENUM('fresher','1-2','3-5','5+') NOT NULL,
    skills TEXT NOT NULL,
    portfolio_url VARCHAR(500) DEFAULT NULL,
    github_url VARCHAR(500) DEFAULT NULL,
    linkedin_url VARCHAR(500) DEFAULT NULL,
    availability ENUM('full_time','part_time','project_based') NOT NULL,
    location VARCHAR(100) DEFAULT NULL,
    about TEXT,
    resume_path VARCHAR(500) DEFAULT NULL,
    resume_original_name VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','reviewed','shortlisted','rejected','active') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_freelancer_email_role (email, primary_role),
    UNIQUE KEY unique_freelancer_user_role (user_id, primary_role)
);

-- Mandatory website popup registration
CREATE TABLE website_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_website_email (email)
);

CREATE TABLE registration_pending_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    mobile VARCHAR(20) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    otp_expiry DATETIME NOT NULL,
    verify_attempts INT NOT NULL DEFAULT 0,
    last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pending_email (email)
);

-- Contact Messages (contact form, start-project, book-consulting)
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(15),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    resume_path VARCHAR(500) DEFAULT NULL,
    resume_original_name VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    replied TINYINT(1) DEFAULT 0,
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cm_read (is_read),
    INDEX idx_cm_subject (subject(100))
);

-- Product demo requests (products.php → api/demo-request.php)
CREATE TABLE demo_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_page VARCHAR(80) DEFAULT NULL,
    product_name VARCHAR(255) DEFAULT NULL,
    project_name VARCHAR(255) DEFAULT NULL,
    interested_technology VARCHAR(180) DEFAULT NULL,
    preferred_date DATE DEFAULT NULL,
    preferred_time TIME DEFAULT NULL,
    message TEXT DEFAULT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(24) DEFAULT NULL,
    org_name VARCHAR(180) DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dr_created (created_at),
    INDEX idx_dr_email (email(120))
);

-- Live project collaboration (collaborate-project.php)
CREATE TABLE collaboration_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_slug VARCHAR(255) DEFAULT NULL,
    project_title VARCHAR(255) DEFAULT NULL,
    project_category VARCHAR(120) DEFAULT NULL,
    collaboration_type VARCHAR(80) DEFAULT NULL,
    budget_range VARCHAR(120) DEFAULT NULL,
    timeline VARCHAR(120) DEFAULT NULL,
    company VARCHAR(180) DEFAULT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(24) DEFAULT NULL,
    description TEXT NOT NULL,
    requirement_file VARCHAR(255) DEFAULT NULL,
    requirement_file_original VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ci_created (created_at),
    INDEX idx_ci_project (project_slug(120))
);

-- Universal form submission log (all public forms — see includes/form-submissions.php)
CREATE TABLE form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_key VARCHAR(80) NOT NULL,
    form_label VARCHAR(255) NOT NULL,
    source_page VARCHAR(120) DEFAULT NULL,
    full_name VARCHAR(120) DEFAULT NULL,
    email VARCHAR(180) DEFAULT NULL,
    phone VARCHAR(24) DEFAULT NULL,
    summary TEXT DEFAULT NULL,
    payload_json JSON DEFAULT NULL,
    storage_table VARCHAR(64) DEFAULT NULL,
    storage_record_id INT DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fs_form_key (form_key),
    INDEX idx_fs_created (created_at),
    INDEX idx_fs_read (is_read)
);

-- Support Desk (also auto-created by includes/support-tickets.php on first visit)
CREATE TABLE support_ticket_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(40) DEFAULT 'fa-folder',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE support_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(40) DEFAULT 'fa-users',
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(24) UNIQUE NOT NULL,
    user_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(15) DEFAULT NULL,
    category_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','waiting_customer','resolved','closed') DEFAULT 'open',
    assigned_team_id INT DEFAULT NULL,
    assigned_admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    INDEX idx_ticket_email (email),
    INDEX idx_ticket_status (status)
);

CREATE TABLE support_ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender_type ENUM('user','admin') NOT NULL,
    sender_user_id INT DEFAULT NULL,
    sender_admin_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reply_ticket (ticket_id)
);

-- Live Projects, Hands-on Projects, Freelance Projects (admin-managed catalog)
CREATE TABLE live_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    short_desc VARCHAR(500) DEFAULT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    icon_class VARCHAR(80) NULL DEFAULT 'fa-code-branch',
    card_theme VARCHAR(32) NULL DEFAULT 'theme-default',
    image_path VARCHAR(255) NULL DEFAULT NULL,
    features_json TEXT NULL,
    stack VARCHAR(255) DEFAULT NULL,
    duration VARCHAR(64) DEFAULT NULL,
    team_size VARCHAR(64) DEFAULT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'Open for Collaboration',
    project_type VARCHAR(32) NOT NULL DEFAULT 'live_project',
    show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_live_projects_slug (slug)
);

CREATE TABLE IF NOT EXISTS catalog_revisions (
    revision_key VARCHAR(64) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    short_desc VARCHAR(500) DEFAULT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    type VARCHAR(64) NOT NULL DEFAULT 'Project',
    skills VARCHAR(500) DEFAULT NULL,
    duration VARCHAR(64) DEFAULT NULL,
    mode VARCHAR(64) DEFAULT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'Open',
    apply_route VARCHAR(255) DEFAULT NULL,
    project_type VARCHAR(32) NOT NULL DEFAULT 'hands_on_project',
    show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_assignments_slug (slug)
);

CREATE TABLE freelance_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    short_desc VARCHAR(500) DEFAULT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    skills VARCHAR(500) DEFAULT NULL,
    duration VARCHAR(64) DEFAULT NULL,
    mode VARCHAR(64) DEFAULT NULL,
    budget_label VARCHAR(120) DEFAULT NULL,
    client_name VARCHAR(120) DEFAULT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'Open',
    apply_route VARCHAR(255) DEFAULT 'register-freelancer.php',
    project_type VARCHAR(32) NOT NULL DEFAULT 'freelancer_project',
    show_on_homepage TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL DEFAULT NULL,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_freelance_projects_slug (slug)
);

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default Admin (password: Admin@123) — run reset_admin_password.php once after import
INSERT INTO admin (username, email, password, full_name, role) VALUES
('cybeorch_admin', 'akshataingale2003@gmail.com', '$2y$12$XYZ_REPLACE_WITH_HASHED_PASSWORD', 'CYBEORCH Admin', 'super_admin');

-- Upcoming Webinars (homepage catalog) — upsert by slug to avoid duplicates on re-import
INSERT INTO webinars (title, slug, description, short_desc, instructor, category, scheduled_at, duration_mins, max_seats, fee, is_free, status) VALUES
('Cybersecurity', 'cybersecurity', 'Learn cybersecurity fundamentals, threat detection, network security, and best practices.', 'Learn cybersecurity fundamentals, threat detection, network security, and best practices.', 'Kalpesh Patil', 'Cybersecurity', '2026-06-07 11:00:00', 90, 200, 0.00, 1, 'upcoming'),
('AI / ML', 'ai-ml', 'Learn Artificial Intelligence, Machine Learning, automation, and real-world applications.', 'Learn Artificial Intelligence, Machine Learning, automation, and real-world applications.', 'Kanchan', 'AI / ML', '2026-06-14 11:00:00', 120, 150, 4750.00, 0, 'upcoming'),
('Blockchain', 'blockchain', 'Understand blockchain technology, smart contracts, Web3, and decentralized systems.', 'Understand blockchain technology, smart contracts, Web3, and decentralized systems.', 'Monali Patil', 'Blockchain', '2026-06-21 11:00:00', 90, 180, 4750.00, 0, 'upcoming'),
('DevOps', 'devops', 'Learn CI/CD, Docker, Kubernetes, Infrastructure as Code, and DevOps best practices.', 'Learn CI/CD, Docker, Kubernetes, Infrastructure as Code, and DevOps best practices.', 'Akshata Ingale', 'DevOps', '2026-06-28 11:00:00', 90, 100, 4750.00, 0, 'upcoming')
ON DUPLICATE KEY UPDATE
title = VALUES(title),
description = VALUES(description),
short_desc = VALUES(short_desc),
instructor = VALUES(instructor),
category = VALUES(category),
scheduled_at = VALUES(scheduled_at),
duration_mins = VALUES(duration_mins),
max_seats = VALUES(max_seats),
fee = VALUES(fee),
is_free = VALUES(is_free);

-- Sample Bootcamps
INSERT INTO bootcamps (title, slug, description, short_desc, instructor, category, start_date, end_date, duration_weeks, total_seats, original_fee, discounted_fee, certificate, status) VALUES
('Cyber Security Bootcamp', 'cyber-security-bootcamp', 'Comprehensive cybersecurity software development company: network defense, ethical hacking fundamentals, SOC workflows, cloud security, and incident response with hands-on labs and CTF challenges.', '4-week cyber security bootcamp with live labs, mentorship, and certification prep.', 'Kanchan', 'Cyber Security', DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 42 DAY), 4, 32, 45499.00, 37909.19, 1, 'open'),
('Complete Ethical Hacking Bootcamp', 'ethical-hacking-bootcamp', 'A 4-week intensive bootcamp covering everything from network security to advanced exploitation techniques.', '4-week hands-on ethical hacking bootcamp with certification.', 'Kalpesh Patil', 'Ethical Hacking', DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 43 DAY), 4, 30, 24999.00, 18999.00, 1, 'open'),
('Web Application Security Bootcamp', 'web-app-security-bootcamp', 'Learn to identify and exploit web vulnerabilities: OWASP Top 10, SQL injection, XSS, CSRF and more.', '3-week web security bootcamp with live practice labs.', 'Kanchan', 'Web Security', DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 41 DAY), 3, 25, 45499.00, 37909.19, 1, 'open'),
('Blockchain Development', 'blockchain-development-bootcamp', 'Build decentralized applications with smart contracts, wallets, and Web3 integrations—from fundamentals to deployment on testnets.', 'Hands-on blockchain & smart contract bootcamp with real project labs.', 'Monali Patil', 'Blockchain', DATE_ADD(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 53 DAY), 4, 28, 45499.00, 37909.19, 1, 'open'),
('Mobile Application Security Bootcamp', 'mobile-app-security-bootcamp', 'Secure Android and iOS apps: OWASP MASVS, reverse engineering basics, API hardening, and mobile pentesting workflows.', '4-week mobile app security bootcamp with device labs & assessments.', 'Kanchan', 'Mobile Security', DATE_ADD(CURDATE(), INTERVAL 18 DAY), DATE_ADD(CURDATE(), INTERVAL 46 DAY), 4, 24, 45499.00, 37909.19, 1, 'open'),
('Full Stack Development Bootcamp', 'full-stack-development-bootcamp', 'End-to-end web development: React/Next.js frontends, Node.js APIs, databases, auth, and deployment pipelines.', '6-week intensive full stack program with capstone project.', 'Kalpesh Patil', 'Full Stack', DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 72 DAY), 6, 35, 56999.00, 47410.24, 1, 'open'),
('DevOps Bootcamp', 'devops-bootcamp', 'Master CI/CD pipelines, Docker, Kubernetes, infrastructure as code, and cloud deployment workflows with hands-on labs.', '4-week DevOps bootcamp covering Docker, K8s, and automation pipelines.', 'Akshata Ingale', 'DevOps', DATE_ADD(CURDATE(), INTERVAL 22 DAY), DATE_ADD(CURDATE(), INTERVAL 50 DAY), 4, 30, 45499.00, 37909.19, 1, 'open'),
('30-Day Bootcamp', '30-day-bootcamp', 'Fast-track foundation with live labs, mentorship, and certificate — ideal first step into cybersecurity.', 'CYBEORCH LABS entry funnel — 30-day intensive bootcamp.', 'Kanchan', 'Program Path', DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 36 DAY), 4, 40, 45499.00, 37909.19, 1, 'open'),
('45–60 Day Advanced Bootcamp', '90-day-bootcamp', 'Deeper specialization, capstone projects, and career placement support for graduates ready to level up.', 'CYBEORCH LABS advanced track — 45–60 day industry bootcamp.', 'Kalpesh Patil', 'Program Path', DATE_ADD(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 65 DAY), 8, 30, 56999.00, 47410.24, 1, 'open'),
('Corporate Training Program', 'premium-industry-lab', 'Elite lab access, enterprise mentors, and real-world industry projects — built for serious professionals and teams.', 'CYBEORCH LABS corporate track — enterprise training program.', 'Monali Patil', 'Program Path', DATE_ADD(CURDATE(), INTERVAL 21 DAY), DATE_ADD(CURDATE(), INTERVAL 50 DAY), 4, 20, 79999.00, 66412.34, 1, 'open');

-- Password reset tokens (one-time use)
CREATE TABLE password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_referral ON users(referral_code);
CREATE INDEX idx_webinars_status ON webinars(status);
CREATE INDEX idx_webinars_scheduled ON webinars(scheduled_at);
CREATE INDEX idx_payments_user ON payments(user_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_wallet_tx_user ON wallet_transactions(user_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
