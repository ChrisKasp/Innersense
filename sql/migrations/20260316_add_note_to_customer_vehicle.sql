-- Migration: add note to customer_vehicle
-- Date: 2026-03-16

ALTER TABLE customer_vehicle
    ADD COLUMN IF NOT EXISTS note TEXT NULL AFTER license_plate;

-- Optional check
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'customer_vehicle'
  AND COLUMN_NAME = 'note';
