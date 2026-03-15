CREATE TABLE IF NOT EXISTS vehicle_type_option (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vehicle_type_option_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO vehicle_type_option (type_name, sort_order, is_active)
VALUES
    ('Kleinwagen', 10, 1),
    ('Limousine', 20, 1),
    ('SUV', 30, 1),
    ('Kombi', 40, 1),
    ('Van', 50, 1)
ON DUPLICATE KEY UPDATE
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);
