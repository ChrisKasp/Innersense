CREATE TABLE IF NOT EXISTS workload_reference (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cleaning_package VARCHAR(120) NOT NULL,
    vehicle_type VARCHAR(120) NOT NULL,
    time_effort DECIMAL(8,2) NOT NULL,
    net_price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_workload_reference_cleaning_vehicle (cleaning_package, vehicle_type),
    KEY idx_workload_reference_cleaning_package (cleaning_package),
    KEY idx_workload_reference_vehicle_type (vehicle_type),
    CONSTRAINT fk_workload_reference_cleaning_package
        FOREIGN KEY (cleaning_package)
        REFERENCES cleaning_package (package_name)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_workload_reference_vehicle_type
        FOREIGN KEY (vehicle_type)
        REFERENCES vehicle_type_option (type_name)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
