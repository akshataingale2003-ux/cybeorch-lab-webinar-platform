-- Project type + homepage visibility (run once on existing databases)

ALTER TABLE live_projects
    ADD COLUMN IF NOT EXISTS project_type VARCHAR(32) NOT NULL DEFAULT 'live_project' AFTER status,
    ADD COLUMN IF NOT EXISTS show_on_homepage TINYINT(1) NOT NULL DEFAULT 0 AFTER project_type;

ALTER TABLE assignments
    ADD COLUMN IF NOT EXISTS project_type VARCHAR(32) NOT NULL DEFAULT 'hands_on_project' AFTER status,
    ADD COLUMN IF NOT EXISTS show_on_homepage TINYINT(1) NOT NULL DEFAULT 0 AFTER project_type;

ALTER TABLE freelance_projects
    ADD COLUMN IF NOT EXISTS project_type VARCHAR(32) NOT NULL DEFAULT 'freelancer_project' AFTER status,
    ADD COLUMN IF NOT EXISTS show_on_homepage TINYINT(1) NOT NULL DEFAULT 0 AFTER project_type;

UPDATE live_projects SET project_type = 'live_project' WHERE project_type IS NULL OR project_type = '';
UPDATE assignments SET project_type = 'hands_on_project' WHERE project_type IS NULL OR project_type = '';
UPDATE freelance_projects SET project_type = 'freelancer_project' WHERE project_type IS NULL OR project_type = '';

UPDATE live_projects SET show_on_homepage = 0 WHERE show_on_homepage IS NULL;
UPDATE assignments SET show_on_homepage = 0 WHERE show_on_homepage IS NULL;
UPDATE freelance_projects SET show_on_homepage = 0 WHERE show_on_homepage IS NULL;
