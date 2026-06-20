-- Normalize legacy freelance project statuses to Active/Inactive for admin management.
-- Public listings still accept legacy Open / Closing Soon as active until rows are edited.

UPDATE freelance_projects
SET status = 'Active'
WHERE LOWER(status) IN ('open', 'closing soon', 'active')
  AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00');

UPDATE freelance_projects
SET status = 'Inactive'
WHERE LOWER(status) IN ('closed', 'filled', 'paused', 'inactive');
