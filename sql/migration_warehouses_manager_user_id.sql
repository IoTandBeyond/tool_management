-- Links warehouse to assigned manager user (canonical id on warehouse row).
-- Run once: mysql -u USER -p DB_NAME < sql/migration_warehouses_manager_user_id.sql

USE tool_management;

ALTER TABLE warehouses
  ADD COLUMN manager_user_id INT UNSIGNED NULL DEFAULT NULL AFTER manager_name,
  ADD INDEX idx_wh_manager_user (manager_user_id);

UPDATE warehouses w
INNER JOIN (
  SELECT warehouse_id, MIN(id) AS uid
  FROM users
  WHERE deleted_flag = 0 AND role = 'manager' AND warehouse_id IS NOT NULL
  GROUP BY warehouse_id
) m ON m.warehouse_id = w.id
SET w.manager_user_id = m.uid
WHERE w.deleted_flag = 0;

ALTER TABLE warehouses
  ADD CONSTRAINT fk_wh_manager_user FOREIGN KEY (manager_user_id) REFERENCES users (id) ON DELETE SET NULL;
