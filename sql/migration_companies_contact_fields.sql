-- Run once on databases created before contact fields existed.
-- Skip if you already added these columns manually (ALTER will error if they exist).

USE tool_management;

ALTER TABLE companies
  ADD COLUMN address TEXT DEFAULT NULL AFTER name,
  ADD COLUMN telephone VARCHAR(64) DEFAULT NULL AFTER address,
  ADD COLUMN contact_name VARCHAR(200) DEFAULT NULL AFTER telephone,
  ADD COLUMN contact_phone VARCHAR(64) DEFAULT NULL AFTER contact_name;
