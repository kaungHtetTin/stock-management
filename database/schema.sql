-- =============================================================================
-- Stock Management System — Database Schema (Phase 0)
-- YUKIOH MYANMAR CO.,LTD
-- Run: mysql -u root -p < database/schema.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS stock_manage
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE stock_manage;

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    display_name    VARCHAR(100) NOT NULL,
    role            ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    status          ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- categories (item product categories)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50)  NOT NULL,
    sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_categories_name (name),
    KEY idx_categories_sort (sort_order),
    KEY idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- items (product master)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_no         VARCHAR(30)  NOT NULL,
    item_name       VARCHAR(150) NOT NULL,
    unit            VARCHAR(20)  NOT NULL,
    unit_price      DECIMAL(12, 2) NULL DEFAULT NULL,
    category_id     INT UNSIGNED NOT NULL,
    remark          TEXT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_items_item_no (item_no),
    KEY idx_items_category_id (category_id),
    KEY idx_items_name (item_name),
    KEY idx_items_active (is_active),
    KEY fk_items_created_by (created_by),

    CONSTRAINT fk_items_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_items_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- customers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code   VARCHAR(30)  NOT NULL,
    customer_name   VARCHAR(150) NOT NULL,
    contact_person  VARCHAR(100) NULL,
    phone           VARCHAR(30)  NULL,
    address         TEXT NULL,
    remark          TEXT NULL,
    customer_type   ENUM('Retail', 'Whole Sale') NOT NULL DEFAULT 'Retail',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_customers_code (customer_code),
    KEY idx_customers_name (customer_name),
    KEY idx_customers_type (customer_type),
    KEY idx_customers_active (is_active),
    KEY fk_customers_created_by (created_by),

    CONSTRAINT fk_customers_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- stock_in
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_in (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_ref           VARCHAR(36) NULL DEFAULT NULL,
    item_id             INT UNSIGNED NOT NULL,
    mfd_date            DATE NULL DEFAULT NULL,
    expire_date         DATE NULL DEFAULT NULL,
    lot_no              VARCHAR(50) NULL DEFAULT NULL,
    qty                 DECIMAL(12, 2) NOT NULL,
    unit                VARCHAR(20)  NOT NULL,
    worker_qty          DECIMAL(12, 2) NULL DEFAULT NULL,
    in_charge_name      VARCHAR(100) NOT NULL,
    status              ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason    TEXT NULL,
    created_by          INT UNSIGNED NOT NULL,
    approved_by         INT UNSIGNED NULL DEFAULT NULL,
    approved_at         DATETIME NULL DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_stock_in_item (item_id),
    KEY idx_stock_in_batch (batch_ref),
    KEY idx_stock_in_status (status),
    KEY idx_stock_in_created_at (created_at),
    KEY idx_stock_in_lot (lot_no),
    KEY fk_stock_in_created_by (created_by),
    KEY fk_stock_in_approved_by (approved_by),

    CONSTRAINT fk_stock_in_item
        FOREIGN KEY (item_id) REFERENCES items (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_in_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_in_approved_by
        FOREIGN KEY (approved_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT chk_stock_in_qty_positive CHECK (qty > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- stock_out
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_out (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_ref           VARCHAR(36) NULL DEFAULT NULL,
    item_id             INT UNSIGNED NOT NULL,
    customer_id         INT UNSIGNED NOT NULL,
    mfd_date            DATE NULL DEFAULT NULL,
    expire_date         DATE NULL DEFAULT NULL,
    qty                 DECIMAL(12, 2) NOT NULL,
    unit                VARCHAR(20)  NOT NULL,
    reason              ENUM('Sales', 'Sample', 'Sale & Marketing', 'Other') NOT NULL,
    remark              TEXT NULL,
    status              ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    rejection_reason    TEXT NULL,
    created_by          INT UNSIGNED NOT NULL,
    approved_by         INT UNSIGNED NULL DEFAULT NULL,
    approved_at         DATETIME NULL DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_stock_out_item (item_id),
    KEY idx_stock_out_batch (batch_ref),
    KEY idx_stock_out_customer (customer_id),
    KEY idx_stock_out_status (status),
    KEY idx_stock_out_reason (reason),
    KEY idx_stock_out_created_at (created_at),
    KEY fk_stock_out_created_by (created_by),
    KEY fk_stock_out_approved_by (approved_by),

    CONSTRAINT fk_stock_out_item
        FOREIGN KEY (item_id) REFERENCES items (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_out_customer
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_out_created_by
        FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_stock_out_approved_by
        FOREIGN KEY (approved_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,

    CONSTRAINT chk_stock_out_qty_positive CHECK (qty > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
