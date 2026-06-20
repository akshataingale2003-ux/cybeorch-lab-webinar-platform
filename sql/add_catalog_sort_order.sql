-- Add sort_order to catalog tables created before the column existed

ALTER TABLE live_projects
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER show_on_homepage;

ALTER TABLE assignments
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER show_on_homepage;

ALTER TABLE freelance_projects
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0 AFTER show_on_homepage;
