CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    company VARCHAR(190) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    consent TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NOT NULL DEFAULT '',
    street_address VARCHAR(190) NOT NULL DEFAULT '',
    postal_code VARCHAR(20) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_type_option (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vehicle_type_option_name (type_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_vehicle (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    brand VARCHAR(120) NOT NULL,
    model VARCHAR(120) NOT NULL,
    vehicle_type VARCHAR(120) NULL,
    license_plate VARCHAR(30) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customer_vehicle_customer_id (customer_id),
    KEY idx_customer_vehicle_customer_vehicle (customer_id, id),
    KEY idx_customer_vehicle_vehicle_type (vehicle_type),
    CONSTRAINT fk_customer_vehicle_customer
        FOREIGN KEY (customer_id)
        REFERENCES customer (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_customer_vehicle_vehicle_type_option
        FOREIGN KEY (vehicle_type)
        REFERENCES vehicle_type_option (type_name)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cleaning_package (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cleaning_package_name (package_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_request (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    customer_vehicle_id INT UNSIGNED NOT NULL,
    cleaning_package VARCHAR(120) NOT NULL,
    special_wishes TEXT NULL,
    preferred_date DATE NULL,
    preferred_time VARCHAR(50) NOT NULL DEFAULT '',
    status ENUM('new', 'contacted', 'scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customer_request_customer_id (customer_id),
    KEY idx_customer_request_vehicle_id (customer_vehicle_id),
    KEY idx_customer_request_cleaning_package (cleaning_package),
    CONSTRAINT fk_customer_request_customer
        FOREIGN KEY (customer_id)
        REFERENCES customer (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_customer_request_vehicle
        FOREIGN KEY (customer_vehicle_id)
        REFERENCES customer_vehicle (id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_customer_request_customer_vehicle_match
        FOREIGN KEY (customer_id, customer_vehicle_id)
        REFERENCES customer_vehicle (customer_id, id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_customer_request_cleaning_package_option
        FOREIGN KEY (cleaning_package)
        REFERENCES cleaning_package (package_name)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_blocked_slot (
    slot_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (slot_date, slot_time),
    KEY idx_schedule_blocked_slot_date (slot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
        ON DELETE RESTRICT
        ON UPDATE CASCADE,
    CONSTRAINT fk_workload_reference_vehicle_type
        FOREIGN KEY (vehicle_type)
        REFERENCES vehicle_type_option (type_name)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
