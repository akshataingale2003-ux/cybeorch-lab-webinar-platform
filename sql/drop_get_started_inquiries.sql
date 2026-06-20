-- Remove Get Started feature (run once on existing databases)
DELETE FROM form_submissions
WHERE form_key = 'get-started'
   OR storage_table = 'get_started_inquiries';

DROP TABLE IF EXISTS get_started_inquiries;
