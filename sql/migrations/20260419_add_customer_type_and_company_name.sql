-- Migration: align customer table with admin/customer UI fields
-- Date: 2026-04-19

SET @stmt = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer'
          AND COLUMN_NAME = 'customer_typ'
    ) = 0,
    "ALTER TABLE customer ADD COLUMN customer_typ ENUM('Privatperson', 'Firma') NOT NULL DEFAULT 'Privatperson' AFTER phone",
    'SELECT 1'
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @stmt = IF(
    (
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'customer'
          AND COLUMN_NAME = 'company_name'
    ) = 0,
    "ALTER TABLE customer ADD COLUMN company_name VARCHAR(190) NOT NULL DEFAULT '' AFTER customer_typ",
    'SELECT 1'
);
PREPARE migration_stmt FROM @stmt;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

UPDATE customer
SET customer_typ = 'Privatperson'
WHERE TRIM(COALESCE(customer_typ, '')) = '';
