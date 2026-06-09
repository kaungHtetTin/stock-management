-- Migration: Item categories table (replaces ENUM on items.category)

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

INSERT IGNORE INTO categories (name, sort_order) VALUES
    ('Fruits', 1),
    ('Gelato', 2),
    ('Icecream', 3);

SET @has_category_id = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'category_id'
);

SET @sql_add_col = IF(@has_category_id = 0,
    'ALTER TABLE items ADD COLUMN category_id INT UNSIGNED NULL AFTER unit_price',
    'SELECT 1');
PREPARE stmt FROM @sql_add_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_migrate = IF(@has_category_id = 0,
    'UPDATE items i INNER JOIN categories c ON c.name = i.category SET i.category_id = c.id',
    'SELECT 1');
PREPARE stmt FROM @sql_migrate;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_category_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND COLUMN_NAME = 'category'
);

SET @sql_drop_enum = IF(@has_category_col > 0,
    'ALTER TABLE items DROP COLUMN category',
    'SELECT 1');
PREPARE stmt FROM @sql_drop_enum;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql_not_null = IF(@has_category_id = 0,
    'ALTER TABLE items MODIFY category_id INT UNSIGNED NOT NULL',
    'SELECT 1');
PREPARE stmt FROM @sql_not_null;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND CONSTRAINT_NAME = 'fk_items_category'
);

SET @sql_fk = IF(@has_fk = 0,
    'ALTER TABLE items ADD CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_idx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'items'
      AND INDEX_NAME = 'idx_items_category_id'
);

SET @sql_idx = IF(@has_idx = 0,
    'ALTER TABLE items ADD KEY idx_items_category_id (category_id)',
    'SELECT 1');
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
