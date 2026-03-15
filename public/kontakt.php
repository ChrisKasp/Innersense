<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/env.php';
loadEnv(dirname(__DIR__) . '/.env');

$publicContactConfigFile = dirname(__DIR__) . '/config/public_contact.php';
$publicContactSettings = [];
if (is_file($publicContactConfigFile) && is_readable($publicContactConfigFile)) {
    $loadedPublicContactSettings = require $publicContactConfigFile;
    if (is_array($loadedPublicContactSettings)) {
        $publicContactSettings = $loadedPublicContactSettings;
    }
}

$contactToEmail = trim((string) ($publicContactSettings['contact_email'] ?? (getenv('CONTACT_TO_EMAIL') ?: 'geome.selle@gmail.de')));
if ($contactToEmail === '' || filter_var($contactToEmail, FILTER_VALIDATE_EMAIL) === false) {
    $contactToEmail = 'geome.selle@gmail.de';
}

require_once dirname(__DIR__) . '/config/database.php';

$pdo = db();
ensureBlockedSlotTable($pdo);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'available_slots') {
    header('Content-Type: application/json; charset=UTF-8');

    $date = trim((string) ($_GET['date'] ?? ''));
    if (!isValidDateOrEmpty($date) || $date === '') {
        echo json_encode(['slots' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $slots = getAvailableHalfHourSlots($pdo, $date);
    echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$contactStatus = (string) ($_GET['contact'] ?? '');
$contactMessage = '';
$contactMessageClass = '';

if ($contactStatus === 'success') {
    $contactMessage = 'Danke. Deine Anfrage wurde erfolgreich versendet.';
    $contactMessageClass = 'form-notice';
}
if ($contactStatus === 'error') {
    $contactMessage = 'Die Anfrage konnte nicht versendet werden. Bitte pruefe deine Eingaben.';
    $contactMessageClass = 'form-error';
}

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
    'license_plate' => '',
    'cleaning_package' => '',
    'preferred_date' => '',
    'preferred_time' => '',
    'special_wishes' => '',
];

$vehicleTypeOptions = loadVehicleTypeOptions($pdo);
$cleaningPackageOptions = loadCleaningPackageOptions($pdo);

$preferredTimeOptions = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $_unused) {
        $formData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($formData['preferred_date'] !== '' && isValidDateOrEmpty($formData['preferred_date'])) {
        $preferredTimeOptions = getAvailableHalfHourSlots($pdo, $formData['preferred_date']);
    }

    $preferredDateTimeCombinationValid = true;
    if ($formData['preferred_time'] !== '' && $formData['preferred_date'] === '') {
        $preferredDateTimeCombinationValid = false;
    }
    if ($formData['preferred_time'] !== '' && $formData['preferred_date'] !== '') {
        $preferredDateTimeCombinationValid = in_array($formData['preferred_time'], $preferredTimeOptions, true);
    }

    $consentGiven = isset($_POST['consent']) && $_POST['consent'] === '1';
    $isValid = $formData['first_name'] !== ''
        && $formData['last_name'] !== ''
        && filter_var($formData['email'], FILTER_VALIDATE_EMAIL)
        && $formData['phone'] !== ''
        && $formData['street_address'] !== ''
        && $formData['vehicle_brand'] !== ''
        && $formData['vehicle_model'] !== ''
        && in_array($formData['cleaning_package'], $cleaningPackageOptions, true)
        && ($formData['vehicle_type'] === '' || in_array($formData['vehicle_type'], $vehicleTypeOptions, true))
        && isValidDateOrEmpty($formData['preferred_date'])
        && ($formData['preferred_time'] === '' || isValidHalfHourSlot($formData['preferred_time']))
        && $preferredDateTimeCombinationValid
        && $consentGiven;

    if ($isValid) {
        try {
            saveContactRequest(db(), $formData, $consentGiven);

            $to = $contactToEmail;
            $subject = 'Neue Anfrage ueber kontakt.php';
            $body = "Neue Kontaktanfrage\n\n"
                . 'Vorname: ' . $formData['first_name'] . "\n"
                . 'Nachname: ' . $formData['last_name'] . "\n"
                . 'E-Mail: ' . $formData['email'] . "\n"
                . 'Telefon: ' . ($formData['phone'] !== '' ? $formData['phone'] : '-') . "\n\n"
                . 'Strasse + Hausnummer: ' . $formData['street_address'] . "\n"
                . 'PLZ: ' . ($formData['postal_code'] !== '' ? $formData['postal_code'] : '-') . "\n"
                . 'Ort: ' . ($formData['city'] !== '' ? $formData['city'] : '-') . "\n"
                . 'Automarke: ' . $formData['vehicle_brand'] . "\n"
                . 'Modell: ' . $formData['vehicle_model'] . "\n"
                . 'Fahrzeugtyp: ' . ($formData['vehicle_type'] !== '' ? $formData['vehicle_type'] : '-') . "\n"
                . 'Kennzeichen: ' . ($formData['license_plate'] !== '' ? $formData['license_plate'] : '-') . "\n"
                . 'Reinigungspaket: ' . $formData['cleaning_package'] . "\n"
                . 'Wunschdatum: ' . ($formData['preferred_date'] !== '' ? $formData['preferred_date'] : '-') . "\n"
                . 'Wunschzeit: ' . ($formData['preferred_time'] !== '' ? $formData['preferred_time'] : '-') . "\n\n"
                . "Besondere Wuensche:\n"
                . ($formData['special_wishes'] !== '' ? $formData['special_wishes'] : '-') . "\n";

            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'From: Innersense Website <no-reply@innersense.sellerie.net>',
                'Reply-To: ' . $formData['email'],
            ];

            @mail($to, $subject, $body, implode("\r\n", $headers));
            header('Location: kontakt.php?contact=success#formular');
            exit;
        } catch (Throwable $exception) {
            $contactMessage = 'Die Anfrage konnte nicht gespeichert werden. Bitte versuche es erneut.';
            $contactMessageClass = 'form-error';
        }
    } else {
        $contactMessage = 'Bitte fuelle alle Pflichtfelder korrekt aus, waehle gueltige Optionen und bestaetige die Zustimmung.';
        $contactMessageClass = 'form-error';
    }
}

if ($formData['preferred_date'] !== '' && isValidDateOrEmpty($formData['preferred_date'])) {
    $preferredTimeOptions = getAvailableHalfHourSlots($pdo, $formData['preferred_date']);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<string, string> $formData
 */
function saveContactRequest(PDO $pdo, array $formData, bool $consentGiven): void
{
    if (!$consentGiven) {
        throw new RuntimeException('Consent is required.');
    }

    $pdo->beginTransaction();

    try {
        $customerId = upsertCustomer($pdo, $formData);

        $vehicleStmt = $pdo->prepare(
            'INSERT INTO customer_vehicle (customer_id, brand, model, vehicle_type, license_plate)
             VALUES (:customer_id, :brand, :model, :vehicle_type, :license_plate)'
        );
        $vehicleStmt->execute([
            'customer_id' => $customerId,
            'brand' => $formData['vehicle_brand'],
            'model' => $formData['vehicle_model'],
            'vehicle_type' => $formData['vehicle_type'] !== '' ? $formData['vehicle_type'] : null,
            'license_plate' => $formData['license_plate'],
        ]);

        $vehicleId = (int) $pdo->lastInsertId();

        $requestStmt = $pdo->prepare(
            'INSERT INTO customer_request (
                customer_id,
                customer_vehicle_id,
                cleaning_package,
                special_wishes,
                preferred_date,
                preferred_time
             )
             VALUES (
                :customer_id,
                :customer_vehicle_id,
                :cleaning_package,
                :special_wishes,
                :preferred_date,
                :preferred_time
             )'
        );
        $requestStmt->execute([
            'customer_id' => $customerId,
            'customer_vehicle_id' => $vehicleId,
            'cleaning_package' => $formData['cleaning_package'],
            'special_wishes' => $formData['special_wishes'] !== '' ? $formData['special_wishes'] : null,
            'preferred_date' => $formData['preferred_date'] !== '' ? $formData['preferred_date'] : null,
            'preferred_time' => $formData['preferred_time'],
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * @param array<string, string> $formData
 */
function upsertCustomer(PDO $pdo, array $formData): int
{
    $hasStreetAddress = customerHasStreetAddressColumn($pdo);

    $findStmt = $pdo->prepare('SELECT id FROM customer WHERE email = :email LIMIT 1');
    $findStmt->execute(['email' => $formData['email']]);
    $existingId = $findStmt->fetchColumn();

    if ($existingId !== false) {
        if ($hasStreetAddress) {
            $updateStmt = $pdo->prepare(
                'UPDATE customer
                 SET first_name = :first_name,
                     last_name = :last_name,
                     phone = :phone,
                     street_address = :street_address,
                     postal_code = :postal_code,
                     city = :city,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'id' => (int) $existingId,
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'phone' => $formData['phone'],
                'street_address' => $formData['street_address'],
                'postal_code' => $formData['postal_code'],
                'city' => $formData['city'],
            ]);
        } else {
            $updateStmt = $pdo->prepare(
                'UPDATE customer
                 SET first_name = :first_name,
                     last_name = :last_name,
                     phone = :phone,
                     postal_code = :postal_code,
                     city = :city,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $updateStmt->execute([
                'id' => (int) $existingId,
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'phone' => $formData['phone'],
                'postal_code' => $formData['postal_code'],
                'city' => $formData['city'],
            ]);
        }

        return (int) $existingId;
    }

    if ($hasStreetAddress) {
        $insertStmt = $pdo->prepare(
            'INSERT INTO customer (first_name, last_name, email, phone, street_address, postal_code, city)
             VALUES (:first_name, :last_name, :email, :phone, :street_address, :postal_code, :city)'
        );
        $insertStmt->execute([
            'first_name' => $formData['first_name'],
            'last_name' => $formData['last_name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'street_address' => $formData['street_address'],
            'postal_code' => $formData['postal_code'],
            'city' => $formData['city'],
        ]);
    } else {
        $insertStmt = $pdo->prepare(
            'INSERT INTO customer (first_name, last_name, email, phone, postal_code, city)
             VALUES (:first_name, :last_name, :email, :phone, :postal_code, :city)'
        );
        $insertStmt->execute([
            'first_name' => $formData['first_name'],
            'last_name' => $formData['last_name'],
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'postal_code' => $formData['postal_code'],
            'city' => $formData['city'],
        ]);
    }

    return (int) $pdo->lastInsertId();
}

function isValidDateOrEmpty(string $value): bool
{
    if ($value === '') {
        return true;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function customerHasStreetAddressColumn(PDO $pdo): bool
{
    static $hasStreetAddress = null;

    if ($hasStreetAddress !== null) {
        return $hasStreetAddress;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM customer LIKE 'street_address'");
    $hasStreetAddress = $stmt->fetch() !== false;

    return $hasStreetAddress;
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

function isValidHalfHourSlot(string $time): bool
{
    return in_array($time, buildHalfHourSlots('09:00', '20:00'), true);
}

function ensureBlockedSlotTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schedule_blocked_slot (
            slot_date DATE NOT NULL,
            slot_time TIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (slot_date, slot_time),
            KEY idx_schedule_blocked_slot_date (slot_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * @return list<string>
 */
function getAvailableHalfHourSlots(PDO $pdo, string $date): array
{
    $allSlots = buildHalfHourSlots('09:00', '20:00');

    if (!isValidDateOrEmpty($date) || $date === '') {
        return $allSlots;
    }

    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(slot_time, "%H:%i") AS slot_key
         FROM schedule_blocked_slot
         WHERE slot_date = :slot_date'
    );
    $stmt->execute([':slot_date' => $date]);

    $blockedMap = [];
    foreach ($stmt->fetchAll() as $row) {
        $slot = (string) ($row['slot_key'] ?? '');
        if ($slot !== '') {
            $blockedMap[$slot] = true;
        }
    }

    return array_values(array_filter(
        $allSlots,
        static fn (string $slot): bool => !isset($blockedMap[$slot])
    ));
}

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Kontakt</title>
    <link rel="stylesheet" href="assets/kontakt.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<?php
$activePage = 'kontakt';
require __DIR__ . '/partials/site_header.php';
?>

<main>
    <section class="contact-intro">
        <div class="container">
            <h1>Du hast Fragen? Frag einfach</h1>
            <img class="quote-image" src="assets/Bilder/Wichtig.avif" alt="Wichtig ist, dass man nicht aufhoert zu fragen.">

            <div class="intro-copy">
                <p>
                    Ob du mehr zu unseren Leistungen wissen willst, dir unsicher bei der Fahrzeuggroesse bist
                    oder einfach wissen moechtest, was "Textilversiegelung" eigentlich genau bringt - schreib uns.
                </p>
                <p>
                    Bei <em>InnerSense</em> geht's nicht um grosse Worte, sondern um ehrliche Arbeit. Und die beginnt
                    fuer uns mit einem offenen Ohr. Wir nehmen uns Zeit, beraten fair und transparent - ganz
                    ohne Fachchinesisch und Verkaufsdruck.
                </p>
                <p class="lead"><strong>Du kannst uns alles fragen - wirklich.</strong></p>
                <p>
                    Und falls du schon weisst, was du brauchst: Dann meld dich direkt fuer ein Angebot oder
                    einen Termin.<br>
                    Wir freuen uns auf deine Nachricht!
                </p>
            </div>

            <div class="contact-actions">
                <a class="btn btn-primary" href="mailto:geome.selle@gmail.de">Per Mail</a>
                <a class="btn btn-secondary" href="#formular">WhatsApp oder Telefonisch</a>
            </div>
        </div>
    </section>

    <section id="formular" class="contact-form-section">
        <div class="container">
            <h2>Oder einfach ueber unser Kontaktformular</h2>

            <?php if ($contactMessage !== ''): ?>
                <p class="<?= h($contactMessageClass) ?>"><?= h($contactMessage) ?></p>
            <?php endif; ?>

            <form class="contact-form" method="post" action="kontakt.php#formular">
                <div class="field-row">
                    <label>Vorname*
                        <input type="text" name="first_name" value="<?= h($formData['first_name']) ?>" required>
                    </label>
                    <label>Nachname*
                        <input type="text" name="last_name" value="<?= h($formData['last_name']) ?>" required>
                    </label>
                    <label>E-Mail-Adresse*
                        <input type="email" name="email" value="<?= h($formData['email']) ?>" required>
                    </label>
                </div>

                <div class="field-row field-block">
                    <label>Telefonnummer*
                        <input type="text" name="phone" value="<?= h($formData['phone']) ?>" required>
                    </label>
                    <label>Strasse + Hausnummer*
                        <input type="text" name="street_address" value="<?= h($formData['street_address']) ?>" required>
                    </label>
                    <label>PLZ (optional)
                        <input type="text" name="postal_code" value="<?= h($formData['postal_code']) ?>">
                    </label>
                    <label>Ort (optional)
                        <input type="text" name="city" value="<?= h($formData['city']) ?>">
                    </label>
                </div>

                <div class="field-row field-block">
                    <label>Automarke*
                        <input type="text" name="vehicle_brand" value="<?= h($formData['vehicle_brand']) ?>" required>
                    </label>
                    <label>Modell*
                        <input type="text" name="vehicle_model" value="<?= h($formData['vehicle_model']) ?>" required>
                    </label>
                    <label>Fahrzeugtyp (optional)
                        <select name="vehicle_type">
                            <option value="">Bitte waehlen</option>
                            <?php foreach ($vehicleTypeOptions as $option): ?>
                                <option value="<?= h($option) ?>" <?= $formData['vehicle_type'] === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="field-row field-block term-row">
                    <label>Kennzeichen (optional)
                        <input type="text" name="license_plate" value="<?= h($formData['license_plate']) ?>">
                    </label>
                    <label>Reinigungspaket*
                        <select name="cleaning_package" required>
                            <option value="">Bitte waehlen</option>
                            <?php foreach ($cleaningPackageOptions as $option): ?>
                                <option value="<?= h($option) ?>" <?= $formData['cleaning_package'] === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>Wunschtermin (optional)
                        <input type="date" name="preferred_date" value="<?= h($formData['preferred_date']) ?>" data-preferred-date>
                    </label>

                    <label>Wunschzeit (optional)
                        <select name="preferred_time" data-preferred-time>
                            <option value="">Bitte waehlen</option>
                            <?php foreach ($preferredTimeOptions as $slot): ?>
                                <option value="<?= h($slot) ?>" <?= $formData['preferred_time'] === $slot ? 'selected' : '' ?>><?= h($slot) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <label class="field-block">Besondere Wuensche oder Hinweise
                    <textarea name="special_wishes"><?= h($formData['special_wishes']) ?></textarea>
                </label>

                <label class="consent-row">
                    <input type="checkbox" name="consent" value="1" required <?= isset($_POST['consent']) && $_POST['consent'] === '1' ? 'checked' : '' ?>>
                    <span>
                        Durch Klicken des "Abschicken"-Buttons stimme ich zu, dass die im Formular eingegebenen personenbezogenen
                        Daten zur Bearbeitung der Anfrage und der personalisierten Beratung verarbeitet werden. Meine Einwilligungen
                        kann ich jederzeit mit Wirkung fuer die Zukunft postalisch oder per E-Mail widerrufen.
                    </span>
                </label>

                <p class="privacy-note">Es gilt unsere <a href="#">Datenschutzerklaerung</a>.</p>

                <button class="submit-btn" type="submit">Absenden</button>
            </form>
        </div>
    </section>
</main>

<?php require __DIR__ . '/partials/site_footer.php'; ?>
<script>
(() => {
    const dateInput = document.querySelector('[data-preferred-date]');
    const timeSelect = document.querySelector('[data-preferred-time]');
    if (!dateInput || !timeSelect) {
        return;
    }

    const resetTimeOptions = (keepValue = '') => {
        timeSelect.innerHTML = '<option value="">Bitte waehlen</option>';
        if (keepValue) {
            const option = document.createElement('option');
            option.value = keepValue;
            option.textContent = keepValue;
            option.selected = true;
            timeSelect.appendChild(option);
        }
    };

    const updateTimeOptions = async () => {
        const dateValue = dateInput.value;
        const currentValue = timeSelect.value;
        resetTimeOptions();

        if (!dateValue) {
            return;
        }

        try {
            const response = await fetch(`kontakt.php?ajax=available_slots&date=${encodeURIComponent(dateValue)}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const slots = Array.isArray(data.slots) ? data.slots : [];
            slots.forEach((slot) => {
                const option = document.createElement('option');
                option.value = slot;
                option.textContent = slot;
                if (slot === currentValue) {
                    option.selected = true;
                }
                timeSelect.appendChild(option);
            });
        } catch (_error) {
            // Keep fallback option only when loading fails.
        }
    };

    dateInput.addEventListener('change', updateTimeOptions);

    if (dateInput.value) {
        updateTimeOptions();
    }
})();
</script>
</body>
</html>
