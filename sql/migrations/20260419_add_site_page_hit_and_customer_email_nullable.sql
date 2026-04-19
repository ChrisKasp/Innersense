-- Migration: add site_page_hit and make customer.email nullable
-- Date: 2026-04-19

CREATE TABLE IF NOT EXISTS site_page_hit (
    page_key VARCHAR(64) NOT NULL,
    hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customer
    MODIFY COLUMN email VARCHAR(190) NULL;

UPDATE customer
SET email = NULL
WHERE TRIM(COALESCE(email, '')) = '';
