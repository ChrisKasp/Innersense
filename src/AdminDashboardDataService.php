<?php

declare(strict_types=1);

/**
 * @return array{rows:list<array<string,mixed>>, error:string}
 */
function loadAdminCustomerRows(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT c.id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    c.phone,
                    c.customer_typ,
                    c.company_name,
                    c.street_address,
                    c.postal_code,
                    c.city,
                    c.created_at,
                    COUNT(cv.id) AS vehicle_count
             FROM customer c
             LEFT JOIN customer_vehicle cv ON cv.customer_id = c.id
             GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.customer_typ, c.company_name, c.street_address, c.postal_code, c.city, c.created_at
             ORDER BY c.created_at DESC, c.id DESC"
        );
        $rows = $stmt->fetchAll();

        return [
            'rows' => is_array($rows) ? $rows : [],
            'error' => '',
        ];
    } catch (Throwable $exception) {
        return [
            'rows' => [],
            'error' => 'Kundendaten konnten nicht geladen werden.',
        ];
    }
}

/**
 * @return array{rows:list<array<string,mixed>>, error:string}
 */
function loadAdminRequestRows(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT
                cr.id,
                COALESCE(cp.package_name, cr.cleaning_package) AS cleaning_package,
                cr.preferred_date,
                cr.status,
                cr.created_at,
                c.first_name,
                c.last_name
             FROM customer_request cr
             LEFT JOIN customer c ON c.id = cr.customer_id
             LEFT JOIN cleaning_package cp ON cp.package_name = cr.cleaning_package
             ORDER BY cr.created_at DESC, cr.id DESC"
        );
        $rows = $stmt->fetchAll();

        return [
            'rows' => is_array($rows) ? $rows : [],
            'error' => '',
        ];
    } catch (Throwable $exception) {
        return [
            'rows' => [],
            'error' => 'Anfragedaten konnten nicht geladen werden.',
        ];
    }
}

/**
 * @return array{rows:list<array<string,mixed>>, error:string}
 */
function loadAdminVehicleRows(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT
                cv.id,
                cv.brand,
                cv.model,
                COALESCE(vto.type_name, cv.vehicle_type) AS vehicle_type,
                cv.license_plate,
                c.first_name,
                c.last_name
             FROM customer_vehicle cv
             LEFT JOIN customer c ON c.id = cv.customer_id
             LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
             ORDER BY cv.created_at DESC, cv.id DESC"
        );
        $rows = $stmt->fetchAll();

        return [
            'rows' => is_array($rows) ? $rows : [],
            'error' => '',
        ];
    } catch (Throwable $exception) {
        return [
            'rows' => [],
            'error' => 'Fahrzeugdaten konnten nicht geladen werden.',
        ];
    }
}

/**
 * @return array{rows:list<array<string,mixed>>, error:string}
 */
function loadAdminAppointmentRows(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT
                cr.id,
                cr.preferred_date,
                cr.preferred_time,
                wr.time_effort,
                COALESCE(cp.package_name, cr.cleaning_package) AS cleaning_package,
                cr.status,
                c.first_name,
                c.last_name,
                cv.brand,
                cv.model,
                COALESCE(vto.type_name, cv.vehicle_type) AS vehicle_type
             FROM customer_request cr
             LEFT JOIN customer c ON c.id = cr.customer_id
             LEFT JOIN customer_vehicle cv ON cv.id = cr.customer_vehicle_id
             LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
             LEFT JOIN cleaning_package cp ON cp.package_name = cr.cleaning_package
             LEFT JOIN workload_reference wr
                ON wr.cleaning_package = COALESCE(cp.package_name, cr.cleaning_package)
                AND wr.vehicle_type = COALESCE(vto.type_name, cv.vehicle_type)
             ORDER BY
                cr.preferred_date IS NULL,
                cr.preferred_date ASC,
                cr.preferred_time ASC,
                cr.id DESC"
        );

        $rows = $stmt->fetchAll();
        return [
            'rows' => is_array($rows) ? $rows : [],
            'error' => '',
        ];
    } catch (Throwable $exception) {
        return [
            'rows' => [],
            'error' => 'Termindaten konnten nicht geladen werden.',
        ];
    }
}

function getAdminPageHitCount(PDO $pdo, string $pageKey): int
{
    try {
        $stmt = $pdo->prepare('SELECT hit_count FROM site_page_hit WHERE page_key = :page_key LIMIT 1');
        $stmt->execute([':page_key' => $pageKey]);
        $row = $stmt->fetch();

        return is_array($row) ? max(0, (int) ($row['hit_count'] ?? 0)) : 0;
    } catch (Throwable $exception) {
        return 0;
    }
}
