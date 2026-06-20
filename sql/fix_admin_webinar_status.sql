-- Admin-created webinars were saved as upcoming due to form/code defaults.
-- Keep canonical seeded upcoming catalog rows; promote other active upcoming rows to live.
UPDATE webinars
SET status = 'live',
    updated_at = NOW()
WHERE status = 'upcoming'
  AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
  AND slug NOT IN ('cybersecurity', 'ai-ml', 'blockchain', 'devops');
