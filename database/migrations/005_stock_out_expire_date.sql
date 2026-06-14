-- Migration: Add expire_date to stock_out

SET @has_stock_out_expire_date = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_out'
      AND COLUMN_NAME = 'expire_date'
);

SET @sql_stock_out_expire_date = IF(@has_stock_out_expire_date = 0,
    'ALTER TABLE stock_out ADD COLUMN expire_date DATE NULL DEFAULT NULL AFTER mfd_date',
    'SELECT 1');
PREPARE stmt FROM @sql_stock_out_expire_date;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
