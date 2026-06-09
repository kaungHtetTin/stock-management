-- Migration: batch_ref for multi-item Stock In/Out submissions

SET @has_si_batch = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_in'
      AND COLUMN_NAME = 'batch_ref'
);

SET @sql_si = IF(@has_si_batch = 0,
    'ALTER TABLE stock_in ADD COLUMN batch_ref VARCHAR(36) NULL DEFAULT NULL AFTER id, ADD KEY idx_stock_in_batch (batch_ref)',
    'SELECT 1');
PREPARE stmt FROM @sql_si;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_so_batch = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_out'
      AND COLUMN_NAME = 'batch_ref'
);

SET @sql_so = IF(@has_so_batch = 0,
    'ALTER TABLE stock_out ADD COLUMN batch_ref VARCHAR(36) NULL DEFAULT NULL AFTER id, ADD KEY idx_stock_out_batch (batch_ref)',
    'SELECT 1');
PREPARE stmt FROM @sql_so;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
