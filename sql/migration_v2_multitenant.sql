-- Upgrade path to multi-tenant schema (companies, warehouses, users, stock per warehouse).
--
-- If you can afford a reset: back up data, then import sql/schema.sql fresh and re-seed.
--
-- If you must migrate in place from an older single-tenant schema, adapt the steps below
-- to your actual table names. Typical v1 had: supervisors, tools (no company_id), operators
-- (no warehouse), transactions (no company_id/warehouse_id).
--
-- 1) Create new structural tables (run once; skip if already present):
--    companies, users, warehouses, tool_warehouse_assignment
--    ALTER existing: categories, operators, tools, transactions, activity_log
--
-- 2) Seed a default company and map all rows to company_id = 1.
--
-- 3) Create a default warehouse for that company; set operators.warehouse_id
--    and transactions.warehouse_id; backfill tool_warehouse_assignment from
--    tools + desired stock (e.g. copy old "in stock" counts).
--
-- 4) Migrate supervisors -> users (role admin or super_admin), set session to tm_user.
--
-- Example: add columns only if missing (MySQL 8+). Adjust names to match your DB.

USE tool_management;

-- Example: tools.image column (older DBs)
-- See migration_add_tool_image.sql

-- Example pattern for adding nullable columns before backfill:
-- ALTER TABLE transactions ADD COLUMN company_id INT UNSIGNED NULL AFTER id;
-- ALTER TABLE transactions ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER company_id;
-- UPDATE transactions SET company_id = 1, warehouse_id = 1 WHERE company_id IS NULL;
-- ALTER TABLE transactions MODIFY company_id INT UNSIGNED NOT NULL;
-- ALTER TABLE transactions MODIFY warehouse_id INT UNSIGNED NOT NULL;
-- ALTER TABLE transactions ADD CONSTRAINT fk_tx_company ... ;

-- After schema matches sql/schema.sql, verify:
-- SELECT COUNT(*) FROM tool_warehouse_assignment;
-- SELECT * FROM transactions LIMIT 5;
