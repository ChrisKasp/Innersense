-- Make customer_vehicle.vehicle_type reference vehicle_type_option.type_name.
-- This keeps existing application payloads unchanged (vehicle_type remains a string).

ALTER TABLE customer_vehicle
    MODIFY COLUMN vehicle_type VARCHAR(120) NULL;

INSERT INTO vehicle_type_option (type_name, sort_order, is_active)
SELECT DISTINCT
    cv.vehicle_type AS type_name,
    999,
    1
FROM customer_vehicle cv
LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
WHERE cv.vehicle_type IS NOT NULL
  AND cv.vehicle_type <> ''
  AND vto.id IS NULL;

ALTER TABLE customer_vehicle
    ADD KEY idx_customer_vehicle_vehicle_type (vehicle_type);

ALTER TABLE customer_vehicle
    ADD CONSTRAINT fk_customer_vehicle_vehicle_type_option
    FOREIGN KEY (vehicle_type)
    REFERENCES vehicle_type_option (type_name)
    ON UPDATE CASCADE
    ON DELETE SET NULL;
