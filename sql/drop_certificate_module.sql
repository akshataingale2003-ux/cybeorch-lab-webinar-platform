-- Remove digital certificate module tables (safe to run multiple times).
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS certificate_verification_logs;
DROP TABLE IF EXISTS certificate_id_sequences;
DROP TABLE IF EXISTS certificates;
DROP TABLE IF EXISTS digital_certificates;
SET FOREIGN_KEY_CHECKS = 1;
