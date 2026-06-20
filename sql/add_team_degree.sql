-- Add degree/qualification column for team profiles (About Us)
ALTER TABLE company_team_members
    ADD COLUMN IF NOT EXISTS degree VARCHAR(150) DEFAULT NULL AFTER role_title;
