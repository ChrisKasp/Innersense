<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/database.php';

$idleTimeoutSeconds = 600;
$vehicleId = max(0, (int) ($_GET['id'] ?? $_POST['vehicle_id'] ?? 0));
$csrfToken = (string) ($_SESSION['verwaltung_csrf'] ?? '');
$detailNotice = '';
$detailError = '';
$vehicleDetail = null;
$customerOptions = [];
$vehicleBrandOptions = [];
$recordDeleted = false;

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $detailNotice = 'Fahrzeug wurde erfolgreich neu erfasst.';
}
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $detailNotice = 'Fahrzeugdaten wurden erfolgreich gespeichert.';
}

if (!isset($_SESSION['verwaltung_authenticated']) || $_SESSION['verwaltung_authenticated'] !== true) {
    header('Location: verwaltung.php');
    exit;
}

$lastActivity = (int) ($_SESSION['verwaltung_last_activity'] ?? 0);
if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeoutSeconds) {
    $_SESSION['verwaltung_authenticated'] = false;
    unset($_SESSION['verwaltung_last_activity']);
    header('Location: verwaltung.php');
    exit;
}
$_SESSION['verwaltung_last_activity'] = time();

if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(24));
    $_SESSION['verwaltung_csrf'] = $csrfToken;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isReferenceDeleteError(Throwable $exception): bool
{
    if ($exception instanceof PDOException) {
        $sqlState = (string) $exception->getCode();
        if ($sqlState === '23000') {
            return true;
        }
    }

    return stripos($exception->getMessage(), 'foreign key') !== false;
}

function detectVehicleTable(PDO $pdo): ?string
{
    $candidates = ['customer_vehicles', 'customer_vehicle'];
    foreach ($candidates as $tableName) {
        try {
            $pdo->query('SELECT id FROM ' . $tableName . ' LIMIT 1');
            return $tableName;
        } catch (Throwable $exception) {
            continue;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function loadVehicleTypeOptions(PDO $pdo): array
{
    $fallback = ['Kleinwagen', 'Limousine', 'SUV', 'Kombi', 'Van'];

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS vehicle_type_option (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                type_name VARCHAR(120) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_vehicle_type_option_name (type_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $stmt = $pdo->query(
            'SELECT type_name
             FROM vehicle_type_option
             WHERE is_active = 1
             ORDER BY sort_order ASC, type_name ASC'
        );
        $rows = $stmt->fetchAll();
        $options = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $name = trim((string) ($row['type_name'] ?? ''));
                if ($name !== '') {
                    $options[] = $name;
                }
            }
        }

        return $options !== [] ? $options : $fallback;
    } catch (Throwable $exception) {
        return $fallback;
    }
}

/**
 * @return list<string>
 */
function loadVehicleBrandOptions(PDO $pdo, string $vehicleTable): array
{
    try {
        $stmt = $pdo->query(
            'SELECT brand
             FROM ' . $vehicleTable . '
             WHERE TRIM(COALESCE(brand, "")) <> ""
             ORDER BY brand ASC'
        );
        $rows = $stmt->fetchAll();
        $options = [];
        $seen = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $brand = trim((string) ($row['brand'] ?? ''));
                if ($brand === '') {
                    continue;
                }

                $dedupeKey = strtolower($brand);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }

                $seen[$dedupeKey] = true;
                $options[] = $brand;
            }
        }

        return $options;
    } catch (Throwable $exception) {
        return [];
    }
}

$pdo = db();
$vehicleTable = detectVehicleTable($pdo);

if ($vehicleTable === null) {
    $detailError = 'Fahrzeugtabelle wurde nicht gefunden.';
}

try {
    $stmt = $pdo->query('SELECT id, first_name, last_name FROM customer ORDER BY first_name ASC, last_name ASC, id ASC');
    $rows = $stmt->fetchAll();
    $customerOptions = is_array($rows) ? $rows : [];
} catch (Throwable $exception) {
    $customerOptions = [];
}

if (
    $vehicleTable !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'update_vehicle'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    $customerId = max(0, (int) ($_POST['customer_id'] ?? 0));
    $brand = trim((string) ($_POST['brand'] ?? ''));
    $model = trim((string) ($_POST['model'] ?? ''));
    $vehicleType = trim((string) ($_POST['vehicle_type'] ?? ''));
    $licensePlate = trim((string) ($_POST['license_plate'] ?? ''));

    $allowedTypes = loadVehicleTypeOptions($pdo);
    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($customerId <= 0) {
        $detailError = 'Bitte einen Kunden auswählen.';
    } elseif ($brand === '' || $model === '') {
        $detailError = 'Marke und Modell sind erforderlich.';
    } elseif ($vehicleType !== '' && !in_array($vehicleType, $allowedTypes, true)) {
        $detailError = 'Der ausgewählte Fahrzeugtyp ist ungültig.';
    } else {
        try {
            if ($vehicleId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE ' . $vehicleTable . '
                     SET customer_id = :customer_id,
                         brand = :brand,
                         model = :model,
                         vehicle_type = :vehicle_type,
                         license_plate = :license_plate
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':customer_id' => $customerId,
                    ':brand' => $brand,
                    ':model' => $model,
                    ':vehicle_type' => ($vehicleType !== '' ? $vehicleType : null),
                    ':license_plate' => $licensePlate,
                    ':id' => $vehicleId,
                ]);

                header('Location: vehicle_detail.php?id=' . $vehicleId . '&saved=1');
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO ' . $vehicleTable . ' (customer_id, brand, model, vehicle_type, license_plate)
                 VALUES (:customer_id, :brand, :model, :vehicle_type, :license_plate)'
            );
            $stmt->execute([
                ':customer_id' => $customerId,
                ':brand' => $brand,
                ':model' => $model,
                ':vehicle_type' => ($vehicleType !== '' ? $vehicleType : null),
                ':license_plate' => $licensePlate,
            ]);

            $newVehicleId = (int) $pdo->lastInsertId();
            if ($newVehicleId > 0) {
                header('Location: vehicle_detail.php?id=' . $newVehicleId . '&created=1');
                exit;
            }

            $detailNotice = 'Fahrzeug wurde erfolgreich neu erfasst.';
        } catch (Throwable $exception) {
            $detailError = 'Fahrzeugdaten konnten nicht gespeichert werden.';
        }
    }
}

if (
    $vehicleTable !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'delete_vehicle'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($vehicleId <= 0) {
        $detailError = 'Ungültige Fahrzeugauswahl.';
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM ' . $vehicleTable . ' WHERE id = :id');
            $stmt->execute([':id' => $vehicleId]);

            if ($stmt->rowCount() > 0) {
                $detailNotice = 'Fahrzeug wurde erfolgreich gelöscht.';
                $recordDeleted = true;
                $vehicleDetail = null;
            } else {
                $detailError = 'Fahrzeug konnte nicht gelöscht werden.';
            }
        } catch (Throwable $exception) {
            if (isReferenceDeleteError($exception)) {
                $detailError = 'Fahrzeug kann nicht gelöscht werden, da Referenzen in anderen Tabellen bestehen.';
            } else {
                $detailError = 'Fahrzeug konnte nicht gelöscht werden.';
            }
        }
    }
}

if (!$recordDeleted && $vehicleTable !== null && $vehicleId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, brand, model, vehicle_type, license_plate
             FROM ' . $vehicleTable . '
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $vehicleId]);
        $row = $stmt->fetch();

        if (is_array($row)) {
            $vehicleDetail = $row;
        } else {
            $detailError = 'Das ausgewählte Fahrzeug wurde nicht gefunden.';
        }
    } catch (Throwable $exception) {
        $detailError = 'Fahrzeugdaten konnten nicht geladen werden.';
    }
} elseif (!$recordDeleted && $vehicleTable !== null) {
    $vehicleDetail = [
        'id' => 0,
        'customer_id' => 0,
        'brand' => '',
        'model' => '',
        'vehicle_type' => '',
        'license_plate' => '',
    ];
}

$selectedCustomerId = (int) ($vehicleDetail['customer_id'] ?? 0);
$selectedBrand = trim((string) ($vehicleDetail['brand'] ?? ''));
$selectedVehicleType = (string) ($vehicleDetail['vehicle_type'] ?? '');
$vehicleTypeOptions = loadVehicleTypeOptions($pdo);
$vehicleBrandOptions = $vehicleTable !== null ? loadVehicleBrandOptions($pdo, $vehicleTable) : [];

if ($selectedBrand !== '') {
    $hasSelectedBrand = false;
    foreach ($vehicleBrandOptions as $brandOption) {
        if (strtolower($brandOption) === strtolower($selectedBrand)) {
            $hasSelectedBrand = true;
            break;
        }
    }

    if (!$hasSelectedBrand) {
        array_unshift($vehicleBrandOptions, $selectedBrand);
    }
}

$isCreateMode = ((int) ($vehicleDetail['id'] ?? 0)) <= 0;
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Fahrzeug-Details</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="detail-page">
    <section class="detail-header">
        <a class="action-icon" href="verwaltung.php?view=vehicles" aria-label="Zurück zur Fahrzeugliste">&#8592;</a>
        <div>
            <h1><?= $isCreateMode ? 'Neues Kundenfahrzeug' : 'Fahrzeug-Detailansicht' ?></h1>
            <p><?= $isCreateMode ? 'Hier kann ein neues Fahrzeug erfasst werden.' : 'Hier können alle Fahrzeugdaten gepflegt und gespeichert werden.' ?></p>
        </div>
    </section>

    <article class="customer-detail-card">
        <?php if ($detailNotice !== ''): ?>
            <p class="form-notice"><?= e($detailNotice) ?></p>
        <?php endif; ?>
        <?php if ($detailError !== ''): ?>
            <p class="form-error"><?= e($detailError) ?></p>
        <?php endif; ?>

        <?php if (is_array($vehicleDetail)): ?>
            <form id="vehicle-detail-form" method="post" action="vehicle_detail.php<?= !$isCreateMode ? '?id=' . e((string) ($vehicleDetail['id'] ?? 0)) : '' ?>" class="customer-detail-form">
                <input type="hidden" name="auth_action" value="update_vehicle">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="vehicle_id" value="<?= e((string) ($vehicleDetail['id'] ?? 0)) ?>">

                <label for="vehicle_customer_id">Kunde</label>
                <select id="vehicle_customer_id" name="customer_id" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($customerOptions as $customer): ?>
                        <?php
                        $optionId = (int) ($customer['id'] ?? 0);
                        $optionName = trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? '')));
                        ?>
                        <option value="<?= e((string) $optionId) ?>"<?= $optionId === $selectedCustomerId ? ' selected' : '' ?>>
                            <?= e($optionName !== '' ? $optionName : ('Kunde #' . $optionId)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="vehicle_brand">Marke</label>
                <input id="vehicle_brand" type="text" name="brand" list="vehicle_brand_options" value="<?= e((string) ($vehicleDetail['brand'] ?? '')) ?>" required>
                <datalist id="vehicle_brand_options">
                    <?php foreach ($vehicleBrandOptions as $brandOption): ?>
                        <option value="<?= e($brandOption) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <label for="vehicle_model">Modell</label>
                <input id="vehicle_model" type="text" name="model" value="<?= e((string) ($vehicleDetail['model'] ?? '')) ?>" required>

                <label for="vehicle_type">Typ</label>
                <select id="vehicle_type" name="vehicle_type">
                    <option value="">Kein Typ</option>
                    <?php foreach ($vehicleTypeOptions as $option): ?>
                        <option value="<?= e($option) ?>"<?= $selectedVehicleType === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="vehicle_license_plate">Kennzeichen</label>
                <input id="vehicle_license_plate" type="text" name="license_plate" value="<?= e((string) ($vehicleDetail['license_plate'] ?? '')) ?>">

            </form>

            <div class="detail-form-actions">
                <button type="submit" form="vehicle-detail-form" class="primary-button"><?= $isCreateMode ? 'Fahrzeug erfassen' : 'Fahrzeugdaten speichern' ?></button>

                <?php if (!$isCreateMode): ?>
                    <form method="post" action="vehicle_detail.php?id=<?= e((string) ($vehicleDetail['id'] ?? 0)) ?>" class="detail-delete-form" onsubmit="return confirm('Fahrzeug wirklich löschen?');">
                        <input type="hidden" name="auth_action" value="delete_vehicle">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="vehicle_id" value="<?= e((string) ($vehicleDetail['id'] ?? 0)) ?>">
                        <button type="submit" class="danger-button">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
</main>
</body>
</html>
