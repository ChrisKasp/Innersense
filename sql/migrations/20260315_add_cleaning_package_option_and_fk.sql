CREATE TABLE IF NOT EXISTS cleaning_package (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cleaning_package_name (package_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE customer_request
SET cleaning_package = 'Innen- und Außenreinigung'
WHERE cleaning_package = 'Innen- und Aussenreinigung';

UPDATE customer_request
SET cleaning_package = 'Innenreinigung Basic'
WHERE TRIM(COALESCE(cleaning_package, '')) = '';

INSERT INTO cleaning_package (package_name, sort_order, is_active)
VALUES
    ('Innenreinigung Basic', 10, 1),
    ('Innen- und Außenreinigung', 20, 1),
    ('Premium Aufbereitung', 30, 1),
    ('Komplettaufbereitung', 40, 1),
    ('Politur / Lackpflege', 50, 1)
ON DUPLICATE KEY UPDATE
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

INSERT INTO cleaning_package (package_name, sort_order, is_active)
SELECT DISTINCT
    cr.cleaning_package AS package_name,
    999,
    1
FROM customer_request cr
LEFT JOIN cleaning_package cp ON cp.package_name = cr.cleaning_package
WHERE cr.cleaning_package IS NOT NULL
  AND TRIM(cr.cleaning_package) <> ''
  AND cp.id IS NULL;

ALTER TABLE customer_request
    ADD KEY idx_customer_request_cleaning_package (cleaning_package);

ALTER TABLE customer_request
    ADD CONSTRAINT fk_customer_request_cleaning_package_option
    FOREIGN KEY (cleaning_package)
    REFERENCES cleaning_package (package_name)
    ON UPDATE CASCADE
    ON DELETE RESTRICT;
