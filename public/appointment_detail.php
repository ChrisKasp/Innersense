<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/database.php';

$idleTimeoutSeconds = 600;
$requestId = max(0, (int) ($_GET['id'] ?? $_POST['request_id'] ?? 0));
$createModeRequested = (string) ($_GET['create'] ?? $_POST['create_mode'] ?? '') === '1';
$csrfToken = (string) ($_SESSION['verwaltung_csrf'] ?? '');
$detailNotice = '';
$detailError = '';
$requestDetail = null;
$customerOptions = [];
$vehicleOptions = [];
$cleaningPackageOptions = [];
$workloadReferenceMap = [];
$recordDeleted = false;

if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $detailNotice = 'Termindaten wurden erfolgreich gespeichert.';
}
if (isset($_GET['created']) && $_GET['created'] === '1') {
    $detailNotice = 'Termin wurde erfolgreich neu erfasst.';
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

function isValidDate(string $value): bool
{
    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

/**
 * @return list<string>
 */
function buildHalfHourSlots(string $start, string $end): array
{
    $slots = [];
    $cursor = DateTimeImmutable::createFromFormat('H:i', $start);
    $endTime = DateTimeImmutable::createFromFormat('H:i', $end);

    if (!$cursor instanceof DateTimeImmutable || !$endTime instanceof DateTimeImmutable) {
        return $slots;
    }

    while ($cursor < $endTime) {
        $slots[] = $cursor->format('H:i');
        $cursor = $cursor->modify('+30 minutes');
    }

    return $slots;
}

function normalizeHalfHourSlot(string $value): ?string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    if (preg_match('/^(\d{2}:\d{2})(?::\d{2})?$/', $trimmed, $matches) !== 1) {
        return null;
    }

    $normalized = $matches[1];
    return in_array($normalized, buildHalfHourSlots('09:00', '20:00'), true) ? $normalized : null;
}

/**
 * @return list<string>
 */
function getAvailableHalfHourSlotsForDate(PDO $pdo, string $requestTable, int $currentRequestId, string $date): array
{
    if (!isValidDate($date)) {
        return [];
    }

    $allSlots = buildHalfHourSlots('09:00', '20:00');
    $unavailableMap = [];

    $blockedStmt = $pdo->prepare(
        'SELECT DATE_FORMAT(slot_time, "%H:%i") AS slot_key
         FROM schedule_blocked_slot
         WHERE slot_date = :slot_date'
    );
    $blockedStmt->execute([':slot_date' => $date]);
    foreach ($blockedStmt->fetchAll() as $row) {
        $slot = (string) ($row['slot_key'] ?? '');
        if ($slot !== '') {
            $unavailableMap[$slot] = true;
        }
    }

    $bookedStmt = $pdo->prepare(
        'SELECT preferred_time
         FROM ' . $requestTable . '
         WHERE preferred_date = :preferred_date
           AND id <> :current_id
           AND COALESCE(status, "") <> "cancelled"
           AND TRIM(COALESCE(preferred_time, "")) <> ""'
    );
    $bookedStmt->execute([
        ':preferred_date' => $date,
        ':current_id' => $currentRequestId,
    ]);
    foreach ($bookedStmt->fetchAll() as $row) {
        $normalized = normalizeHalfHourSlot((string) ($row['preferred_time'] ?? ''));
        if ($normalized !== null) {
            $unavailableMap[$normalized] = true;
        }
    }

    return array_values(array_filter(
        $allSlots,
        static fn (string $slot): bool => !isset($unavailableMap[$slot])
    ));
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
$requestTable = 'customer_request';
$vehicleTable = 'customer_vehicle';
$cleaningPackageOptions = loadCleaningPackageOptions($pdo);
$workloadReferenceMap = loadWorkloadReferenceMap($pdo);

if ((string) ($_GET['action'] ?? '') === 'available_slots') {
    header('Content-Type: application/json; charset=UTF-8');

    $date = trim((string) ($_GET['date'] ?? ''));
    $currentId = max(0, (int) ($_GET['id'] ?? 0));
    $slots = [];

    if ($requestTable !== null && isValidDate($date)) {
        $slots = getAvailableHalfHourSlotsForDate($pdo, $requestTable, $currentId, $date);
    }

    echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
    $requestTable !== null
    && $vehicleTable !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'update_appointment'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    $customerId = max(0, (int) ($_POST['customer_id'] ?? 0));
    $vehicleId = max(0, (int) ($_POST['customer_vehicle_id'] ?? 0));
    $cleaningPackage = trim((string) ($_POST['cleaning_package'] ?? ''));
    $specialWishes = trim((string) ($_POST['special_wishes'] ?? ''));
    $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
    $preferredTimeInput = trim((string) ($_POST['preferred_time'] ?? ''));
    $preferredTime = normalizeHalfHourSlot($preferredTimeInput) ?? '';
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
    } elseif ($preferredDate === '' && $preferredTimeInput !== '') {
        $detailError = 'Bitte zuerst ein Wunschdatum auswählen.';
    } elseif ($preferredDate !== '' && $preferredTimeInput !== '') {
        $availableTimeOptions = getAvailableHalfHourSlotsForDate($pdo, $requestTable, $requestId, $preferredDate);
        if (!in_array($preferredTime, $availableTimeOptions, true)) {
            $detailError = 'Die gewählte Wunschzeit ist für den ausgewählten Tag nicht verfügbar.';
        }
    }

    if ($detailError !== '') {
        $requestDetail = [
            'id' => $requestId,
            'customer_id' => $customerId,
            'customer_vehicle_id' => $vehicleId,
            'cleaning_package' => $cleaningPackage,
            'special_wishes' => $specialWishes,
            'preferred_date' => $preferredDate,
            'preferred_time' => $preferredTimeInput,
            'status' => $status,
        ];
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

                    header('Location: appointment_detail.php?id=' . $requestId . '&saved=1');
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
                    header('Location: appointment_detail.php?id=' . $newRequestId . '&created=1');
                    exit;
                }
            }
        } catch (Throwable $exception) {
            $detailError = 'Termindaten konnten nicht gespeichert werden.';
        }
    }
}

if (
    $requestTable !== null
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['auth_action'] ?? '') === 'delete_appointment'
) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($requestId <= 0) {
        $detailError = 'Ungültige Terminauswahl.';
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM ' . $requestTable . ' WHERE id = :id');
            $stmt->execute([':id' => $requestId]);

            if ($stmt->rowCount() > 0) {
                $detailNotice = 'Termin wurde erfolgreich gelöscht.';
                $recordDeleted = true;
                $requestDetail = null;
            } else {
                $detailError = 'Termin konnte nicht gelöscht werden.';
            }
        } catch (Throwable $exception) {
            if (isReferenceDeleteError($exception)) {
                $detailError = 'Termin kann nicht gelöscht werden, da Referenzen in anderen Tabellen bestehen.';
            } else {
                $detailError = 'Termin konnte nicht gelöscht werden.';
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
            $detailError = 'Der ausgewählte Termin wurde nicht gefunden.';
        }
    } catch (Throwable $exception) {
        $detailError = 'Termindaten konnten nicht geladen werden.';
    }
} elseif (!$recordDeleted && $requestTable !== null && $vehicleTable !== null && ($createModeRequested || $_SERVER['REQUEST_METHOD'] === 'POST')) {
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
} elseif (!$recordDeleted && $requestTable !== null && $vehicleTable !== null) {
    $detailError = 'Bitte wähle einen Termin aus der Terminliste aus.';
}

$selectedCustomerId = (int) ($requestDetail['customer_id'] ?? 0);
$selectedVehicleId = (int) ($requestDetail['customer_vehicle_id'] ?? 0);
$selectedStatus = (string) ($requestDetail['status'] ?? 'new');
$selectedCleaningPackage = (string) ($requestDetail['cleaning_package'] ?? '');
if ($selectedCleaningPackage !== '' && !in_array($selectedCleaningPackage, $cleaningPackageOptions, true)) {
    array_unshift($cleaningPackageOptions, $selectedCleaningPackage);
}
$selectedPreferredDate = trim((string) ($requestDetail['preferred_date'] ?? ''));
$selectedPreferredTime = normalizeHalfHourSlot((string) ($requestDetail['preferred_time'] ?? '')) ?? '';
$preferredTimeOptions = [];
if ($requestTable !== null && isValidDate($selectedPreferredDate)) {
    $preferredTimeOptions = getAvailableHalfHourSlotsForDate($pdo, $requestTable, $requestId, $selectedPreferredDate);
    if ($selectedPreferredTime !== '' && !in_array($selectedPreferredTime, $preferredTimeOptions, true)) {
        array_unshift($preferredTimeOptions, $selectedPreferredTime);
    }
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
$formAction = $isCreateMode
    ? 'appointment_detail.php?create=1'
    : 'appointment_detail.php?id=' . e((string) ($requestDetail['id'] ?? 0));
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Termin-Details</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="detail-page">
    <section class="detail-header">
        <a class="action-icon" href="verwaltung.php?view=appointments" aria-label="Zurück zur Terminliste">&#8592;</a>
        <div>
            <h1><?= $isCreateMode ? 'Neuen Termin erfassen' : 'Termin-Detailansicht' ?></h1>
            <p><?= $isCreateMode ? 'Hier kann ein neuer Termin erfasst werden.' : 'Hier können alle Termindaten gepflegt und gespeichert werden.' ?></p>
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
            <form id="appointment-detail-form" method="post" action="<?= $formAction ?>" class="customer-detail-form">
                <input type="hidden" name="auth_action" value="update_appointment">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="request_id" value="<?= e((string) ($requestDetail['id'] ?? 0)) ?>">
                <?php if ($isCreateMode): ?>
                    <input type="hidden" name="create_mode" value="1">
                <?php endif; ?>

                <label for="request_customer_id">Kunde</label>
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
                            <?= e(($optionVehicleOwner !== '' ? $optionVehicleOwner . ' - ' : '') . ($optionVehicleLabel !== '' ? $optionVehicleLabel : ('Fahrzeug #' . $optionVehicleId))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="request_vehicle_type">Fahrzeugtyp</label>
                <input id="request_vehicle_type" type="text" value="" readonly>

                <label for="request_package">Paket</label>
                <select id="request_package" name="cleaning_package" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($cleaningPackageOptions as $cleaningPackageOption): ?>
                        <option value="<?= e($cleaningPackageOption) ?>"<?= $selectedCleaningPackage === $cleaningPackageOption ? ' selected' : '' ?>><?= e($cleaningPackageOption) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="request_time_effort_calc">Zeitaufwand (Vorschlag)</label>
                <input id="request_time_effort_calc" type="text" value="-" readonly>

                <label for="request_net_price_calc">Nettopreis (Vorschlag)</label>
                <input id="request_net_price_calc" type="text" value="-" readonly>

                <label for="request_preferred_date">Wunschdatum</label>
                <input id="request_preferred_date" type="date" name="preferred_date" value="<?= e((string) ($requestDetail['preferred_date'] ?? '')) ?>">

                <label for="request_preferred_time">Wunschzeit</label>
                <select id="request_preferred_time" name="preferred_time" <?= $selectedPreferredDate === '' ? 'disabled' : '' ?>>
                    <option value=""><?= $selectedPreferredDate === '' ? 'Bitte zuerst Datum auswählen' : 'Bitte auswählen' ?></option>
                    <?php foreach ($preferredTimeOptions as $slot): ?>
                        <option value="<?= e($slot) ?>"<?= $selectedPreferredTime === $slot ? ' selected' : '' ?>><?= e($slot) ?></option>
                    <?php endforeach; ?>
                </select>

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
                <button type="submit" form="appointment-detail-form" class="primary-button"><?= $isCreateMode ? 'Termin erfassen' : 'Termindaten speichern' ?></button>

                <?php if (!$isCreateMode): ?>
                    <form method="post" action="appointment_detail.php?id=<?= e((string) ($requestDetail['id'] ?? 0)) ?>" class="detail-delete-form" onsubmit="return confirm('Termin wirklich löschen?');">
                        <input type="hidden" name="auth_action" value="delete_appointment">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="request_id" value="<?= e((string) ($requestDetail['id'] ?? 0)) ?>">
                        <button type="submit" class="danger-button">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
</main>

<script>
(() => {
    const isCreateMode = <?= $isCreateMode ? 'true' : 'false' ?>;
    const customerSelect = document.getElementById('request_customer_id');
    const vehicleSelect = document.getElementById('request_vehicle_id');
    const preferredDateInput = document.getElementById('request_preferred_date');
    const preferredTimeSelect = document.getElementById('request_preferred_time');
    const vehicleTypeInput = document.getElementById('request_vehicle_type');
    const packageSelect = document.getElementById('request_package');
    const timeEffortInput = document.getElementById('request_time_effort_calc');
    const netPriceInput = document.getElementById('request_net_price_calc');
    const requestIdField = document.querySelector('input[name="request_id"]');
    const workloadReferenceMap = <?= json_encode($workloadReferenceMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (!customerSelect || !vehicleSelect || !packageSelect || !timeEffortInput || !netPriceInput) {
        return;
    }

    const formatNumber = (value, suffix) => `${Number(value).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${suffix}`;

    const getSelectedVehicleType = () => {
        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex] || null;
        if (!selectedOption || selectedOption.value === '') {
            return '';
        }

        return (selectedOption.getAttribute('data-vehicle-type') || '').trim();
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

    const loadAvailableSlots = async () => {
        if (!preferredDateInput || !preferredTimeSelect) {
            return;
        }

        const date = preferredDateInput.value;
        const currentValue = preferredTimeSelect.value;
        preferredTimeSelect.innerHTML = '';

        if (!date) {
            preferredTimeSelect.disabled = true;
            preferredTimeSelect.add(new Option('Bitte zuerst Datum auswählen', ''));
            return;
        }

        try {
            const currentId = requestIdField ? requestIdField.value : '0';
            const response = await fetch(`appointment_detail.php?action=available_slots&id=${encodeURIComponent(currentId)}&date=${encodeURIComponent(date)}`);
            const data = await response.json();
            const slots = Array.isArray(data.slots) ? data.slots : [];

            preferredTimeSelect.disabled = false;
            preferredTimeSelect.add(new Option('Bitte auswählen', ''));

            slots.forEach((slot) => {
                preferredTimeSelect.add(new Option(slot, slot));
            });

            if (currentValue && slots.includes(currentValue)) {
                preferredTimeSelect.value = currentValue;
            }
        } catch (_error) {
            preferredTimeSelect.disabled = false;
            preferredTimeSelect.add(new Option('Zeiten konnten nicht geladen werden', ''));
        }
    };

    const filterVehicles = () => {
        const selectedCustomerId = customerSelect.value;
        const options = Array.from(vehicleSelect.options);
        const visibleVehicleOptions = [];

        options.forEach((option, index) => {
            if (index === 0) {
                option.hidden = false;
                return;
            }

            const optionCustomerId = option.getAttribute('data-customer-id') || '';
            option.hidden = selectedCustomerId !== '' && optionCustomerId !== selectedCustomerId;

            if (!option.hidden) {
                visibleVehicleOptions.push(option);
            }
        });

        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex] || null;
        if (selectedOption && selectedOption.hidden) {
            vehicleSelect.value = '';
        }

        // In create mode, preselect the vehicle if customer has exactly one vehicle.
        if (isCreateMode && selectedCustomerId !== '' && visibleVehicleOptions.length === 1) {
            vehicleSelect.value = visibleVehicleOptions[0].value;
        }

        syncVehicleType();
    };

    const syncVehicleType = () => {
        if (!vehicleTypeInput) {
            return;
        }

        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex] || null;
        if (!selectedOption || selectedOption.value === '') {
            vehicleTypeInput.value = '-';
            syncWorkloadEstimation();
            return;
        }

        const vehicleType = (selectedOption.getAttribute('data-vehicle-type') || '').trim();
        vehicleTypeInput.value = vehicleType !== '' ? vehicleType : '-';
        syncWorkloadEstimation();
    };

    customerSelect.addEventListener('change', filterVehicles);
    vehicleSelect.addEventListener('change', syncVehicleType);
    packageSelect.addEventListener('change', syncWorkloadEstimation);
    filterVehicles();
    syncVehicleType();
    syncWorkloadEstimation();

    if (preferredDateInput && preferredTimeSelect) {
        preferredDateInput.addEventListener('change', loadAvailableSlots);
    }
})();
</script>
</body>
</html>
