-- Run once on existing cybeorch_db installs (phpMyAdmin or mysql CLI)
USE cybeorch_db;

CREATE TABLE IF NOT EXISTS freelancer_registrations (
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
