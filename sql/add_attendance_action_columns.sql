-- Soft-delete and block columns for attendance tables

ALTER TABLE webinar_attendance_log
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN record_status ENUM('active','blocked') NOT NULL DEFAULT 'active';

ALTER TABLE attendance
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN record_status ENUM('active','blocked') NOT NULL DEFAULT 'active';
