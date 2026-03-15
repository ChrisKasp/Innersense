CREATE TABLE IF NOT EXISTS schedule_blocked_slot (
    slot_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (slot_date, slot_time),
    KEY idx_schedule_blocked_slot_date (slot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
