<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/database.php';

$idleTimeoutSeconds = 600;
$requestId = max(0, (int) ($_GET['id'] ?? $_POST['request_id'] ?? 0));
$csrfToken = (string) ($_SESSION['verwaltung_csrf'] ?? '');
$detailNotice = '';
$detailError = '';
$requestDetail = null;
$customerOptions = [];
$vehicleOptions = [];
$cleaningPackageOptions = [];
$workloadReferenceMap = [];
$vehicleTypeOptions = [];
$newCustomerErrors = [];
$newCustomerFormData = [
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
$openCreateCustomerModal = false;
$prefillCustomerId = 0;
$prefillVehicleId = 0;
$recordDeleted = false;

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $detailNotice = 'Anfrage wurde erfolgreich neu erfasst.';
}
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $detailNotice = 'Anfragedaten wurden erfolgreich gespeichert.';
}

if (!isset($_SESSION['verwaltung_authenticated']) || $_SESSION['verwaltung_authenticated'] !== true) {
    header('Location: verwaltung.php');
    exit;
}

$lastActivity = (int) ($_SESSION['verwaltung_last_activity'] ?? 0);
if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeoutSeconds) {
    $_SESSION['verwaltung_authenticated'] = false;
    unset($_SESSION['verwaltung_last_activity']);
    header('Location: verwaltung.php?timed_out=1');
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

function detectFirstExistingTable(PDO $pdo, array $tableNames): ?string
{
    foreach ($tableNames as $tableName) {
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
function loadCleaningPackageOptions(PDO $pdo): array
{
    $defaults = [
        'Innenreinigung Basic',
        'Innen- und Außenreinigung',
        'Premium Aufbereitung',
        'Komplettaufbereitung',
        'Politur / Lackpflege',
    ];

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cleaning_package (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                package_name VARCHAR(120) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cleaning_package_name (package_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $seedStmt = $pdo->prepare(
            'INSERT INTO cleaning_package (package_name, sort_order, is_active)
             VALUES (:package_name, :sort_order, 1)
             ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), sort_order = VALUES(sort_order)'
        );
        $sort = 10;
        foreach ($defaults as $defaultPackage) {
            $seedStmt->execute([
                ':package_name' => $defaultPackage,
                ':sort_order' => $sort,
            ]);
            $sort += 10;
        }

        $stmt = $pdo->query(
            'SELECT package_name
             FROM cleaning_package
             WHERE is_active = 1
             ORDER BY sort_order ASC, package_name ASC'
        );
        $rows = $stmt->fetchAll();
        $options = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $name = trim((string) ($row['package_name'] ?? ''));
                if ($name !== '') {
                    $options[] = $name;
                }
            }
        }

        return $options !== [] ? $options : $defaults;
    } catch (Throwable $exception) {
        return $defaults;
    }
}

/**
 * @return array<string, array<string, array<string, float>>>
 */
function loadWorkloadReferenceMap(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            'SELECT cleaning_package, vehicle_type, time_effort, net_price
             FROM workload_reference'
        );
        $rows = $stmt->fetchAll();
        $map = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $cleaningPackage = trim((string) ($row['cleaning_package'] ?? ''));
                $vehicleType = trim((string) ($row['vehicle_type'] ?? ''));
                if ($cleaningPackage === '' || $vehicleType === '') {
                    continue;
                }

                if (!isset($map[$cleaningPackage])) {
                    $map[$cleaningPackage] = [];
                }

                $map[$cleaningPackage][$vehicleType] = [
                    'time_effort' => (float) ($row['time_effort'] ?? 0),
                    'net_price' => (float) ($row['net_price'] ?? 0),
                ];
            }
        }

        return $map;
    } catch (Throwable $exception) {
        return [];
    }
}

$pdo = db();
$requestTable = detectFirstExistingTable($pdo, ['customer_requests', 'customer_request']);
$vehicleTable = detectFirstExistingTable($pdo, ['customer_vehicles', 'customer_vehicle']);
$cleaningPackageOptions = loadCleaningPackageOptions($pdo);
$workloadReferenceMap = loadWorkloadReferenceMap($pdo);
$vehicleTypeOptions = loadVehicleTypeOptions($pdo);

if ($requestTable === null) {
    $detailError = 'Anfragetabelle wurde nicht gefunden.';
}
if ($vehicleTable === null && $detailError === '') {
    $detailError = 'Fahrzeugtabelle wurde nicht gefunden.';
}

try {
    $stmt = $pdo->query('SELECT id, first_name, last_name FROM customer ORDER BY first_name ASC, last_name ASC, id ASC');
    $rows = $stmt->fetchAll();
    $customerOptions = is_array($rows) ? $rows : [];
} catch (Throwable $exception) {
    $customerOptions = [];
}

if ($vehicleTable !== null) {
    try {
        $stmt = $pdo->query(
              'SELECT cv.id,
                    cv.customer_id,
                    cv.brand,
                    cv.model,
                    COALESCE(vto.type_name, cv.vehicle_type) AS vehicle_type,
                    c.first_name,
                    c.last_name
             FROM ' . $vehicleTable . ' cv
             LEFT JOIN customer c ON c.id = cv.customer_id
               LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
             ORDER BY c.first_name ASC, c.last_name ASC, cv.brand ASC, cv.model ASC, cv.id ASC'
        );
        $rows = $stmt->fetchAll();
        $vehicleOptions = is_array($rows) ? $rows : [];
    } catch (Throwable $exception) {
        $vehicleOptions = [];
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'create_customer_from_request'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $openCreateCustomerModal = true;

    foreach ($newCustomerFormData as $field => $_unused) {
        $newCustomerFormData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if (!hash_equals($csrfToken, $postedToken)) {
        $newCustomerErrors[] = 'Ungültige Anfrage. Bitte Seite neu laden.';
    }

    if ($newCustomerFormData['email'] !== '' && !filter_var($newCustomerFormData['email'], FILTER_VALIDATE_EMAIL)) {
        $newCustomerErrors[] = 'Bitte eine gültige E-Mail angeben oder leer lassen.';
    }

    if (!in_array($newCustomerFormData['customer_typ'], ['Firma', 'Privatperson'], true)) {
        $newCustomerErrors[] = 'Bitte einen gültigen Kundentyp auswählen.';
    }

    if ($newCustomerFormData['customer_typ'] === 'Firma' && $newCustomerFormData['company_name'] === '') {
        $newCustomerErrors[] = 'Bitte einen Firmennamen angeben.';
    }

    $vehicleBrand = $newCustomerFormData['vehicle_brand'];
    $vehicleModel = $newCustomerFormData['vehicle_model'];
    $vehicleType = $newCustomerFormData['vehicle_type'];
    $vehicleLicensePlate = $newCustomerFormData['vehicle_license_plate'];
    $vehicleRequested = $vehicleBrand !== '' || $vehicleModel !== '' || $vehicleType !== '' || $vehicleLicensePlate !== '';

    if ($vehicleRequested && ($vehicleBrand === '' || $vehicleModel === '')) {
        $newCustomerErrors[] = 'Für die Fahrzeugerfassung bitte mindestens Marke und Modell angeben.';
    }

    if ($vehicleType !== '' && !in_array($vehicleType, $vehicleTypeOptions, true)) {
        $newCustomerErrors[] = 'Der ausgewählte Fahrzeugtyp ist ungültig.';
    }

    if ($newCustomerErrors === []) {
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
                ':first_name' => $newCustomerFormData['first_name'],
                ':last_name' => $newCustomerFormData['last_name'],
                ':email' => $newCustomerFormData['email'],
                ':phone' => $newCustomerFormData['phone'],
                ':customer_typ' => $newCustomerFormData['customer_typ'],
                ':company_name' => $newCustomerFormData['company_name'],
                ':street_address' => $newCustomerFormData['street_address'],
                ':postal_code' => $newCustomerFormData['postal_code'],
                ':city' => $newCustomerFormData['city'],
            ]);

            $createdCustomerId = (int) $pdo->lastInsertId();
            $createdVehicleId = 0;

            if ($vehicleRequested) {
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
                    ':customer_id' => $createdCustomerId,
                    ':brand' => $vehicleBrand,
                    ':model' => $vehicleModel,
                    ':vehicle_type' => ($vehicleType !== '' ? $vehicleType : null),
                    ':license_plate' => $vehicleLicensePlate,
                ]);

                $createdVehicleId = (int) $pdo->lastInsertId();
            }

            $pdo->commit();

            try {
                $stmt = $pdo->query('SELECT id, first_name, last_name FROM customer ORDER BY first_name ASC, last_name ASC, id ASC');
                $rows = $stmt->fetchAll();
                $customerOptions = is_array($rows) ? $rows : [];
            } catch (Throwable $exception) {
                $customerOptions = [];
            }

            if ($vehicleTable !== null) {
                try {
                    $stmt = $pdo->query(
                        'SELECT cv.id,
                                cv.customer_id,
                                cv.brand,
                                cv.model,
                                COALESCE(vto.type_name, cv.vehicle_type) AS vehicle_type,
                                c.first_name,
                                c.last_name
                         FROM ' . $vehicleTable . ' cv
                         LEFT JOIN customer c ON c.id = cv.customer_id
                         LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
                         ORDER BY c.first_name ASC, c.last_name ASC, cv.brand ASC, cv.model ASC, cv.id ASC'
                    );
                    $rows = $stmt->fetchAll();
                    $vehicleOptions = is_array($rows) ? $rows : [];
                } catch (Throwable $exception) {
                    $vehicleOptions = [];
                }
            }

            $prefillCustomerId = $createdCustomerId;
            $prefillVehicleId = $createdVehicleId;
            $detailNotice = 'Kunde wurde erfolgreich angelegt.';
            $openCreateCustomerModal = false;

            foreach ($newCustomerFormData as $field => $_unused) {
                $newCustomerFormData[$field] = $field === 'customer_typ' ? 'Privatperson' : '';
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $newCustomerErrors[] = 'Kunde konnte nicht gespeichert werden (E-Mail eventuell bereits vorhanden).';
        }
    }
}

if (
    $requestTable !== null
    && $vehicleTable !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'update_request'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    $customerId = max(0, (int) ($_POST['customer_id'] ?? 0));
    $vehicleId = max(0, (int) ($_POST['customer_vehicle_id'] ?? 0));
    $cleaningPackage = trim((string) ($_POST['cleaning_package'] ?? ''));
    $specialWishes = trim((string) ($_POST['special_wishes'] ?? ''));
    $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
    $preferredTime = trim((string) ($_POST['preferred_time'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'new'));

    $allowedStatuses = ['new', 'contacted', 'scheduled', 'completed', 'cancelled'];
    $dateIsValid = ($preferredDate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate) === 1);

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($customerId <= 0 || $vehicleId <= 0) {
        $detailError = 'Bitte Kunde und Fahrzeug auswählen.';
    } elseif ($cleaningPackage === '') {
        $detailError = 'Das Paket ist erforderlich.';
    } elseif (!in_array($cleaningPackage, $cleaningPackageOptions, true)) {
        $detailError = 'Bitte ein gültiges Reinigungspaket auswählen.';
    } elseif (!$dateIsValid) {
        $detailError = 'Bitte ein gültiges Datum auswählen.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $detailError = 'Der Status ist ungültig.';
    } else {
        try {
            $vehicleMatchStmt = $pdo->prepare(
                'SELECT id
                 FROM ' . $vehicleTable . '
                 WHERE id = :vehicle_id AND customer_id = :customer_id
                 LIMIT 1'
            );
            $vehicleMatchStmt->execute([
                ':vehicle_id' => $vehicleId,
                ':customer_id' => $customerId,
            ]);
            $vehicleMatch = $vehicleMatchStmt->fetch();

            if (!is_array($vehicleMatch)) {
                $detailError = 'Das ausgewählte Fahrzeug gehört nicht zum gewählten Kunden.';
            } else {
                if ($requestId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE ' . $requestTable . '
                         SET customer_id = :customer_id,
                             customer_vehicle_id = :customer_vehicle_id,
                             cleaning_package = :cleaning_package,
                             special_wishes = :special_wishes,
                             preferred_date = :preferred_date,
                             preferred_time = :preferred_time,
                             status = :status
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':customer_id' => $customerId,
                        ':customer_vehicle_id' => $vehicleId,
                        ':cleaning_package' => $cleaningPackage,
                        ':special_wishes' => ($specialWishes !== '' ? $specialWishes : null),
                        ':preferred_date' => ($preferredDate !== '' ? $preferredDate : null),
                        ':preferred_time' => $preferredTime,
                        ':status' => $status,
                        ':id' => $requestId,
                    ]);

                    header('Location: request_detail.php?id=' . $requestId . '&saved=1');
                    exit;
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO ' . $requestTable . ' (
                        customer_id,
                        customer_vehicle_id,
                        cleaning_package,
                        special_wishes,
                        preferred_date,
                        preferred_time,
                        status
                    ) VALUES (
                        :customer_id,
                        :customer_vehicle_id,
                        :cleaning_package,
                        :special_wishes,
                        :preferred_date,
                        :preferred_time,
                        :status
                    )'
                );
                $stmt->execute([
                    ':customer_id' => $customerId,
                    ':customer_vehicle_id' => $vehicleId,
                    ':cleaning_package' => $cleaningPackage,
                    ':special_wishes' => ($specialWishes !== '' ? $specialWishes : null),
                    ':preferred_date' => ($preferredDate !== '' ? $preferredDate : null),
                    ':preferred_time' => $preferredTime,
                    ':status' => $status,
                ]);

                $newRequestId = (int) $pdo->lastInsertId();
                if ($newRequestId > 0) {
                    header('Location: request_detail.php?id=' . $newRequestId . '&created=1');
                    exit;
                }

                $detailNotice = 'Anfrage wurde erfolgreich neu erfasst.';
            }
        } catch (Throwable $exception) {
            $detailError = 'Anfragedaten konnten nicht gespeichert werden.';
        }
    }
}

if (
    $requestTable !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'delete_request'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($requestId <= 0) {
        $detailError = 'Ungültige Anfrageauswahl.';
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM ' . $requestTable . ' WHERE id = :id');
            $stmt->execute([':id' => $requestId]);

            if ($stmt->rowCount() > 0) {
                $detailNotice = 'Anfrage wurde erfolgreich gelöscht.';
                $recordDeleted = true;
                $requestDetail = null;
            } else {
                $detailError = 'Anfrage konnte nicht gelöscht werden.';
            }
        } catch (Throwable $exception) {
            if (isReferenceDeleteError($exception)) {
                $detailError = 'Anfrage kann nicht gelöscht werden, da Referenzen in anderen Tabellen bestehen.';
            } else {
                $detailError = 'Anfrage konnte nicht gelöscht werden.';
            }
        }
    }
}

if (!$recordDeleted && $requestTable !== null && $requestId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, customer_id, customer_vehicle_id, cleaning_package, special_wishes, preferred_date, preferred_time, status
             FROM ' . $requestTable . '
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $requestId]);
        $row = $stmt->fetch();

        if (is_array($row)) {
            $requestDetail = $row;
        } else {
            $detailError = 'Die ausgewählte Anfrage wurde nicht gefunden.';
        }
    } catch (Throwable $exception) {
        $detailError = 'Anfragedaten konnten nicht geladen werden.';
    }
} elseif (!$recordDeleted && $requestTable !== null && $vehicleTable !== null) {
    $requestDetail = [
        'id' => 0,
        'customer_id' => 0,
        'customer_vehicle_id' => 0,
        'cleaning_package' => '',
        'special_wishes' => '',
        'preferred_date' => '',
        'preferred_time' => '',
        'status' => 'new',
    ];
}

$selectedCustomerId = (int) ($requestDetail['customer_id'] ?? 0);
$selectedVehicleId = (int) ($requestDetail['customer_vehicle_id'] ?? 0);
$selectedCustomerId = $prefillCustomerId > 0 ? $prefillCustomerId : $selectedCustomerId;
$selectedVehicleId = $prefillVehicleId > 0 ? $prefillVehicleId : $selectedVehicleId;
$selectedStatus = (string) ($requestDetail['status'] ?? 'new');
$selectedCleaningPackage = (string) ($requestDetail['cleaning_package'] ?? '');
if ($selectedCleaningPackage !== '' && !in_array($selectedCleaningPackage, $cleaningPackageOptions, true)) {
    array_unshift($cleaningPackageOptions, $selectedCleaningPackage);
}
$statusOptions = ['new', 'contacted', 'scheduled', 'completed', 'cancelled'];
$statusLabelMap = [
    'new' => 'neu',
    'contacted' => 'kontaktiert',
    'scheduled' => 'geplant',
    'completed' => 'abgeschlossen',
    'cancelled' => 'storniert',
];
$isCreateMode = ((int) ($requestDetail['id'] ?? 0)) <= 0;
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Anfrage-Details</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="detail-page">
    <section class="detail-header">
        <a class="action-icon" href="verwaltung.php?view=requests" aria-label="Zurück zur Anfragenliste">&#8592;</a>
        <div>
            <h1><?= $isCreateMode ? 'Neue Kundenanfrage' : 'Anfrage-Detailansicht' ?></h1>
            <p><?= $isCreateMode ? 'Hier kann eine neue Kundenanfrage erfasst werden.' : 'Hier können alle Anfragedaten gepflegt und gespeichert werden.' ?></p>
        </div>
    </section>

    <article class="customer-detail-card">
        <?php if ($detailNotice !== ''): ?>
            <p class="form-notice"><?= e($detailNotice) ?></p>
        <?php endif; ?>
        <?php if ($detailError !== ''): ?>
            <p class="form-error"><?= e($detailError) ?></p>
        <?php endif; ?>

        <?php if (is_array($requestDetail)): ?>
            <form id="request-detail-form" method="post" action="request_detail.php<?= !$isCreateMode ? '?id=' . e((string) ($requestDetail['id'] ?? 0)) : '' ?>" class="customer-detail-form">
                <input type="hidden" name="auth_action" value="update_request">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="request_id" value="<?= e((string) ($requestDetail['id'] ?? 0)) ?>">

                <label for="request_customer_id">Kunde</label>
                <div class="field-inline-action">
                    <select id="request_customer_id" name="customer_id" required>
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
                    <button type="button" id="open-create-customer-dialog" class="secondary-button">Neu</button>
                </div>

                <label for="request_vehicle_id">Fahrzeug</label>
                <select id="request_vehicle_id" name="customer_vehicle_id" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($vehicleOptions as $vehicle): ?>
                        <?php
                        $optionVehicleId = (int) ($vehicle['id'] ?? 0);
                        $optionVehicleCustomerId = (int) ($vehicle['customer_id'] ?? 0);
                        $optionVehicleOwner = trim(((string) ($vehicle['first_name'] ?? '')) . ' ' . ((string) ($vehicle['last_name'] ?? '')));
                        $optionVehicleLabel = trim(((string) ($vehicle['brand'] ?? '')) . ' ' . ((string) ($vehicle['model'] ?? '')));
                        $optionVehicleType = trim((string) ($vehicle['vehicle_type'] ?? ''));
                        ?>
                        <option
                            value="<?= e((string) $optionVehicleId) ?>"
                            data-customer-id="<?= e((string) $optionVehicleCustomerId) ?>"
                            data-vehicle-type="<?= e($optionVehicleType) ?>"
                            <?= $optionVehicleId === $selectedVehicleId ? ' selected' : '' ?>
                        >
                            <?= e(($optionVehicleOwner !== '' ? $optionVehicleOwner . ' - ' : '') . ($optionVehicleLabel !== '' ? $optionVehicleLabel : ('Fahrzeug #' . $optionVehicleId)) . ($optionVehicleType !== '' ? ' (' . $optionVehicleType . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="request_package">Paket</label>
                <select id="request_package" name="cleaning_package" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($cleaningPackageOptions as $cleaningPackageOption): ?>
                        <option value="<?= e($cleaningPackageOption) ?>"<?= $selectedCleaningPackage === $cleaningPackageOption ? ' selected' : '' ?>><?= e($cleaningPackageOption) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="request_vehicle_type">Fahrzeugtyp</label>
                <input id="request_vehicle_type" type="text" value="" readonly>

                <label for="request_time_effort_calc">Zeitaufwand (Vorschlag)</label>
                <input id="request_time_effort_calc" type="text" value="-" readonly>

                <label for="request_net_price_calc">Nettopreis (Vorschlag)</label>
                <input id="request_net_price_calc" type="text" value="-" readonly>

                <label for="request_preferred_date">Wunschdatum</label>
                <input id="request_preferred_date" type="date" name="preferred_date" value="<?= e((string) ($requestDetail['preferred_date'] ?? '')) ?>">

                <label for="request_preferred_time">Wunschzeit</label>
                <input id="request_preferred_time" type="text" name="preferred_time" value="<?= e((string) ($requestDetail['preferred_time'] ?? '')) ?>">

                <label for="request_status">Status</label>
                <select id="request_status" name="status" required>
                    <?php foreach ($statusOptions as $option): ?>
                        <option value="<?= e($option) ?>"<?= $selectedStatus === $option ? ' selected' : '' ?>><?= e($statusLabelMap[$option] ?? $option) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="request_special_wishes">Sonderwünsche</label>
                <textarea id="request_special_wishes" name="special_wishes" rows="5"><?= e((string) ($requestDetail['special_wishes'] ?? '')) ?></textarea>

            </form>

            <div class="detail-form-actions">
                <button type="submit" form="request-detail-form" class="primary-button"><?= $isCreateMode ? 'Anfrage erfassen' : 'Anfragedaten speichern' ?></button>

                <?php if (!$isCreateMode): ?>
                    <form method="post" action="request_detail.php?id=<?= e((string) ($requestDetail['id'] ?? 0)) ?>" class="detail-delete-form" onsubmit="return confirm('Anfrage wirklich löschen?');">
                        <input type="hidden" name="auth_action" value="delete_request">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="request_id" value="<?= e((string) ($requestDetail['id'] ?? 0)) ?>">
                        <button type="submit" class="danger-button">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>

            <dialog id="create-customer-dialog" class="inline-modal">
                <article class="inline-modal-card" aria-label="Kunde anlegen">
                    <header class="inline-modal-head">
                        <h2>Kunde anlegen</h2>
                        <form method="dialog">
                            <button type="submit" class="action-icon" aria-label="Dialog schließen" title="Schließen">&#10005;</button>
                        </form>
                    </header>

                    <?php foreach ($newCustomerErrors as $newCustomerError): ?>
                        <p class="form-error"><?= e($newCustomerError) ?></p>
                    <?php endforeach; ?>

                    <form method="post" action="request_detail.php<?= !$isCreateMode ? '?id=' . e((string) ($requestDetail['id'] ?? 0)) : '' ?>" class="customer-detail-form modal-form" novalidate>
                        <input type="hidden" name="auth_action" value="create_customer_from_request">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="new_customer_first_name">Vorname</label>
                        <input id="new_customer_first_name" type="text" name="first_name" value="<?= e((string) ($newCustomerFormData['first_name'] ?? '')) ?>">

                        <label for="new_customer_last_name">Nachname</label>
                        <input id="new_customer_last_name" type="text" name="last_name" value="<?= e((string) ($newCustomerFormData['last_name'] ?? '')) ?>">

                        <label for="new_customer_email">E-Mail</label>
                        <input id="new_customer_email" type="email" name="email" value="<?= e((string) ($newCustomerFormData['email'] ?? '')) ?>">

                        <label for="new_customer_phone">Telefon</label>
                        <input id="new_customer_phone" type="text" name="phone" value="<?= e((string) ($newCustomerFormData['phone'] ?? '')) ?>">

                        <label for="new_customer_type">Kundentyp</label>
                        <select id="new_customer_type" name="customer_typ" required>
                            <option value="Privatperson"<?= (($newCustomerFormData['customer_typ'] ?? 'Privatperson') === 'Privatperson') ? ' selected' : '' ?>>Privatperson</option>
                            <option value="Firma"<?= (($newCustomerFormData['customer_typ'] ?? '') === 'Firma') ? ' selected' : '' ?>>Firma</option>
                        </select>

                        <label for="new_customer_company_name">Firma</label>
                        <input id="new_customer_company_name" type="text" name="company_name" value="<?= e((string) ($newCustomerFormData['company_name'] ?? '')) ?>">

                        <label for="new_customer_street_address">Straße und Hausnummer</label>
                        <input id="new_customer_street_address" type="text" name="street_address" value="<?= e((string) ($newCustomerFormData['street_address'] ?? '')) ?>">

                        <label for="new_customer_postal_code">PLZ</label>
                        <input id="new_customer_postal_code" type="text" name="postal_code" value="<?= e((string) ($newCustomerFormData['postal_code'] ?? '')) ?>">

                        <label for="new_customer_city">Ort</label>
                        <input id="new_customer_city" type="text" name="city" value="<?= e((string) ($newCustomerFormData['city'] ?? '')) ?>">

                        <label for="new_customer_vehicle_brand">Fahrzeug Marke (optional)</label>
                        <input id="new_customer_vehicle_brand" type="text" name="vehicle_brand" value="<?= e((string) ($newCustomerFormData['vehicle_brand'] ?? '')) ?>" placeholder="z. B. Audi">

                        <label for="new_customer_vehicle_model">Fahrzeug Modell (optional)</label>
                        <input id="new_customer_vehicle_model" type="text" name="vehicle_model" value="<?= e((string) ($newCustomerFormData['vehicle_model'] ?? '')) ?>" placeholder="z. B. A4">

                        <label for="new_customer_vehicle_type">Fahrzeugtyp (optional)</label>
                        <select id="new_customer_vehicle_type" name="vehicle_type">
                            <option value="">Bitte auswählen</option>
                            <?php foreach ($vehicleTypeOptions as $vehicleTypeOption): ?>
                                <option value="<?= e($vehicleTypeOption) ?>"<?= (($newCustomerFormData['vehicle_type'] ?? '') === $vehicleTypeOption) ? ' selected' : '' ?>><?= e($vehicleTypeOption) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="new_customer_vehicle_license_plate">Kennzeichen (optional)</label>
                        <input id="new_customer_vehicle_license_plate" type="text" name="vehicle_license_plate" value="<?= e((string) ($newCustomerFormData['vehicle_license_plate'] ?? '')) ?>" placeholder="z. B. HH-AB 1234">

                        <div class="detail-form-actions modal-actions">
                            <button type="submit" class="primary-button">Kunde anlegen</button>
                            <button type="button" id="cancel-create-customer-dialog" class="secondary-button">Abbrechen</button>
                        </div>
                    </form>
                </article>
            </dialog>
        <?php endif; ?>
    </article>
</main>

<script>
(() => {
    const customerSelect = document.getElementById('request_customer_id');
    const vehicleSelect = document.getElementById('request_vehicle_id');
    const packageSelect = document.getElementById('request_package');
    const vehicleTypeInput = document.getElementById('request_vehicle_type');
    const timeEffortInput = document.getElementById('request_time_effort_calc');
    const netPriceInput = document.getElementById('request_net_price_calc');
    const openCreateCustomerDialogButton = document.getElementById('open-create-customer-dialog');
    const createCustomerDialog = document.getElementById('create-customer-dialog');
    const cancelCreateCustomerDialogButton = document.getElementById('cancel-create-customer-dialog');
    const workloadReferenceMap = <?= json_encode($workloadReferenceMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const openCreateCustomerModalOnLoad = <?= $openCreateCustomerModal ? 'true' : 'false' ?>;

    if (!customerSelect || !vehicleSelect || !packageSelect || !vehicleTypeInput || !timeEffortInput || !netPriceInput) {
        return;
    }

    if (openCreateCustomerDialogButton && createCustomerDialog && typeof createCustomerDialog.showModal === 'function') {
        openCreateCustomerDialogButton.addEventListener('click', () => {
            createCustomerDialog.showModal();
        });

        if (cancelCreateCustomerDialogButton) {
            cancelCreateCustomerDialogButton.addEventListener('click', () => {
                createCustomerDialog.close();
            });
        }

        if (openCreateCustomerModalOnLoad) {
            createCustomerDialog.showModal();
        }
    }

    const formatNumber = (value, suffix) => `${Number(value).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${suffix}`;

    const getSelectedVehicleType = () => {
        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex] || null;
        if (!selectedOption || selectedOption.value === '') {
            return '';
        }

        return (selectedOption.getAttribute('data-vehicle-type') || '').trim();
    };

    const syncVehicleType = () => {
        const vehicleType = getSelectedVehicleType();
        vehicleTypeInput.value = vehicleType !== '' ? vehicleType : '-';
    };

    const syncWorkloadEstimation = () => {
        const selectedPackage = (packageSelect.value || '').trim();
        const selectedVehicleType = getSelectedVehicleType();

        if (selectedPackage === '' || selectedVehicleType === '') {
            timeEffortInput.value = '-';
            netPriceInput.value = '-';
            return;
        }

        const packageMap = workloadReferenceMap[selectedPackage] || null;
        const reference = packageMap ? packageMap[selectedVehicleType] : null;

        if (!reference) {
            timeEffortInput.value = '-';
            netPriceInput.value = '-';
            return;
        }

        timeEffortInput.value = formatNumber(reference.time_effort, 'h');
        netPriceInput.value = formatNumber(reference.net_price, 'EUR');
    };

    const filterVehicles = () => {
        const selectedCustomerId = customerSelect.value;
        const options = Array.from(vehicleSelect.options);

        options.forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const optionCustomerId = option.getAttribute('data-customer-id') || '';
            option.hidden = selectedCustomerId !== '' && optionCustomerId !== selectedCustomerId;
        });

        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex] || null;
        if (selectedOption && selectedOption.hidden) {
            vehicleSelect.value = '';
        }

        syncVehicleType();
        syncWorkloadEstimation();
    };

    customerSelect.addEventListener('change', filterVehicles);
    vehicleSelect.addEventListener('change', () => {
        syncVehicleType();
        syncWorkloadEstimation();
    });
    packageSelect.addEventListener('change', syncWorkloadEstimation);
    filterVehicles();
    syncVehicleType();
    syncWorkloadEstimation();
})();
</script>
</body>
</html>
