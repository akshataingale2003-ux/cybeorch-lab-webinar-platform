-- Allow multiple freelancer applications per user (one per role).
-- Run once on existing cybeorch_db installs.

USE cybeorch_db;

ALTER TABLE freelancer_registrations DROP INDEX unique_freelancer_email;
ALTER TABLE freelancer_registrations ADD UNIQUE KEY unique_freelancer_email_role (email, primary_role);
ALTER TABLE freelancer_registrations ADD UNIQUE KEY unique_freelancer_user_role (user_id, primary_role);
