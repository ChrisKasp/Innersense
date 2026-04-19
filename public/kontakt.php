<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/env.php';
loadEnv(dirname(__DIR__) . '/config/.env');

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
require_once dirname(__DIR__) . '/src/EmailTemplateService.php';
require_once dirname(__DIR__) . '/src/FormBotProtection.php';
require_once dirname(__DIR__) . '/src/ScheduleSlots.php';

$pdo = db();
EmailTemplateService::ensureTables($pdo);

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
$turnstileSiteKey = trim((string) (getenv('TURNSTILE_SITE_KEY') ?: ''));
$turnstileSecretKey = trim((string) (getenv('TURNSTILE_SECRET_KEY') ?: ''));
$turnstileEnabled = isTurnstileConfigured($turnstileSiteKey, $turnstileSecretKey);

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
    $botProtectionValid = validateBotProtection($_POST, 'kontakt_form');
    $turnstileValid = !$turnstileEnabled || validateTurnstileToken(
        $_POST['cf-turnstile-response'] ?? null,
        $turnstileSecretKey,
        getClientIpAddress()
    );

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
        && $consentGiven
        && $botProtectionValid
        && $turnstileValid;

    if ($isValid) {
        try {
            saveContactRequest(db(), $formData, $consentGiven);

            $to = $contactToEmail;
            $context = [
                'customer.first_name' => $formData['first_name'],
                'customer.last_name' => $formData['last_name'],
                'customer.full_name' => trim($formData['first_name'] . ' ' . $formData['last_name']),
                'customer.email' => $formData['email'],
                'customer.phone' => $formData['phone'],
                'customer.street_address' => $formData['street_address'],
                'customer.postal_code' => $formData['postal_code'],
                'customer.city' => $formData['city'],
                'vehicle.brand' => $formData['vehicle_brand'],
                'vehicle.model' => $formData['vehicle_model'],
                'vehicle.type' => $formData['vehicle_type'],
                'vehicle.license_plate' => $formData['license_plate'],
                'appointment.date' => $formData['preferred_date'],
                'appointment.time' => $formData['preferred_time'],
                'contact.cleaning_package' => $formData['cleaning_package'],
                'contact.special_wishes' => $formData['special_wishes'],
                'contact.products' => '-',
                'contact.needs' => '-',
                'contact.helpful_links' => '-',
                'contact.budget' => '-',
            ];

            $notificationMail = EmailTemplateService::renderForType(
                $pdo,
                EmailTemplateService::TYPE_CONTACT_REQUEST_NOTIFICATION,
                $context
            );
            if ($notificationMail === null) {
                $notificationMail = [
                    'subject' => 'Neue Kontakt-Anfrage von ' . trim($formData['first_name'] . ' ' . $formData['last_name']),
                    'body' => 'Es wurde eine neue Kontakt-Anfrage uebermittelt.',
                ];
            }

            $from = getenv('CONTACT_FROM_EMAIL') ?: 'no-reply@innersense.sellerie.net';
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'From: Innersense Website <' . $from . '>',
                'Reply-To: ' . $formData['email'],
            ];

            @mail($to, $notificationMail['subject'], $notificationMail['body'], implode("\r\n", $headers));

            $ackMail = EmailTemplateService::renderForType(
                $pdo,
                EmailTemplateService::TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT,
                $context
            );
            if ($ackMail !== null && filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $ackHeaders = [
                    'MIME-Version: 1.0',
                    'Content-Type: text/plain; charset=UTF-8',
                    'From: Innersense Website <' . $from . '>',
                    'Reply-To: ' . $contactToEmail,
                ];
                @mail($formData['email'], $ackMail['subject'], $ackMail['body'], implode("\r\n", $ackHeaders));
            }

            header('Location: kontakt.php?contact=success#formular');
            exit;
        } catch (Throwable $exception) {
            $contactMessage = 'Die Anfrage konnte nicht gespeichert werden. Bitte versuche es erneut.';
            $contactMessageClass = 'form-error';
        }
    } else {
        $contactMessage = 'Bitte fuelle alle Pflichtfelder korrekt aus, bestaetige die Sicherheitspruefung und versuche es erneut.';
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
    <?php if ($turnstileEnabled): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
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
                <input type="hidden" name="_started_at" value="<?= (string) time() ?>">
                <p style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                </p>

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

                <p class="privacy-note"><strong>Datenschutzhinweis</strong></p>
                <p class="privacy-note">Die von Ihnen im Kontaktformular eingegebenen Daten (Name, E-Mail-Adresse, Telefonnummer, Anfrageinhalte sowie Angaben zu Leistungspaket, Sonderwünschen und Budget) werden ausschließlich zur Bearbeitung Ihrer Anfrage, zur Kontaktaufnahme sowie zur Erstellung eines Angebots verarbeitet.</p>
                <p class="privacy-note">Die Verarbeitung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO (vorvertragliche Maßnahmen) sowie Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einer effizienten Bearbeitung von Anfragen).</p>
                <p class="privacy-note">Ihre Daten werden per E-Mail an uns übermittelt, eine Bestätigungs-E-Mail wird an Sie versendet und die Daten werden in unserer internen Verwaltungssoftware gespeichert, um Ihre Anfrage weiter zu bearbeiten.</p>
                <p class="privacy-note">Eine Weitergabe an Dritte erfolgt nicht.</p>
                <p class="privacy-note">Weitere Informationen zum Datenschutz und zu Ihren Rechten finden Sie in unserer <a href="datenschutz.php">Datenschutzerklärung</a>.</p>

                <label class="consent-row">
                    <input type="checkbox" name="consent" value="1" required <?= isset($_POST['consent']) && $_POST['consent'] === '1' ? 'checked' : '' ?>>
                    <span>Ich habe die Datenschutzerklärung zur Kenntnis genommen und stimme der Verarbeitung meiner Daten zu.</span>
                </label>

                <?php if ($turnstileEnabled): ?>
                    <div class="cf-turnstile" data-sitekey="<?= h($turnstileSiteKey) ?>"></div>
                <?php endif; ?>

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
