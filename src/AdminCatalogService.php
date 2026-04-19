<?php

declare(strict_types=1);

function ensureVehicleTypeTable(PDO $pdo): void
{
    $defaultTypes = ['Kleinwagen', 'Limousine', 'SUV', 'Kombi', 'Van'];
    $sort = 10;
    $stmt = $pdo->prepare(
        'INSERT INTO vehicle_type_option (type_name, sort_order, is_active)
         VALUES (:type_name, :sort_order, 1)
         ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)'
    );

    foreach ($defaultTypes as $defaultType) {
        $stmt->execute([
            ':type_name' => $defaultType,
            ':sort_order' => $sort,
        ]);
        $sort += 10;
    }
}

function ensureCleaningPackageTable(PDO $pdo): void
{
    $defaultPackages = [
        'Innenreinigung Basic',
        'Innen- und Außenreinigung',
        'Premium Aufbereitung',
        'Komplettaufbereitung',
        'Politur / Lackpflege',
    ];
    $sort = 10;
    $stmt = $pdo->prepare(
        'INSERT INTO cleaning_package (package_name, sort_order, is_active)
         VALUES (:package_name, :sort_order, 1)
         ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)'
    );

    foreach ($defaultPackages as $defaultPackage) {
        $stmt->execute([
            ':package_name' => $defaultPackage,
            ':sort_order' => $sort,
        ]);
        $sort += 10;
    }
}

function ensureWorkloadReferenceTable(PDO $pdo): void
{
    // Table is expected to be provided by SQL schema/migrations.
}

/**
 * @return list<array{type_name:string, sort_order:int, is_active:int}>
 */
function getVehicleTypes(PDO $pdo): array
{
    try {
        ensureVehicleTypeTable($pdo);
        $stmt = $pdo->query(
            'SELECT type_name, sort_order, is_active
             FROM vehicle_type_option
             ORDER BY sort_order ASC, type_name ASC'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $exception) {
        return [];
    }
}

function addVehicleType(PDO $pdo, string $typeName): bool
{
    try {
        ensureVehicleTypeTable($pdo);
        $maxSortStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM vehicle_type_option');
        $maxSortRow = $maxSortStmt->fetch();
        $nextSort = (int) ($maxSortRow['max_sort'] ?? 0) + 10;

        $stmt = $pdo->prepare(
            'INSERT INTO vehicle_type_option (type_name, sort_order, is_active)
             VALUES (:type_name, :sort_order, 1)
             ON DUPLICATE KEY UPDATE is_active = 1'
        );
        return $stmt->execute([
            ':type_name' => $typeName,
            ':sort_order' => $nextSort,
        ]);
    } catch (Throwable $exception) {
        return false;
    }
}

/**
 * @return list<array{package_name:string, sort_order:int, is_active:int}>
 */
function getCleaningPackages(PDO $pdo): array
{
    try {
        ensureCleaningPackageTable($pdo);
        $stmt = $pdo->query(
            'SELECT package_name, sort_order, is_active
             FROM cleaning_package
             ORDER BY sort_order ASC, package_name ASC'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $exception) {
        return [];
    }
}

function addCleaningPackage(PDO $pdo, string $packageName): bool
{
    try {
        ensureCleaningPackageTable($pdo);
        $maxSortStmt = $pdo->query('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM cleaning_package');
        $maxSortRow = $maxSortStmt->fetch();
        $nextSort = (int) ($maxSortRow['max_sort'] ?? 0) + 10;

        $stmt = $pdo->prepare(
            'INSERT INTO cleaning_package (package_name, sort_order, is_active)
             VALUES (:package_name, :sort_order, 1)
             ON DUPLICATE KEY UPDATE is_active = 1'
        );
        return $stmt->execute([
            ':package_name' => $packageName,
            ':sort_order' => $nextSort,
        ]);
    } catch (Throwable $exception) {
        return false;
    }
}

/**
 * @return list<array{id:int, cleaning_package:string, vehicle_type:string, time_effort:string, net_price:string}>
 */
function getWorkloadReferences(PDO $pdo): array
{
    try {
        ensureVehicleTypeTable($pdo);
        ensureCleaningPackageTable($pdo);
        ensureWorkloadReferenceTable($pdo);
        $stmt = $pdo->query(
            'SELECT id, cleaning_package, vehicle_type, time_effort, net_price
             FROM workload_reference
             ORDER BY cleaning_package ASC, vehicle_type ASC'
        );
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $exception) {
        return [];
    }
}

function addWorkloadReference(PDO $pdo, string $cleaningPackage, string $vehicleType, float $timeEffort, float $netPrice): bool
{
    try {
        ensureVehicleTypeTable($pdo);
        ensureCleaningPackageTable($pdo);
        ensureWorkloadReferenceTable($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO workload_reference (cleaning_package, vehicle_type, time_effort, net_price)
             VALUES (:cleaning_package, :vehicle_type, :time_effort, :net_price)
             ON DUPLICATE KEY UPDATE
                 time_effort = VALUES(time_effort),
                 net_price = VALUES(net_price)'
        );

        return $stmt->execute([
            ':cleaning_package' => $cleaningPackage,
            ':vehicle_type' => $vehicleType,
            ':time_effort' => round($timeEffort, 2),
            ':net_price' => round($netPrice, 2),
        ]);
    } catch (Throwable $exception) {
        return false;
    }
}

function deleteVehicleType(PDO $pdo, string $typeName, ?string $vehicleTable): bool
{
    try {
        if ($vehicleTable !== null) {
            $checkStmt = $pdo->prepare(
                'SELECT COUNT(*) AS cnt
                 FROM ' . $vehicleTable . '
                 WHERE vehicle_type = :type_name'
            );
            $checkStmt->execute([':type_name' => $typeName]);
            $row = $checkStmt->fetch();
            if ((int) ($row['cnt'] ?? 0) > 0) {
                return false;
            }
        }

        $stmt = $pdo->prepare('DELETE FROM vehicle_type_option WHERE type_name = :type_name');
        $stmt->execute([':type_name' => $typeName]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function deleteCleaningPackage(PDO $pdo, string $packageName, ?string $requestTable): bool
{
    try {
        if ($requestTable !== null) {
            $checkStmt = $pdo->prepare(
                'SELECT COUNT(*) AS cnt
                 FROM ' . $requestTable . '
                 WHERE cleaning_package = :package_name'
            );
            $checkStmt->execute([':package_name' => $packageName]);
            $row = $checkStmt->fetch();
            if ((int) ($row['cnt'] ?? 0) > 0) {
                return false;
            }
        }

        $stmt = $pdo->prepare('DELETE FROM cleaning_package WHERE package_name = :package_name');
        $stmt->execute([':package_name' => $packageName]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function deleteWorkloadReference(PDO $pdo, int $referenceId): bool
{
    try {
        $stmt = $pdo->prepare('DELETE FROM workload_reference WHERE id = :id');
        $stmt->execute([':id' => $referenceId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $exception) {
        return false;
    }
}

function completeRequestById(PDO $pdo, int $requestId): bool
{
    try {
        $selectStmt = $pdo->prepare('SELECT status FROM customer_request WHERE id = :id LIMIT 1');
        $selectStmt->execute([':id' => $requestId]);
        $row = $selectStmt->fetch();
        if (!is_array($row)) {
            return false;
        }

        $currentStatus = strtolower(trim((string) ($row['status'] ?? '')));
        if ($currentStatus === 'completed') {
            return true;
        }

        $updateStmt = $pdo->prepare('UPDATE customer_request SET status = :status WHERE id = :id');
        $updateStmt->execute([
            ':status' => 'completed',
            ':id' => $requestId,
        ]);
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}
