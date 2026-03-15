<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$errors = [];
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'street_address' => '',
    'postal_code' => '',
    'city' => '',
    'vehicle_brand' => '',
    'vehicle_model' => '',
    'vehicle_type' => '',
    'vehicle_license_plate' => '',
];

$vehicleTypeOptions = loadVehicleTypeOptions(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $_unused) {
        $formData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($formData['first_name'] === '') {
        $errors[] = 'Vorname ist erforderlich.';
    }
    if ($formData['last_name'] === '') {
        $errors[] = 'Nachname ist erforderlich.';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail angeben.';
    }

    $vehicleBrand = $formData['vehicle_brand'];
    $vehicleModel = $formData['vehicle_model'];
    $vehicleType = $formData['vehicle_type'];
    $vehicleLicensePlate = $formData['vehicle_license_plate'];
    $vehicleRequested = $vehicleBrand !== '' || $vehicleModel !== '' || $vehicleType !== '' || $vehicleLicensePlate !== '';

    if ($vehicleRequested && ($vehicleBrand === '' || $vehicleModel === '')) {
        $errors[] = 'Für die Fahrzeugerfassung bitte mindestens Marke und Modell angeben.';
    }
    if ($vehicleType !== '' && !in_array($vehicleType, $vehicleTypeOptions, true)) {
        $errors[] = 'Der ausgewählte Fahrzeugtyp ist ungültig.';
    }

    if ($errors === []) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO customer (
                    first_name,
                    last_name,
                    email,
                    phone,
                    street_address,
                    postal_code,
                    city
                ) VALUES (
                    :first_name,
                    :last_name,
                    :email,
                    :phone,
                    :street_address,
                    :postal_code,
                    :city
                )'
            );
            $stmt->execute([
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'email' => $formData['email'],
                'phone' => $formData['phone'],
                'street_address' => $formData['street_address'],
                'postal_code' => $formData['postal_code'],
                'city' => $formData['city'],
            ]);

            $customerId = (int) $pdo->lastInsertId();

            if ($vehicleRequested) {
                $vehicleTable = detectVehicleTable($pdo);
                if ($vehicleTable === null) {
                    throw new RuntimeException('Fahrzeugtabelle wurde nicht gefunden.');
                }

                $vehicleStmt = $pdo->prepare(
                    'INSERT INTO ' . $vehicleTable . ' (
                        customer_id,
                        brand,
                        model,
                        vehicle_type,
                        license_plate
                    ) VALUES (
                        :customer_id,
                        :brand,
                        :model,
                        :vehicle_type,
                        :license_plate
                    )'
                );
                $vehicleStmt->execute([
                    'customer_id' => $customerId,
                    'brand' => $vehicleBrand,
                    'model' => $vehicleModel,
                    'vehicle_type' => ($vehicleType !== '' ? $vehicleType : null),
                    'license_plate' => $vehicleLicensePlate,
                ]);
            }

            $pdo->commit();

            redirect('verwaltung.php?view=customers');
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Kunde konnte nicht gespeichert werden (E-Mail eventuell bereits vorhanden).';
        }
    }
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
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kunde anlegen</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<?php
$activePage = '';
require __DIR__ . '/partials/site_header.php';
?>

<main class="container narrow">
    <h1>Kunde anlegen</h1>
    <p><a href="verwaltung.php?view=customers">Zurück zur Übersicht</a></p>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="form-grid">
        <label>Vorname<input type="text" name="first_name" value="<?= e($formData['first_name']) ?>" required></label>
        <label>Nachname<input type="text" name="last_name" value="<?= e($formData['last_name']) ?>" required></label>
        <label>E-Mail<input type="email" name="email" value="<?= e($formData['email']) ?>" required></label>
        <label>Telefon<input type="text" name="phone" value="<?= e($formData['phone']) ?>"></label>
        <label>Straße und Hausnummer<input type="text" name="street_address" value="<?= e($formData['street_address']) ?>"></label>
        <label>PLZ<input type="text" name="postal_code" value="<?= e($formData['postal_code']) ?>"></label>
        <label>Ort<input type="text" name="city" value="<?= e($formData['city']) ?>"></label>
        <label>Fahrzeug Marke (optional)<input type="text" name="vehicle_brand" value="<?= e($formData['vehicle_brand']) ?>"></label>
        <label>Fahrzeug Modell (optional)<input type="text" name="vehicle_model" value="<?= e($formData['vehicle_model']) ?>"></label>
        <label>Fahrzeugtyp (optional)
            <select name="vehicle_type">
                <option value="">Bitte auswählen</option>
                <?php foreach ($vehicleTypeOptions as $vehicleTypeOption): ?>
                    <option value="<?= e($vehicleTypeOption) ?>"<?= $formData['vehicle_type'] === $vehicleTypeOption ? ' selected' : '' ?>><?= e($vehicleTypeOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Kennzeichen (optional)<input type="text" name="vehicle_license_plate" value="<?= e($formData['vehicle_license_plate']) ?>"></label>
        <button type="submit">Speichern</button>
    </form>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
