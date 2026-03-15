<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/database.php';

$idleTimeoutSeconds = 600;
$referenceId = max(0, (int) ($_GET['id'] ?? $_POST['workload_reference_id'] ?? 0));
$createModeRequested = (string) ($_GET['create'] ?? $_POST['create_mode'] ?? '') === '1';
$csrfToken = (string) ($_SESSION['verwaltung_csrf'] ?? '');
$detailNotice = '';
$detailError = '';
$workloadReferenceDetail = null;
$vehicleTypeOptions = [];
$cleaningPackageOptions = [];
$recordDeleted = false;

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $detailNotice = 'Workload-Referenz wurde erfolgreich neu erfasst.';
}
if (isset($_GET['saved']) && $_GET['saved'] === '1') {
    $detailNotice = 'Workload-Referenz wurde erfolgreich gespeichert.';
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

/**
 * @return list<string>
 */
function loadVehicleTypeOptions(PDO $pdo): array
{
    try {
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

        return $options;
    } catch (Throwable $exception) {
        return [];
    }
}

/**
 * @return list<string>
 */
function loadCleaningPackageOptions(PDO $pdo): array
{
    try {
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

        return $options;
    } catch (Throwable $exception) {
        return [];
    }
}

$pdo = db();
$vehicleTypeOptions = loadVehicleTypeOptions($pdo);
$cleaningPackageOptions = loadCleaningPackageOptions($pdo);

if ($vehicleTypeOptions === [] || $cleaningPackageOptions === []) {
    $detailError = 'Bitte zuerst Fahrzeugtypen und Reinigungspakete in den Einstellungen pflegen.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'update_workload_reference') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $cleaningPackage = trim((string) ($_POST['cleaning_package'] ?? ''));
    $vehicleType = trim((string) ($_POST['vehicle_type'] ?? ''));
    $timeEffortInput = trim((string) ($_POST['time_effort'] ?? ''));
    $netPriceInput = trim((string) ($_POST['net_price'] ?? ''));

    $timeEffort = is_numeric($timeEffortInput) ? (float) $timeEffortInput : -1.0;
    $netPrice = is_numeric($netPriceInput) ? (float) $netPriceInput : -1.0;

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($cleaningPackage === '' || !in_array($cleaningPackage, $cleaningPackageOptions, true)) {
        $detailError = 'Bitte ein gültiges Reinigungspaket auswählen.';
    } elseif ($vehicleType === '' || !in_array($vehicleType, $vehicleTypeOptions, true)) {
        $detailError = 'Bitte einen gültigen Fahrzeugtyp auswählen.';
    } elseif ($timeEffort <= 0) {
        $detailError = 'Bitte einen gültigen Zeitaufwand größer als 0 eingeben.';
    } elseif ($netPrice < 0) {
        $detailError = 'Bitte einen gültigen Nettopreis eingeben.';
    } else {
        try {
            if ($referenceId > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE workload_reference
                     SET cleaning_package = :cleaning_package,
                         vehicle_type = :vehicle_type,
                         time_effort = :time_effort,
                         net_price = :net_price
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':cleaning_package' => $cleaningPackage,
                    ':vehicle_type' => $vehicleType,
                    ':time_effort' => round($timeEffort, 2),
                    ':net_price' => round($netPrice, 2),
                    ':id' => $referenceId,
                ]);

                header('Location: workload_reference_detail.php?id=' . $referenceId . '&saved=1');
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO workload_reference (cleaning_package, vehicle_type, time_effort, net_price)
                 VALUES (:cleaning_package, :vehicle_type, :time_effort, :net_price)'
            );
            $stmt->execute([
                ':cleaning_package' => $cleaningPackage,
                ':vehicle_type' => $vehicleType,
                ':time_effort' => round($timeEffort, 2),
                ':net_price' => round($netPrice, 2),
            ]);

            $newId = (int) $pdo->lastInsertId();
            if ($newId > 0) {
                header('Location: workload_reference_detail.php?id=' . $newId . '&created=1');
                exit;
            }

            $detailNotice = 'Workload-Referenz wurde erfolgreich neu erfasst.';
        } catch (Throwable $exception) {
            $detailError = 'Workload-Referenz konnte nicht gespeichert werden.';
        }
    }

    $workloadReferenceDetail = [
        'id' => $referenceId,
        'cleaning_package' => $cleaningPackage,
        'vehicle_type' => $vehicleType,
        'time_effort' => $timeEffortInput,
        'net_price' => $netPriceInput,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'delete_workload_reference') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($referenceId <= 0) {
        $detailError = 'Ungültige Aufwandsauswahl.';
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM workload_reference WHERE id = :id');
            $stmt->execute([':id' => $referenceId]);

            if ($stmt->rowCount() > 0) {
                $detailNotice = 'Aufwands-Eintrag wurde erfolgreich gelöscht.';
                $recordDeleted = true;
                $workloadReferenceDetail = null;
            } else {
                $detailError = 'Aufwands-Eintrag konnte nicht gelöscht werden.';
            }
        } catch (Throwable $exception) {
            if (isReferenceDeleteError($exception)) {
                $detailError = 'Aufwands-Eintrag kann nicht gelöscht werden, da Referenzen in anderen Tabellen bestehen.';
            } else {
                $detailError = 'Aufwands-Eintrag konnte nicht gelöscht werden.';
            }
        }
    }
}

if (!$recordDeleted && $workloadReferenceDetail === null) {
    if ($referenceId > 0) {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, cleaning_package, vehicle_type, time_effort, net_price
                 FROM workload_reference
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute([':id' => $referenceId]);
            $row = $stmt->fetch();

            if (is_array($row)) {
                $workloadReferenceDetail = $row;
            } else {
                $detailError = 'Der ausgewählte Aufwands-Eintrag wurde nicht gefunden.';
            }
        } catch (Throwable $exception) {
            $detailError = 'Aufwandsdaten konnten nicht geladen werden.';
        }
    } elseif ($createModeRequested || $_SERVER['REQUEST_METHOD'] === 'POST') {
        $workloadReferenceDetail = [
            'id' => 0,
            'cleaning_package' => '',
            'vehicle_type' => '',
            'time_effort' => '',
            'net_price' => '',
        ];
    } else {
        $detailError = 'Bitte wähle zuerst einen Eintrag aus der Aufwandsliste aus.';
    }
}

$isCreateMode = ((int) ($workloadReferenceDetail['id'] ?? 0)) <= 0;
$selectedCleaningPackage = trim((string) ($workloadReferenceDetail['cleaning_package'] ?? ''));
$selectedVehicleType = trim((string) ($workloadReferenceDetail['vehicle_type'] ?? ''));
$timeEffortValue = trim((string) ($workloadReferenceDetail['time_effort'] ?? ''));
$netPriceValue = trim((string) ($workloadReferenceDetail['net_price'] ?? ''));

if ($selectedCleaningPackage !== '' && !in_array($selectedCleaningPackage, $cleaningPackageOptions, true)) {
    array_unshift($cleaningPackageOptions, $selectedCleaningPackage);
}
if ($selectedVehicleType !== '' && !in_array($selectedVehicleType, $vehicleTypeOptions, true)) {
    array_unshift($vehicleTypeOptions, $selectedVehicleType);
}

$formAction = $isCreateMode
    ? 'workload_reference_detail.php?create=1'
    : 'workload_reference_detail.php?id=' . e((string) ($workloadReferenceDetail['id'] ?? 0));
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Arbeits- und Kosten-Aufwand</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="detail-page">
    <section class="detail-header">
        <a class="action-icon" href="verwaltung.php?view=workload" aria-label="Zurück zur Aufwandsliste">&#8592;</a>
        <div>
            <h1><?= $isCreateMode ? 'Neuen Aufwand erfassen' : 'Aufwand-Detailansicht' ?></h1>
            <p><?= $isCreateMode ? 'Hier kann ein neuer Arbeits- und Kosten-Aufwand erfasst werden.' : 'Hier können Arbeits- und Kosten-Aufwände bearbeitet und gespeichert werden.' ?></p>
        </div>
    </section>

    <article class="customer-detail-card">
        <?php if ($detailNotice !== ''): ?>
            <p class="form-notice"><?= e($detailNotice) ?></p>
        <?php endif; ?>
        <?php if ($detailError !== ''): ?>
            <p class="form-error"><?= e($detailError) ?></p>
        <?php endif; ?>

        <?php if (is_array($workloadReferenceDetail)): ?>
            <form id="workload-reference-detail-form" method="post" action="<?= $formAction ?>" class="customer-detail-form">
                <input type="hidden" name="auth_action" value="update_workload_reference">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="workload_reference_id" value="<?= e((string) ($workloadReferenceDetail['id'] ?? 0)) ?>">
                <?php if ($isCreateMode): ?>
                    <input type="hidden" name="create_mode" value="1">
                <?php endif; ?>

                <label for="workload_cleaning_package">Reinigungspaket</label>
                <select id="workload_cleaning_package" name="cleaning_package" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($cleaningPackageOptions as $cleaningPackageOption): ?>
                        <option value="<?= e($cleaningPackageOption) ?>"<?= $selectedCleaningPackage === $cleaningPackageOption ? ' selected' : '' ?>><?= e($cleaningPackageOption) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="workload_vehicle_type">Fahrzeugtyp</label>
                <select id="workload_vehicle_type" name="vehicle_type" required>
                    <option value="">Bitte auswählen</option>
                    <?php foreach ($vehicleTypeOptions as $vehicleTypeOption): ?>
                        <option value="<?= e($vehicleTypeOption) ?>"<?= $selectedVehicleType === $vehicleTypeOption ? ' selected' : '' ?>><?= e($vehicleTypeOption) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="workload_time_effort">Zeitaufwand (Stunden)</label>
                <input id="workload_time_effort" type="number" name="time_effort" min="0.01" step="0.01" value="<?= e($timeEffortValue) ?>" required>

                <label for="workload_net_price">Nettopreis (EUR)</label>
                <input id="workload_net_price" type="number" name="net_price" min="0" step="0.01" value="<?= e($netPriceValue) ?>" required>

            </form>

            <div class="detail-form-actions">
                <button type="submit" form="workload-reference-detail-form" class="primary-button"><?= $isCreateMode ? 'Aufwand erfassen' : 'Aufwand speichern' ?></button>

                <?php if (!$isCreateMode): ?>
                    <form method="post" action="workload_reference_detail.php?id=<?= e((string) ($workloadReferenceDetail['id'] ?? 0)) ?>" class="detail-delete-form" onsubmit="return confirm('Aufwands-Eintrag wirklich löschen?');">
                        <input type="hidden" name="auth_action" value="delete_workload_reference">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="workload_reference_id" value="<?= e((string) ($workloadReferenceDetail['id'] ?? 0)) ?>">
                        <button type="submit" class="danger-button">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>
</main>
</body>
</html>
