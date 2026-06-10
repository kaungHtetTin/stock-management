-- Migration: Add contact_person to customers

SET @has_contact_person = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'customers'
      AND COLUMN_NAME = 'contact_person'
);

SET @sql_contact = IF(@has_contact_person = 0,
    'ALTER TABLE customers ADD COLUMN contact_person VARCHAR(100) NULL AFTER customer_name',
    'SELECT 1');
PREPARE stmt FROM @sql_contact;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
