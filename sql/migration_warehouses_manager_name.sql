-- Run once if warehouses lacks manager_name:
-- mysql -u USER -p DB_NAME < sql/migration_warehouses_manager_name.sql

USE tool_management;

ALTER TABLE warehouses
  ADD COLUMN manager_name VARCHAR(200) DEFAULT NULL AFTER warehouse_address;
