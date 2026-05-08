-- Warehouse Tool Management — multi-tenant schema (companies, warehouses, users, stock)
-- Import: mysql -u root -p < sql/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS tool_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tool_management;

DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS tool_warehouse_assignment;
DROP TABLE IF EXISTS tools;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS operators;
DROP TABLE IF EXISTS warehouses;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS companies;

CREATE TABLE companies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  address TEXT,
  telephone VARCHAR(64) DEFAULT NULL,
  contact_name VARCHAR(200) DEFAULT NULL,
  contact_phone VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_flag TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_name (name)
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED DEFAULT NULL,
  warehouse_id INT UNSIGNED DEFAULT NULL,
  role ENUM('super_admin','admin','manager') NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(200) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_flag TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_email (email),
  INDEX idx_co (company_id),
  INDEX idx_wh (warehouse_id),
  CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE warehouses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  warehouse_name VARCHAR(200) NOT NULL,
  warehouse_address TEXT,
  manager_name VARCHAR(200) DEFAULT NULL,
  user_id INT UNSIGNED DEFAULT NULL COMMENT 'User who created the warehouse',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_flag TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_wh_co (company_id),
  CONSTRAINT fk_wh_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE users
  ADD CONSTRAINT fk_users_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_co_name (company_id, name),
  CONSTRAINT fk_cat_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE operators (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  employee_id VARCHAR(64) NOT NULL,
  department VARCHAR(120) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_op_co_emp (company_id, employee_id),
  INDEX idx_wh (warehouse_id),
  CONSTRAINT fk_op_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_op_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tools (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  barcode VARCHAR(128) NOT NULL,
  nfc_id VARCHAR(128) DEFAULT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  description TEXT,
  image VARCHAR(512) DEFAULT NULL,
  missing_flag TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tool_co_bc (company_id, barcode),
  UNIQUE KEY uq_tool_co_nfc (company_id, nfc_id),
  INDEX idx_co (company_id),
  CONSTRAINT fk_tools_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tools_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tool_warehouse_assignment (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tool_id INT UNSIGNED NOT NULL,
  company_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_flag TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_tool_wh (tool_id, warehouse_id),
  INDEX idx_wh (warehouse_id),
  CONSTRAINT fk_twa_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
  CONSTRAINT fk_twa_co FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_twa_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  warehouse_id INT UNSIGNED NOT NULL,
  tool_id INT UNSIGNED NOT NULL,
  operator_id INT UNSIGNED NOT NULL,
  checkout_at DATETIME NOT NULL,
  checkin_at DATETIME DEFAULT NULL,
  expected_return_at DATETIME NOT NULL,
  CONSTRAINT fk_tx_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_wh FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tx_operator FOREIGN KEY (operator_id) REFERENCES operators(id) ON DELETE RESTRICT,
  INDEX idx_open (checkin_at),
  INDEX idx_co (company_id),
  INDEX idx_wh (warehouse_id)
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED DEFAULT NULL,
  event_type VARCHAR(64) NOT NULL,
  message VARCHAR(512) NOT NULL,
  meta TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed password for all demo users: password
INSERT INTO companies (name) VALUES ('Demo Company');

INSERT INTO users (company_id, warehouse_id, role, email, password_hash, name) VALUES
  (NULL, NULL, 'super_admin', 'super@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin'),
  (1, NULL, 'admin', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Company Admin');

INSERT INTO warehouses (company_id, warehouse_name, warehouse_address, user_id) VALUES
  (1, 'Main Warehouse', '100 Industrial Way', 2);

INSERT INTO users (company_id, warehouse_id, role, email, password_hash, name) VALUES
  (1, 1, 'manager', 'manager@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Warehouse Manager');

INSERT INTO categories (company_id, name) VALUES
  (1, 'Hand tools'), (1, 'Power tools'), (1, 'Measuring'), (1, 'Safety'), (1, 'Other');

INSERT INTO operators (company_id, warehouse_id, name, employee_id, department) VALUES
  (1, 1, 'Demo Operator', 'OP-001', 'Maintenance'),
  (1, 1, 'Jane Smith', 'OP-002', 'Assembly');

INSERT INTO tools (company_id, name, barcode, nfc_id, category_id, description, missing_flag, is_active) VALUES
  (1, 'Cordless drill', 'BC-DRILL-001', 'NFC-DRILL-001', 2, '18V cordless drill', 0, 1),
  (1, 'Torque wrench', 'BC-TW-001', NULL, 1, '20-200 Nm', 0, 1),
  (1, 'Laser measure', 'BC-LM-001', 'NFC-LM-001', 3, NULL, 0, 1);

INSERT INTO tool_warehouse_assignment (tool_id, company_id, warehouse_id, stock_qty) VALUES
  (1, 1, 1, 5),
  (2, 1, 1, 3),
  (3, 1, 1, 2);

</think>


<｜tool▁calls▁begin｜><｜tool▁call▁begin｜>
StrReplace