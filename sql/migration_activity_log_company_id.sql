-- Run once if activity_feed / tm_log_activity fail with "Unknown column 'company_id'":
-- mysql -u USER -p DB_NAME < sql/migration_activity_log_company_id.sql

USE tool_management;

ALTER TABLE activity_log
  ADD COLUMN company_id INT UNSIGNED DEFAULT NULL AFTER id,
  ADD INDEX idx_activity_company (company_id);
