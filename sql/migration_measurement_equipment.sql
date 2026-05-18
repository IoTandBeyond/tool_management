-- Measurement equipment, maintenance workflow, consumable vs returnable assets.
-- mysql -u USER -p DB_NAME < sql/migration_measurement_equipment.sql

USE tool_management;

ALTER TABLE tools
  ADD COLUMN asset_type ENUM('consumable', 'measurement') NOT NULL DEFAULT 'consumable' AFTER company_id,
  ADD COLUMN uom VARCHAR(64) DEFAULT NULL AFTER description,
  ADD COLUMN range_spec VARCHAR(128) DEFAULT NULL AFTER uom,
  ADD COLUMN brand_model VARCHAR(200) DEFAULT NULL AFTER range_spec,
  ADD COLUMN tool_condition VARCHAR(64) DEFAULT NULL AFTER brand_model,
  ADD COLUMN location VARCHAR(200) DEFAULT NULL AFTER tool_condition,
  ADD COLUMN last_maintenance DATE DEFAULT NULL AFTER location,
  ADD COLUMN maintenance_status ENUM('available', 'in_maintenance') NOT NULL DEFAULT 'available' AFTER last_maintenance;

CREATE TABLE IF NOT EXISTS tool_maintenance (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tool_id INT UNSIGNED NOT NULL,
  owner_company_id INT UNSIGNED NOT NULL,
  provider_company_id INT UNSIGNED NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  returned_at DATETIME DEFAULT NULL,
  notes TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  INDEX idx_tm_tool (tool_id),
  INDEX idx_tm_open (tool_id, returned_at),
  INDEX idx_tm_provider (provider_company_id),
  CONSTRAINT fk_tm_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tm_owner FOREIGN KEY (owner_company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tm_provider FOREIGN KEY (provider_company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tm_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
