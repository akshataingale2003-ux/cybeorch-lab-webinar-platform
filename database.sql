-- ============================================
-- CYBEORCH LAB - Complete Database Schema
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
    duration_mins INT DEFAULT 60,
    max_seats INT DEFAULT 100,
    registered_seats INT DEFAULT 0,
    fee DECIMAL(10,2) DEFAULT 0.00,
    is_free TINYINT(1) DEFAULT 0,
    meeting_link VARCHAR(500),
    meeting_platform ENUM('zoom','google_meet','teams','other') DEFAULT 'zoom',
    status ENUM('upcoming','live','completed','cancelled') DEFAULT 'upcoming',
    recording_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
    curriculum TEXT,
    outcomes TEXT,
    prerequisites TEXT,
    certificate TINYINT(1) DEFAULT 1,
    status ENUM('open','closed','ongoing','completed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    payment_method VARCHAR(50),
    notes TEXT,
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- NxL Wallet
CREATE TABLE wallet (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    total_earned DECIMAL(10,2) DEFAULT 0.00,
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wallet Transactions
CREATE TABLE wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason ENUM('webinar_reward','referral_bonus','bootcamp_reward','admin_credit','redemption','cashback','signup_bonus') NOT NULL,
    reference_id INT DEFAULT NULL,
    description VARCHAR(255),
    balance_after DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
    status ENUM('pending','reviewed','shortlisted','rejected','active') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_freelancer_email (email)
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
    is_read TINYINT(1) DEFAULT 0,
    replied TINYINT(1) DEFAULT 0,
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cm_read (is_read),
    INDEX idx_cm_subject (subject(100))
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reply_ticket (ticket_id)
);

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Default Admin (password: Admin@123) — run reset_admin_password.php once after import
INSERT INTO admin (username, email, password, full_name, role) VALUES
('cybeorch_admin', 'admin@cybeorch.com', '$2y$12$XYZ_REPLACE_WITH_HASHED_PASSWORD', 'CYBEORCH Admin', 'super_admin');

-- Sample Webinars
INSERT INTO webinars (title, slug, description, short_desc, instructor, category, scheduled_at, duration_mins, max_seats, fee, is_free, status) VALUES
('Cybersecurity Fundamentals for Beginners', 'cybersec-fundamentals', 'A comprehensive introduction to cybersecurity concepts, ethical hacking basics, and career pathways in the cybersecurity domain.', 'Learn cybersecurity from scratch in this beginner-friendly webinar.', 'Rahul Sharma', 'Cybersecurity', '2026-05-31 11:00:00', 90, 200, 0.00, 1, 'upcoming'),
('Ethical Hacking & Penetration Testing', 'ethical-hacking-pentest', 'Deep dive into penetration testing methodologies, tools like Nmap, Metasploit, and real-world CTF challenges.', 'Master ethical hacking tools and techniques with live demos.', 'Priya Nair', 'Ethical Hacking', '2026-06-07 11:00:00', 120, 150, 2999.00, 0, 'upcoming'),
('Cloud Security & AWS Essentials', 'cloud-security-aws', 'Understand cloud security architecture, IAM policies, S3 security, and AWS security best practices.', 'Secure your cloud infrastructure with industry-standard practices.', 'Amit Verma', 'Cloud Security', '2026-06-21 11:00:00', 90, 100, 499.00, 0, 'upcoming');

-- Sample Bootcamps
INSERT INTO bootcamps (title, slug, description, short_desc, instructor, category, start_date, end_date, duration_weeks, total_seats, original_fee, discounted_fee, certificate, status) VALUES
('Complete Ethical Hacking Bootcamp', 'ethical-hacking-bootcamp', 'A 4-week intensive bootcamp covering everything from network security to advanced exploitation techniques.', '4-week hands-on ethical hacking bootcamp with certification.', 'Rahul Sharma', 'Ethical Hacking', DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 43 DAY), 4, 30, 24999.00, 18999.00, 1, 'open'),
('Web Application Security Bootcamp', 'web-app-security-bootcamp', 'Learn to identify and exploit web vulnerabilities: OWASP Top 10, SQL injection, XSS, CSRF and more.', '3-week web security bootcamp with live practice labs.', 'Priya Nair', 'Web Security', DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 41 DAY), 3, 25, 3999.00, 2499.00, 1, 'open'),
('Blockchain Development', 'blockchain-development-bootcamp', 'Build decentralized applications with smart contracts, wallets, and Web3 integrations—from fundamentals to deployment on testnets.', 'Hands-on blockchain & smart contract bootcamp with real project labs.', 'Amit Verma', 'Blockchain', DATE_ADD(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 53 DAY), 4, 28, 5499.00, 3499.00, 1, 'open'),
('Mobile Application Security Bootcamp', 'mobile-app-security-bootcamp', 'Secure Android and iOS apps: OWASP MASVS, reverse engineering basics, API hardening, and mobile pentesting workflows.', '4-week mobile app security bootcamp with device labs & assessments.', 'Priya Nair', 'Mobile Security', DATE_ADD(CURDATE(), INTERVAL 18 DAY), DATE_ADD(CURDATE(), INTERVAL 46 DAY), 4, 24, 4499.00, 2799.00, 1, 'open'),
('Full Stack Development Bootcamp', 'full-stack-development-bootcamp', 'End-to-end web development: React/Next.js frontends, Node.js APIs, databases, auth, and deployment pipelines.', '6-week intensive full stack program with capstone project.', 'Rahul Sharma', 'Full Stack', DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 72 DAY), 6, 35, 5999.00, 3999.00, 1, 'open'),
('DevOps Bootcamp', 'devops-bootcamp', 'Master CI/CD pipelines, Docker, Kubernetes, infrastructure as code, and cloud deployment workflows with hands-on labs.', '4-week DevOps bootcamp covering Docker, K8s, and automation pipelines.', 'Amit Verma', 'DevOps', DATE_ADD(CURDATE(), INTERVAL 22 DAY), DATE_ADD(CURDATE(), INTERVAL 50 DAY), 4, 30, 4999.00, 3299.00, 1, 'open');

-- Extra webinars
INSERT INTO webinars (title, slug, description, short_desc, instructor, category, scheduled_at, duration_mins, max_seats, fee, is_free, status) VALUES
('AI Tools & Automation Webinar', 'ai-tools-automation-webinar', 'Practical session on AI assistants, workflow automation, and integrating LLM tools safely into engineering and security workflows.', 'Live webinar on AI tooling, automation, and responsible adoption.', 'Amit Verma', 'AI & Automation', '2026-06-14 11:00:00', 90, 180, 1999.00, 0, 'upcoming'),
('Cybersecurity and Awareness Webinar', 'cybersecurity-awareness-webinar', 'Essential security hygiene for teams: phishing awareness, password managers, MFA, and incident reporting best practices.', 'Free awareness webinar for user registrations & trainees, startups, and corporate teams.', 'Rahul Sharma', 'Cybersecurity', '2026-06-28 11:00:00', 75, 250, 0.00, 1, 'upcoming'),
('DevOps & Pipeline Security Webinar', 'devops-pipeline-security-webinar', 'Secure CI/CD pipelines, container images, secrets management, and Kubernetes hardening for modern DevOps teams.', 'Live webinar on DevOps security, Docker, and secure deployment pipelines.', 'Amit Verma', 'DevOps', '2026-07-05 11:00:00', 90, 150, 1999.00, 0, 'upcoming');

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
