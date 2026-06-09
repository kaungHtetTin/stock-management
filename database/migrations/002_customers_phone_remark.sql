-- Migration: Add phone and remark to customers

SET @has_phone = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'phone'
);

SET @sql_phone = IF(@has_phone = 0,
    'ALTER TABLE customers ADD COLUMN phone VARCHAR(30) NULL AFTER customer_name',
    'SELECT 1');
PREPARE stmt FROM @sql_phone;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_remark = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'remark'
);

SET @sql_remark = IF(@has_remark = 0,
    'ALTER TABLE customers ADD COLUMN remark TEXT NULL AFTER address',
    'SELECT 1');
PREPARE stmt FROM @sql_remark;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
