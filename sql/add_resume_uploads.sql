-- Resume/CV uploads for hands-on project and freelancer applications

ALTER TABLE contact_messages
    ADD COLUMN resume_path VARCHAR(500) DEFAULT NULL AFTER message,
    ADD COLUMN resume_original_name VARCHAR(255) DEFAULT NULL AFTER resume_path;

ALTER TABLE freelancer_registrations
    ADD COLUMN resume_path VARCHAR(500) DEFAULT NULL AFTER about,
    ADD COLUMN resume_original_name VARCHAR(255) DEFAULT NULL AFTER resume_path;
