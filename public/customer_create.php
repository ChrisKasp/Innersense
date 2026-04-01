<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$pdo = db();
ensureCustomerEmailAllowsNull($pdo);
$errors = [];
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'customer_typ' => 'Privatperson',
    'company_name' => '',
    'street_address' => '',
    'postal_code' => '',
    'city' => '',
    'vehicle_brand' => '',
    'vehicle_model' => '',
    'vehicle_type' => '',
    'vehicle_license_plate' => '',
];

$vehicleTypeOptions = loadVehicleTypeOptions($pdo);
$vehicleTable = detectVehicleTable($pdo);
$vehicleBrandOptions = $vehicleTable !== null ? loadVehicleBrandOptions($pdo, $vehicleTable) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $_unused) {
        $formData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($formData['first_name'] === '' && $formData['last_name'] === '') {
        $errors[] = 'Bitte mindestens einen Vor- oder Nachnamen angeben.';
    }

    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gültige E-Mail angeben oder leer lassen.';
    }
    if (!in_array($formData['customer_typ'], ['Firma', 'Privatperson'], true)) {
        $errors[] = 'Bitte einen gültigen Kundentyp auswählen.';
    }
    if ($formData['customer_typ'] === 'Firma' && $formData['company_name'] === '') {
        $errors[] = 'Bitte einen Firmennamen angeben.';
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
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO customer (
                    first_name,
                    last_name,
                    email,
                    phone,
                    customer_typ,
                    company_name,
                    street_address,
                    postal_code,
                    city
                ) VALUES (
                    :first_name,
                    :last_name,
                    :email,
                    :phone,
                    :customer_typ,
                    :company_name,
                    :street_address,
                    :postal_code,
                    :city
                )'
            );
            $stmt->execute([
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'email' => ($formData['email'] !== '' ? $formData['email'] : null),
                'phone' => $formData['phone'],
                'customer_typ' => $formData['customer_typ'],
                'company_name' => $formData['company_name'],
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

$selectedVehicleBrand = trim((string) ($formData['vehicle_brand'] ?? ''));
if ($selectedVehicleBrand !== '') {
    $hasSelectedBrand = false;
    foreach ($vehicleBrandOptions as $brandOption) {
        if (strtolower($brandOption) === strtolower($selectedVehicleBrand)) {
            $hasSelectedBrand = true;
            break;
        }
    }

    if (!$hasSelectedBrand) {
        array_unshift($vehicleBrandOptions, $selectedVehicleBrand);
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

function ensureCustomerEmailAllowsNull(PDO $pdo): void
{
    try {
        $columnStmt = $pdo->query('SHOW COLUMNS FROM customer LIKE "email"');
        $column = $columnStmt !== false ? $columnStmt->fetch() : false;
        if (!is_array($column)) {
            return;
        }

        $isNullable = strtolower((string) ($column['Null'] ?? 'NO')) === 'yes';
        if ($isNullable) {
            return;
        }

        $pdo->exec('ALTER TABLE customer MODIFY email VARCHAR(190) NULL');
        $pdo->exec('UPDATE customer SET email = NULL WHERE TRIM(COALESCE(email, "")) = ""');
    } catch (Throwable $exception) {
        // Keep runtime resilient if ALTER permissions are missing.
    }
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
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Kunde anlegen</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="detail-page">
    <section class="detail-header">
        <a class="action-icon" href="verwaltung.php?view=customers" aria-label="Zurück zur Kundenliste">&#8592;</a>
        <div>
            <h1>Neuer Kunde</h1>
            <p>Hier kann ein neuer Kunde erfasst werden.</p>
        </div>
    </section>

    <article class="customer-detail-card">
        <?php foreach ($errors as $error): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endforeach; ?>

        <form id="customer-create-form" method="post" class="customer-detail-form">
            <label for="customer_typ">Kundentyp *</label>
            <select id="customer_typ" name="customer_typ" required>
                <option value="Privatperson"<?= $formData['customer_typ'] === 'Privatperson' ? ' selected' : '' ?>>Privatperson</option>
                <option value="Firma"<?= $formData['customer_typ'] === 'Firma' ? ' selected' : '' ?>>Firma</option>
            </select>

            <label for="customer_company_name">Firmenname</label>
            <input id="customer_company_name" type="text" name="company_name" value="<?= e($formData['company_name']) ?>">

            <label for="customer_first_name">Vorname</label>
            <input id="customer_first_name" type="text" name="first_name" value="<?= e($formData['first_name']) ?>">

            <label for="customer_last_name">Nachname</label>
            <input id="customer_last_name" type="text" name="last_name" value="<?= e($formData['last_name']) ?>">

            <label for="customer_email">E-Mail</label>
            <input id="customer_email" type="email" name="email" value="<?= e($formData['email']) ?>">

            <label for="customer_phone">Telefon</label>
            <input id="customer_phone" type="text" name="phone" value="<?= e($formData['phone']) ?>">

            <label for="customer_street_address">Straße und Hausnummer</label>
            <input id="customer_street_address" type="text" name="street_address" value="<?= e($formData['street_address']) ?>">

            <label for="customer_postal_code">PLZ</label>
            <input id="customer_postal_code" type="text" name="postal_code" value="<?= e($formData['postal_code']) ?>">

            <label for="customer_city">Ort</label>
            <input id="customer_city" type="text" name="city" value="<?= e($formData['city']) ?>">

            <label for="vehicle_brand">Fahrzeug Marke (optional)</label>
            <input id="vehicle_brand" type="text" name="vehicle_brand" list="vehicle_brand_options" value="<?= e($formData['vehicle_brand']) ?>">
            <datalist id="vehicle_brand_options">
                <?php foreach ($vehicleBrandOptions as $brandOption): ?>
                    <option value="<?= e($brandOption) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <label for="vehicle_model">Fahrzeug Modell (optional)</label>
            <input id="vehicle_model" type="text" name="vehicle_model" value="<?= e($formData['vehicle_model']) ?>">

            <label for="vehicle_type">Fahrzeugtyp (optional)</label>
            <select id="vehicle_type" name="vehicle_type">
                <option value="">Bitte auswählen</option>
                <?php foreach ($vehicleTypeOptions as $vehicleTypeOption): ?>
                    <option value="<?= e($vehicleTypeOption) ?>"<?= $formData['vehicle_type'] === $vehicleTypeOption ? ' selected' : '' ?>><?= e($vehicleTypeOption) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="vehicle_license_plate">Kennzeichen (optional)</label>
            <input id="vehicle_license_plate" type="text" name="vehicle_license_plate" value="<?= e($formData['vehicle_license_plate']) ?>">
        </form>

        <div class="detail-form-actions">
            <button type="submit" form="customer-create-form" class="primary-button">Kunde speichern</button>
        </div>
    </article>
</main>
<script>
(() => {
    const customerTypeField = document.getElementById('customer_typ');
    const companyNameField = document.getElementById('customer_company_name');

    if (!(customerTypeField instanceof HTMLSelectElement) || !(companyNameField instanceof HTMLInputElement)) {
        return;
    }

    const syncCompanyFieldState = () => {
        const isCompany = customerTypeField.value === 'Firma';
        companyNameField.disabled = !isCompany;
        if (!isCompany) {
            companyNameField.value = '';
            companyNameField.placeholder = 'Nur bei Kundentyp Firma';
        } else {
            companyNameField.placeholder = '';
        }
    };

    customerTypeField.addEventListener('change', syncCompanyFieldState);
    syncCompanyFieldState();
})();
</script>
</body>
</html>
