-- Run once if your database was created before tool images existed:
-- mysql -u root -p tool_management < sql/migration_add_tool_image.sql

USE tool_management;

ALTER TABLE tools
  ADD COLUMN image VARCHAR(512) DEFAULT NULL AFTER description;
