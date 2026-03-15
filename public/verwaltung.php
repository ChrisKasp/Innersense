<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/env.php';
loadEnv(dirname(__DIR__) . '/.env');

$adminPassword = 'innersense4.gerome';
$authConfigFile = dirname(__DIR__) . '/config/verwaltung_auth.php';
$publicContactConfigFile = dirname(__DIR__) . '/config/public_contact.php';
$idleTimeoutSeconds = 600;
$loginError = '';
$settingsNotice = '';
$settingsError = '';
$customerRows = [];
$customerLoadError = '';
$requestRows = [];
$requestLoadError = '';
$vehicleRows = [];
$vehicleLoadError = '';
$appointmentRows = [];
$appointmentLoadError = '';
$vehicleTypeRows = [];
$cleaningPackageRows = [];
$workloadReferenceRows = [];
$dashboardOpenRequests = 0;
$dashboardOpenRequestsSinceYesterday = 0;
$dashboardPlannedAppointments = 0;
$dashboardPlannedAppointmentsThisWeek = 0;
$dashboardCustomerCount = 0;
$dashboardNewCustomersThisMonth = 0;
$dashboardVehicleCount = 0;
$dashboardHomepageHits = 0;
$availableViews = ['dashboard', 'requests', 'customers', 'workload', 'vehicles', 'appointments', 'calendar', 'settings_social', 'settings_password', 'settings_vehicle_types', 'settings_cleaning_packages', 'settings_workload_reference'];
$requestedView = (string) ($_GET['view'] ?? 'dashboard');
$initialView = in_array($requestedView, $availableViews, true) ? $requestedView : 'dashboard';
if ($requestedView === 'settings') {
    $initialView = 'settings_social';
}

if (!isset($_SESSION['verwaltung_csrf']) || !is_string($_SESSION['verwaltung_csrf'])) {
    $_SESSION['verwaltung_csrf'] = bin2hex(random_bytes(24));
}

$csrfToken = (string) $_SESSION['verwaltung_csrf'];

$storedPasswordHash = loadVerwaltungPasswordHash($authConfigFile, $adminPassword);
$socialMediaSettings = loadSocialMediaSettings($publicContactConfigFile, [
    'whatsapp_number' => (string) (getenv('WHATSAPP_NUMBER') ?: ''),
    'facebook_url' => (string) (getenv('FACEBOOK_URL') ?: ''),
    'instagram_url' => (string) (getenv('INSTAGRAM_URL') ?: ''),
    'contact_email' => (string) (getenv('CONTACT_TO_EMAIL') ?: ''),
]);

if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    header('Location: verwaltung.php');
    exit;
}

$isAuthenticated = isset($_SESSION['verwaltung_authenticated']) && $_SESSION['verwaltung_authenticated'] === true;

if ($isAuthenticated) {
    $lastActivity = (int) ($_SESSION['verwaltung_last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeoutSeconds) {
        $_SESSION['verwaltung_authenticated'] = false;
        unset($_SESSION['verwaltung_last_activity']);
        $isAuthenticated = false;
        $loginError = 'Sitzung abgelaufen. Bitte Passwort erneut eingeben.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'login') {
    $inputPassword = (string) ($_POST['password'] ?? '');
    if (password_verify($inputPassword, $storedPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['verwaltung_authenticated'] = true;
        $_SESSION['verwaltung_last_activity'] = time();
        header('Location: verwaltung.php');
        exit;
    }

    $isAuthenticated = false;
    $loginError = 'Passwort ist nicht korrekt.';
}

if ($isAuthenticated) {
    $_SESSION['verwaltung_last_activity'] = time();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'change_password') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_password';
        } else {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, $storedPasswordHash)) {
                $settingsError = 'Aktuelles Passwort ist nicht korrekt.';
                $initialView = 'settings_password';
            } elseif (strlen($newPassword) < 10) {
                $settingsError = 'Das neue Passwort muss mindestens 10 Zeichen haben.';
                $initialView = 'settings_password';
            } elseif (!hash_equals($newPassword, $confirmPassword)) {
                $settingsError = 'Neues Passwort und Wiederholung stimmen nicht überein.';
                $initialView = 'settings_password';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if (!saveVerwaltungPasswordHash($authConfigFile, $newHash)) {
                    $settingsError = 'Passwort konnte nicht gespeichert werden. Dateirechte für config/ prüfen.';
                    $initialView = 'settings_password';
                } else {
                    $storedPasswordHash = $newHash;
                    $settingsNotice = 'Passwort wurde erfolgreich aktualisiert.';
                    $initialView = 'settings_password';
                }
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'save_social_settings') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_social';
        } else {
            $whatsAppNumber = trim((string) ($_POST['whatsapp_number'] ?? ''));
            $facebookUrl = trim((string) ($_POST['facebook_url'] ?? ''));
            $instagramUrl = trim((string) ($_POST['instagram_url'] ?? ''));
            $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));

            if ($facebookUrl !== '' && filter_var($facebookUrl, FILTER_VALIDATE_URL) === false) {
                $settingsError = 'Facebook-URL ist ungültig.';
                $initialView = 'settings_social';
            } elseif ($instagramUrl !== '' && filter_var($instagramUrl, FILTER_VALIDATE_URL) === false) {
                $settingsError = 'Instagram-URL ist ungültig.';
                $initialView = 'settings_social';
            } elseif ($contactEmail === '' || filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
                $settingsError = 'Kontakt-E-Mail ist ungültig.';
                $initialView = 'settings_social';
            } else {
                $nextSettings = [
                    'whatsapp_number' => $whatsAppNumber,
                    'facebook_url' => $facebookUrl,
                    'instagram_url' => $instagramUrl,
                    'contact_email' => $contactEmail,
                ];

                if (!saveSocialMediaSettings($publicContactConfigFile, $nextSettings)) {
                    $settingsError = 'Social-Media-Einstellungen konnten nicht gespeichert werden. Dateirechte für config/ prüfen.';
                    $initialView = 'settings_social';
                } else {
                    $socialMediaSettings = $nextSettings;
                    $settingsNotice = 'Social-Media-Einstellungen wurden gespeichert.';
                    $initialView = 'settings_social';
                }
            }
        }
    }

    require_once dirname(__DIR__) . '/config/database.php';
    ensureVehicleTypeTable(db());
    ensureCleaningPackageTable(db());
    ensureWorkloadReferenceTable(db());

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'add_vehicle_type') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_vehicle_types';
        } else {
            $typeName = trim((string) ($_POST['vehicle_type_name'] ?? ''));

            if ($typeName === '') {
                $settingsError = 'Bitte einen Fahrzeugtyp eingeben.';
                $initialView = 'settings_vehicle_types';
            } elseif (!addVehicleType(db(), $typeName)) {
                $settingsError = 'Fahrzeugtyp konnte nicht gespeichert werden (ggf. bereits vorhanden).';
                $initialView = 'settings_vehicle_types';
            } else {
                $settingsNotice = 'Fahrzeugtyp wurde gespeichert.';
                $initialView = 'settings_vehicle_types';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'delete_vehicle_type') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_vehicle_types';
        } else {
            $typeName = trim((string) ($_POST['vehicle_type_name'] ?? ''));
            $vehicleTable = detectFirstExistingTable(db(), ['customer_vehicles', 'customer_vehicle']);

            if ($typeName === '') {
                $settingsError = 'Ungültiger Fahrzeugtyp.';
                $initialView = 'settings_vehicle_types';
            } elseif (!deleteVehicleType(db(), $typeName, $vehicleTable)) {
                $settingsError = 'Fahrzeugtyp konnte nicht gelöscht werden (evtl. in Verwendung).';
                $initialView = 'settings_vehicle_types';
            } else {
                $settingsNotice = 'Fahrzeugtyp wurde gelöscht.';
                $initialView = 'settings_vehicle_types';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'add_cleaning_package') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_cleaning_packages';
        } else {
            $packageName = trim((string) ($_POST['cleaning_package_name'] ?? ''));

            if ($packageName === '') {
                $settingsError = 'Bitte ein Reinigungspaket eingeben.';
                $initialView = 'settings_cleaning_packages';
            } elseif (!addCleaningPackage(db(), $packageName)) {
                $settingsError = 'Reinigungspaket konnte nicht gespeichert werden (ggf. bereits vorhanden).';
                $initialView = 'settings_cleaning_packages';
            } else {
                $settingsNotice = 'Reinigungspaket wurde gespeichert.';
                $initialView = 'settings_cleaning_packages';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'delete_cleaning_package') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_cleaning_packages';
        } else {
            $packageName = trim((string) ($_POST['cleaning_package_name'] ?? ''));
            $requestTable = detectFirstExistingTable(db(), ['customer_requests', 'customer_request']);

            if ($packageName === '') {
                $settingsError = 'Ungültiges Reinigungspaket.';
                $initialView = 'settings_cleaning_packages';
            } elseif (!deleteCleaningPackage(db(), $packageName, $requestTable)) {
                $settingsError = 'Reinigungspaket konnte nicht gelöscht werden (evtl. in Verwendung).';
                $initialView = 'settings_cleaning_packages';
            } else {
                $settingsNotice = 'Reinigungspaket wurde gelöscht.';
                $initialView = 'settings_cleaning_packages';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'add_workload_reference') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_workload_reference';
        } else {
            $cleaningPackage = trim((string) ($_POST['cleaning_package_name'] ?? ''));
            $vehicleType = trim((string) ($_POST['vehicle_type_name'] ?? ''));
            $timeEffort = trim((string) ($_POST['time_effort'] ?? ''));
            $netPrice = trim((string) ($_POST['net_price'] ?? ''));

            if ($cleaningPackage === '' || $vehicleType === '') {
                $settingsError = 'Bitte Reinigungspaket und Fahrzeugtyp auswählen.';
                $initialView = 'settings_workload_reference';
            } elseif (!is_numeric($timeEffort) || (float) $timeEffort <= 0) {
                $settingsError = 'Bitte einen gültigen Zeitaufwand eingeben.';
                $initialView = 'settings_workload_reference';
            } elseif (!is_numeric($netPrice) || (float) $netPrice < 0) {
                $settingsError = 'Bitte einen gültigen Nettopreis eingeben.';
                $initialView = 'settings_workload_reference';
            } elseif (!addWorkloadReference(db(), $cleaningPackage, $vehicleType, (float) $timeEffort, (float) $netPrice)) {
                $settingsError = 'Workload-Referenz konnte nicht gespeichert werden.';
                $initialView = 'settings_workload_reference';
            } else {
                $settingsNotice = 'Workload-Referenz wurde gespeichert.';
                $initialView = 'settings_workload_reference';
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'delete_workload_reference') {
        $postedToken = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals($csrfToken, $postedToken)) {
            $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
            $initialView = 'settings_workload_reference';
        } else {
            $referenceId = max(0, (int) ($_POST['workload_reference_id'] ?? 0));
            if ($referenceId <= 0) {
                $settingsError = 'Ungültige Workload-Referenz.';
                $initialView = 'settings_workload_reference';
            } elseif (!deleteWorkloadReference(db(), $referenceId)) {
                $settingsError = 'Workload-Referenz konnte nicht gelöscht werden.';
                $initialView = 'settings_workload_reference';
            } else {
                $settingsNotice = 'Workload-Referenz wurde gelöscht.';
                $initialView = 'settings_workload_reference';
            }
        }
    }

    try {
        $stmt = db()->query(
            "SELECT c.id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    c.phone,
                    c.street_address,
                    c.postal_code,
                    c.city,
                    c.created_at,
                    COUNT(cv.id) AS vehicle_count
             FROM customer c
             LEFT JOIN customer_vehicle cv ON cv.customer_id = c.id
               GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.street_address, c.postal_code, c.city, c.created_at
             ORDER BY c.created_at DESC, c.id DESC"
        );
        $rows = $stmt->fetchAll();
        $customerRows = is_array($rows) ? $rows : [];
    } catch (Throwable $exception) {
        $customerRows = [];
        $customerLoadError = 'Kundendaten konnten nicht geladen werden.';
    }

    $requestQueryByTable = static function (string $requestTable): string {
        return "SELECT
                    cr.id,
                    COALESCE(cp.package_name, cr.cleaning_package) AS cleaning_package,
                    cr.preferred_date,
                    cr.status,
                    cr.created_at,
                    c.first_name,
                    c.last_name
                FROM {$requestTable} cr
                LEFT JOIN customer c ON c.id = cr.customer_id
                LEFT JOIN cleaning_package cp ON cp.package_name = cr.cleaning_package
                ORDER BY cr.created_at DESC, cr.id DESC";
    };

    try {
        $stmt = db()->query($requestQueryByTable('customer_requests'));
        $rows = $stmt->fetchAll();
        $requestRows = is_array($rows) ? $rows : [];
    } catch (Throwable $pluralException) {
        try {
            $stmt = db()->query($requestQueryByTable('customer_request'));
            $rows = $stmt->fetchAll();
            $requestRows = is_array($rows) ? $rows : [];
        } catch (Throwable $singularException) {
            $requestRows = [];
            $requestLoadError = 'Anfragedaten konnten nicht geladen werden.';
        }
    }

    $vehicleQueryByTable = static function (string $vehicleTable): string {
        return "SELECT
                    cv.id,
                    cv.brand,
                    cv.model,
                    COALESCE(vto.type_name, cv.vehicle_type) AS vehicle_type,
                    cv.license_plate,
                    c.first_name,
                    c.last_name
                FROM {$vehicleTable} cv
                LEFT JOIN customer c ON c.id = cv.customer_id
                LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
                ORDER BY cv.created_at DESC, cv.id DESC";
    };

    try {
        $stmt = db()->query($vehicleQueryByTable('customer_vehicles'));
        $rows = $stmt->fetchAll();
        $vehicleRows = is_array($rows) ? $rows : [];
    } catch (Throwable $pluralException) {
        try {
            $stmt = db()->query($vehicleQueryByTable('customer_vehicle'));
            $rows = $stmt->fetchAll();
            $vehicleRows = is_array($rows) ? $rows : [];
        } catch (Throwable $singularException) {
            $vehicleRows = [];
            $vehicleLoadError = 'Fahrzeugdaten konnten nicht geladen werden.';
        }
    }

    $requestTableCandidates = ['customer_requests', 'customer_request'];
    $vehicleTableCandidates = ['customer_vehicles', 'customer_vehicle'];
    $appointmentsLoaded = false;

    foreach ($requestTableCandidates as $requestTable) {
        foreach ($vehicleTableCandidates as $vehicleTable) {
            try {
                $stmt = db()->query(
                    "SELECT
                        cr.id,
                        cr.preferred_date,
                        cr.preferred_time,
                        COALESCE(cp.package_name, cr.cleaning_package) AS cleaning_package,
                        cr.status,
                        c.first_name,
                        c.last_name,
                        cv.brand,
                        cv.model,
                        COALESCE(vto.type_name, cv.vehicle_type) AS vehicle_type
                    FROM {$requestTable} cr
                    LEFT JOIN customer c ON c.id = cr.customer_id
                    LEFT JOIN {$vehicleTable} cv ON cv.id = cr.customer_vehicle_id
                    LEFT JOIN vehicle_type_option vto ON vto.type_name = cv.vehicle_type
                    LEFT JOIN cleaning_package cp ON cp.package_name = cr.cleaning_package
                    ORDER BY
                        cr.preferred_date IS NULL,
                        cr.preferred_date ASC,
                        cr.preferred_time ASC,
                        cr.id DESC"
                );

                $rows = $stmt->fetchAll();
                $appointmentRows = is_array($rows) ? $rows : [];
                $appointmentsLoaded = true;
                break 2;
            } catch (Throwable $exception) {
                continue;
            }
        }
    }

    if (!$appointmentsLoaded) {
        $appointmentRows = [];
        $appointmentLoadError = 'Termindaten konnten nicht geladen werden.';
    }

    if ((string) ($_GET['download'] ?? '') === 'appointments_ical') {
        $fileName = 'innersense-termine-' . date('Ymd') . '.ics';
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo buildAppointmentsIcal($appointmentRows);
        exit;
    }

    $dashboardCustomerCount = count($customerRows);
    $dashboardVehicleCount = count($vehicleRows);

    $dashboardOpenRequests = count(array_filter(
        $requestRows,
        static function (array $request): bool {
            $status = strtolower(trim((string) ($request['status'] ?? 'new')));
            return !in_array($status, ['completed', 'cancelled'], true);
        }
    ));

    $yesterdayThreshold = strtotime('-1 day');
    if ($yesterdayThreshold !== false) {
        $dashboardOpenRequestsSinceYesterday = count(array_filter(
            $requestRows,
            static function (array $request) use ($yesterdayThreshold): bool {
                $status = strtolower(trim((string) ($request['status'] ?? 'new')));
                if (in_array($status, ['completed', 'cancelled'], true)) {
                    return false;
                }

                $createdAt = trim((string) ($request['created_at'] ?? ''));
                $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
                return $createdTs !== false && $createdTs >= $yesterdayThreshold;
            }
        ));
    }

    $dashboardPlannedAppointments = count(array_filter(
        $appointmentRows,
        static function (array $appointment): bool {
            $status = strtolower(trim((string) ($appointment['status'] ?? '')));
            if ($status === 'scheduled') {
                return true;
            }

            $preferredDate = trim((string) ($appointment['preferred_date'] ?? ''));
            return $preferredDate !== '';
        }
    ));

    $currentWeek = date('o-W');
    $dashboardPlannedAppointmentsThisWeek = count(array_filter(
        $appointmentRows,
        static function (array $appointment) use ($currentWeek): bool {
            $preferredDate = trim((string) ($appointment['preferred_date'] ?? ''));
            if ($preferredDate === '') {
                return false;
            }

            $ts = strtotime($preferredDate);
            return $ts !== false && date('o-W', $ts) === $currentWeek;
        }
    ));

    $currentMonth = date('Y-m');
    $dashboardNewCustomersThisMonth = count(array_filter(
        $customerRows,
        static function (array $customer) use ($currentMonth): bool {
            $createdAt = trim((string) ($customer['created_at'] ?? ''));
            $ts = $createdAt !== '' ? strtotime($createdAt) : false;
            return $ts !== false && date('Y-m', $ts) === $currentMonth;
        }
    ));

    $dashboardHomepageHits = getPageHitCount(db(), 'index');
    $vehicleTypeRows = getVehicleTypes(db());
    $cleaningPackageRows = getCleaningPackages(db());
    $workloadReferenceRows = getWorkloadReferences(db());
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function loadVerwaltungPasswordHash(string $configFile, string $defaultPassword): string
{
    if (is_file($configFile) && is_readable($configFile)) {
        $data = require $configFile;
        if (
            is_array($data)
            && isset($data['password_hash'])
            && is_string($data['password_hash'])
            && $data['password_hash'] !== ''
        ) {
            return $data['password_hash'];
        }
    }

    $defaultHash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    saveVerwaltungPasswordHash($configFile, $defaultHash);

    return $defaultHash;
}

function saveVerwaltungPasswordHash(string $configFile, string $passwordHash): bool
{
    $payload = [
        'password_hash' => $passwordHash,
    ];

    $content = "<?php\nreturn " . var_export($payload, true) . ";\n";
    $directory = dirname($configFile);

    if (!is_dir($directory) || !is_writable($directory)) {
        return false;
    }

    $tmpFile = $configFile . '.tmp';
    $bytes = @file_put_contents($tmpFile, $content, LOCK_EX);
    if ($bytes === false) {
        return false;
    }

    if (!@rename($tmpFile, $configFile)) {
        @unlink($tmpFile);
        return false;
    }

    @chmod($configFile, 0640);
    return true;
}

/**
 * @param array<string, string> $defaults
 * @return array<string, string>
 */
function loadSocialMediaSettings(string $configFile, array $defaults): array
{
    $settings = [
        'whatsapp_number' => (string) ($defaults['whatsapp_number'] ?? ''),
        'facebook_url' => (string) ($defaults['facebook_url'] ?? ''),
        'instagram_url' => (string) ($defaults['instagram_url'] ?? ''),
        'contact_email' => (string) ($defaults['contact_email'] ?? ''),
    ];

    if (!is_file($configFile) || !is_readable($configFile)) {
        return $settings;
    }

    $data = require $configFile;
    if (!is_array($data)) {
        return $settings;
    }

    foreach (array_keys($settings) as $key) {
        if (isset($data[$key]) && is_string($data[$key])) {
            $settings[$key] = trim($data[$key]);
        }
    }

    return $settings;
}

/**
 * @param array<string, string> $settings
 */
function saveSocialMediaSettings(string $configFile, array $settings): bool
{
    $payload = [
        'whatsapp_number' => (string) ($settings['whatsapp_number'] ?? ''),
        'facebook_url' => (string) ($settings['facebook_url'] ?? ''),
        'instagram_url' => (string) ($settings['instagram_url'] ?? ''),
        'contact_email' => (string) ($settings['contact_email'] ?? ''),
    ];

    $content = "<?php\nreturn " . var_export($payload, true) . ";\n";
    $directory = dirname($configFile);

    if (!is_dir($directory) || !is_writable($directory)) {
        return false;
    }

    $tmpFile = $configFile . '.tmp';
    $bytes = @file_put_contents($tmpFile, $content, LOCK_EX);
    if ($bytes === false) {
        return false;
    }

    if (!@rename($tmpFile, $configFile)) {
        @unlink($tmpFile);
        return false;
    }

    @chmod($configFile, 0640);
    return true;
}

function getPageHitCount(PDO $pdo, string $pageKey): int
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS site_page_hit (
                page_key VARCHAR(64) NOT NULL,
                hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (page_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $stmt = $pdo->prepare('SELECT hit_count FROM site_page_hit WHERE page_key = :page_key LIMIT 1');
        $stmt->execute([':page_key' => $pageKey]);
        $row = $stmt->fetch();

        return is_array($row) ? max(0, (int) ($row['hit_count'] ?? 0)) : 0;
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * @param list<array<string, mixed>> $appointments
 */
function buildAppointmentsIcal(array $appointments): string
{
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Innersense//Terminliste//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:Innersense Termine',
        'X-WR-TIMEZONE:Europe/Berlin',
        'BEGIN:VTIMEZONE',
        'TZID:Europe/Berlin',
        'X-LIC-LOCATION:Europe/Berlin',
        'BEGIN:STANDARD',
        'TZOFFSETFROM:+0200',
        'TZOFFSETTO:+0100',
        'TZNAME:CET',
        'DTSTART:19701025T030000',
        'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
        'END:STANDARD',
        'BEGIN:DAYLIGHT',
        'TZOFFSETFROM:+0100',
        'TZOFFSETTO:+0200',
        'TZNAME:CEST',
        'DTSTART:19700329T020000',
        'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
        'END:DAYLIGHT',
        'END:VTIMEZONE',
    ];

    $dtStamp = gmdate('Ymd\\THis\\Z');

    foreach ($appointments as $appointment) {
        $preferredDateRaw = trim((string) ($appointment['preferred_date'] ?? ''));
        if ($preferredDateRaw === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDateRaw) !== 1) {
            continue;
        }

        $preferredTimeRaw = trim((string) ($appointment['preferred_time'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $preferredDateRaw);
        if (!$date instanceof DateTimeImmutable) {
            continue;
        }

        $id = max(0, (int) ($appointment['id'] ?? 0));
        $uid = 'appointment-' . $id . '@innersense';

        $customerName = trim(((string) ($appointment['first_name'] ?? '')) . ' ' . ((string) ($appointment['last_name'] ?? '')));
        $vehicleLabel = trim(((string) ($appointment['brand'] ?? '')) . ' ' . ((string) ($appointment['model'] ?? '')));
        $vehicleType = trim((string) ($appointment['vehicle_type'] ?? ''));
        $cleaningPackage = trim((string) ($appointment['cleaning_package'] ?? ''));
        $status = trim((string) ($appointment['status'] ?? ''));

        $summary = $customerName !== '' ? 'Termin - ' . $customerName : 'Termin';
        $descriptionParts = [];
        if ($cleaningPackage !== '') {
            $descriptionParts[] = 'Paket: ' . $cleaningPackage;
        }
        if ($vehicleLabel !== '') {
            $descriptionParts[] = 'Fahrzeug: ' . $vehicleLabel . ($vehicleType !== '' ? ' (' . $vehicleType . ')' : '');
        }
        if ($status !== '') {
            $descriptionParts[] = 'Status: ' . $status;
        }
        $description = $descriptionParts !== [] ? implode("\\n", $descriptionParts) : 'Termin aus der Innersense-Verwaltung';

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . escapeIcalText($uid);
        $lines[] = 'DTSTAMP:' . $dtStamp;
        $lines[] = 'SUMMARY:' . escapeIcalText($summary);
        $lines[] = 'DESCRIPTION:' . escapeIcalText($description);

        if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $preferredTimeRaw, $timeMatch) === 1) {
            $hour = (int) $timeMatch[1];
            $minute = (int) $timeMatch[2];
            $startDateTime = $date->setTime($hour, $minute, 0);
            $endDateTime = $startDateTime->modify('+1 hour');

            $lines[] = 'DTSTART;TZID=Europe/Berlin:' . $startDateTime->format('Ymd\\THis');
            $lines[] = 'DTEND;TZID=Europe/Berlin:' . $endDateTime->format('Ymd\\THis');
        } else {
            $endDate = $date->modify('+1 day');
            $lines[] = 'DTSTART;VALUE=DATE:' . $date->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $endDate->format('Ymd');
        }

        $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
}

function escapeIcalText(string $value): string
{
    $escaped = str_replace('\\', '\\\\', $value);
    $escaped = str_replace(["\r\n", "\n", "\r"], '\\n', $escaped);
    $escaped = str_replace(',', '\\,', $escaped);
    return str_replace(';', '\\;', $escaped);
}

/**
 * @param list<string> $tableCandidates
 */
function detectFirstExistingTable(PDO $pdo, array $tableCandidates): ?string
{
    foreach ($tableCandidates as $table) {
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $table]);
        if ($stmt->fetch() !== false) {
            return $table;
        }
    }

    return null;
}

function ensureVehicleTypeTable(PDO $pdo): void
{
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
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS workload_reference (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cleaning_package VARCHAR(120) NOT NULL,
            vehicle_type VARCHAR(120) NOT NULL,
            time_effort DECIMAL(8,2) NOT NULL,
            net_price DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_workload_reference_cleaning_vehicle (cleaning_package, vehicle_type),
            KEY idx_workload_reference_cleaning_package (cleaning_package),
            KEY idx_workload_reference_vehicle_type (vehicle_type),
            CONSTRAINT fk_workload_reference_cleaning_package
                FOREIGN KEY (cleaning_package)
                REFERENCES cleaning_package (package_name)
                ON DELETE RESTRICT
                ON UPDATE CASCADE,
            CONSTRAINT fk_workload_reference_vehicle_type
                FOREIGN KEY (vehicle_type)
                REFERENCES vehicle_type_option (type_name)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
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

if (!$isAuthenticated):
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Verwaltung Login</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="login-screen">
    <section class="login-card" aria-label="Verwaltung Login">
        <h1>Verwaltung</h1>
        <p>Bitte Passwort eingeben, um den Verwaltungsbereich zu öffnen.</p>

        <?php if ($loginError !== ''): ?>
            <p class="login-error"><?= e($loginError) ?></p>
        <?php endif; ?>

        <form method="post" action="verwaltung.php" class="login-form">
            <input type="hidden" name="auth_action" value="login">
            <label for="password">Passwort</label>
            <div class="password-field-wrap">
                <input id="password" name="password" type="password" autocomplete="current-password" required autofocus data-password-field>
                <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                    <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                </button>
            </div>
            <button type="submit">Anmelden</button>
        </form>
    </section>
</main>
<script>
(() => {
    const toggleButtons = Array.from(document.querySelectorAll('[data-password-toggle-btn]'));

    toggleButtons.forEach((button) => {
        const wrapper = button.closest('.password-field-wrap');
        if (!wrapper) {
            return;
        }

        const input = wrapper.querySelector('[data-password-field]');
        if (!input) {
            return;
        }

        const icon = button.querySelector('[data-password-toggle-icon]');

        button.addEventListener('click', () => {
            const nextType = input.type === 'password' ? 'text' : 'password';
            input.type = nextType;

            if (icon) {
                icon.textContent = nextType === 'text' ? '\u{1F648}' : '\u{1F441}\uFE0F';
            }

            button.classList.toggle('is-active', nextType === 'text');
            button.setAttribute('aria-label', nextType === 'text' ? 'Passwort verbergen' : 'Passwort anzeigen');
        });
    });
})();
</script>
</body>
</html>
<?php
exit;
endif;
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Verwaltung</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="admin-shell">
    <aside class="sidebar" aria-label="Verwaltungsmenü">
        <div class="brand-card">
            <a href="index.php" class="brand-link" aria-label="Zur Startseite">
                <img class="brand-logo" src="assets/icons/Innersense_Logo.avif" alt="Innersense Logo">
            </a>
        </div>

        <button class="menu-toggle" type="button" aria-label="Menü umschalten">&#9776; Menü umschalten</button>

        <nav class="menu-list" data-menu-list>
            <button class="menu-item is-active" type="button" data-view="dashboard"><span class="menu-icon" aria-hidden="true">&#9638;</span><span>Übersicht</span></button>
            <button class="menu-item" type="button" data-view="requests"><span class="menu-icon" aria-hidden="true">&#9993;</span><span>Anfragen</span></button>
            <button class="menu-item" type="button" data-view="customers"><span class="menu-icon" aria-hidden="true">&#128101;</span><span>Kunden</span></button>
            <button class="menu-item" type="button" data-view="vehicles"><span class="menu-icon" aria-hidden="true">&#128663;</span><span>Fahrzeuge</span></button>
            <button class="menu-item" type="button" data-view="appointments"><span class="menu-icon" aria-hidden="true">&#128340;</span><span>Termine</span></button>
            <button class="menu-item" type="button" data-view="calendar"><span class="menu-icon" aria-hidden="true">&#128197;</span><span>Kalender</span></button>
            <button class="menu-item" type="button" data-view="settings_social" data-settings-parent aria-expanded="false"><span class="menu-icon" aria-hidden="true">&#9881;</span><span>Einstellungen</span></button>
            <div class="menu-submenu" data-settings-submenu hidden>
                <button class="menu-item is-subitem" type="button" data-view="settings_social"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>Social Media &amp; Kontakt</span></button>
                <button class="menu-item is-subitem" type="button" data-view="settings_password"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>Passwort Verwaltung</span></button>
                <button class="menu-item is-subitem" type="button" data-view="settings_vehicle_types"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>Fahrzeugtypen</span></button>
                <button class="menu-item is-subitem" type="button" data-view="settings_cleaning_packages"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>Reinigungspakete</span></button>
                <button class="menu-item is-subitem" type="button" data-view="workload"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>Aufwände</span></button>
            </div>
        </nav>

        <div class="sidebar-footer">
            <a class="logout" href="index.php">Zurück zur Hauptseite</a>
        </div>
    </aside>

    <main class="content-area">
        <header class="content-head">
            <div>
                <h1 data-view-title>Übersicht</h1>
                <p data-view-subtitle>Alle aktuellen Kennzahlen und offenen Punkte auf einen Blick.</p>
            </div>
            <button class="help" type="button" aria-label="Hilfe">?</button>
        </header>

        <section class="panel" data-view-panel="dashboard">
            <div class="cards-grid">
                <article class="stat-card">
                    <h2>Offene Anfragen</h2>
                    <p class="value"><?= e((string) $dashboardOpenRequests) ?></p>
                    <small><?= e((string) $dashboardOpenRequestsSinceYesterday) ?> seit gestern</small>
                </article>
                <article class="stat-card">
                    <h2>Geplante Termine</h2>
                    <p class="value"><?= e((string) $dashboardPlannedAppointments) ?></p>
                    <small><?= e((string) $dashboardPlannedAppointmentsThisWeek) ?> in dieser Woche</small>
                </article>
                <article class="stat-card">
                    <h2>Aktive Kunden</h2>
                    <p class="value"><?= e((string) $dashboardCustomerCount) ?></p>
                    <small><?= e((string) $dashboardNewCustomersThisMonth) ?> neu in diesem Monat</small>
                </article>
                <article class="stat-card">
                    <h2>Fahrzeuge in Datenbank</h2>
                    <p class="value"><?= e((string) $dashboardVehicleCount) ?></p>
                    <small>inkl. Mehrfahrzeug-Kunden</small>
                </article>
                <article class="stat-card">
                    <h2>Hauptseite aufgerufen</h2>
                    <p class="value"><?= e((string) $dashboardHomepageHits) ?></p>
                    <small>gesamt seit Aktivierung</small>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="requests">
            <div class="panel-actions">
                <a class="primary-link" href="request_detail.php">+ Neu</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Kunde</th>
                        <th>Paket</th>
                        <th>Wunschdatum</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($requestLoadError !== ''): ?>
                        <tr>
                            <td colspan="6"><?= e($requestLoadError) ?></td>
                        </tr>
                    <?php elseif ($requestRows === []): ?>
                        <tr>
                            <td colspan="6">Noch keine Anfragen vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requestRows as $request): ?>
                            <?php
                            $customerName = trim(((string) ($request['first_name'] ?? '')) . ' ' . ((string) ($request['last_name'] ?? '')));
                            $createdAtRaw = (string) ($request['created_at'] ?? '');
                            $preferredDateRaw = (string) ($request['preferred_date'] ?? '');
                            $createdAt = $createdAtRaw !== '' ? date('d.m.Y', strtotime($createdAtRaw)) : '-';
                            $preferredDate = $preferredDateRaw !== '' ? date('d.m.Y', strtotime($preferredDateRaw)) : '-';
                            $status = strtolower(trim((string) ($request['status'] ?? 'new')));

                            $statusLabelMap = [
                                'new' => 'neu',
                                'contacted' => 'kontaktiert',
                                'scheduled' => 'geplant',
                                'completed' => 'abgeschlossen',
                                'cancelled' => 'storniert',
                            ];
                            $statusClassMap = [
                                'new' => 'warning',
                                'contacted' => 'ok',
                                'scheduled' => 'neutral',
                                'completed' => 'ok',
                                'cancelled' => 'neutral',
                            ];
                            $statusLabel = $statusLabelMap[$status] ?? ($status !== '' ? $status : 'neu');
                            $statusClass = $statusClassMap[$status] ?? 'neutral';
                            ?>
                            <tr>
                                <td><?= e($createdAt) ?></td>
                                <td><?= e($customerName !== '' ? $customerName : 'Unbekannt') ?></td>
                                <td><?= e((string) (($request['cleaning_package'] ?? '') !== '' ? $request['cleaning_package'] : '-')) ?></td>
                                <td><?= e($preferredDate) ?></td>
                                <td><span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                <td>
                                    <a
                                        class="action-icon"
                                        href="request_detail.php?id=<?= e((string) ($request['id'] ?? 0)) ?>"
                                        aria-label="Anfrage im Detail anzeigen"
                                        title="Details anzeigen"
                                    >&#128269;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="customers">
            <div class="panel-actions">
                <a class="primary-link" href="customer_create.php">+ Neu</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>E-Mail</th>
                        <th>Telefon</th>
                        <th>Adresse</th>
                        <th>Fahrzeuge</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($customerLoadError !== ''): ?>
                        <tr>
                            <td colspan="6"><?= e($customerLoadError) ?></td>
                        </tr>
                    <?php elseif ($customerRows === []): ?>
                        <tr>
                            <td colspan="6">Noch keine Kunden vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customerRows as $customer): ?>
                            <?php
                            $fullName = trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? '')));
                            $streetAddress = trim((string) ($customer['street_address'] ?? ''));
                            $postalCode = trim((string) ($customer['postal_code'] ?? ''));
                            $city = trim((string) ($customer['city'] ?? ''));

                            $addressParts = array_values(array_filter([$streetAddress, trim($postalCode . ' ' . $city)]));
                            $address = $addressParts === [] ? '-' : implode(', ', $addressParts);
                            ?>
                            <tr>
                                <td><?= e($fullName !== '' ? $fullName : 'Unbekannt') ?></td>
                                <td><?= e((string) ($customer['email'] ?? '-')) ?></td>
                                <td><?= e((string) (($customer['phone'] ?? '') !== '' ? $customer['phone'] : '-')) ?></td>
                                <td><?= e($address) ?></td>
                                <td><?= e((string) ($customer['vehicle_count'] ?? '0')) ?></td>
                                <td>
                                    <a
                                        class="action-icon"
                                        href="customer_detail.php?id=<?= e((string) ($customer['id'] ?? 0)) ?>"
                                        aria-label="Kunde im Detail anzeigen"
                                        title="Details anzeigen"
                                    >&#128269;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="vehicles">
            <div class="panel-actions">
                <a class="primary-link" href="vehicle_detail.php">+ Neu</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Marke</th>
                        <th>Modell</th>
                        <th>Typ</th>
                        <th>Kennzeichen</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($vehicleLoadError !== ''): ?>
                        <tr>
                            <td colspan="6"><?= e($vehicleLoadError) ?></td>
                        </tr>
                    <?php elseif ($vehicleRows === []): ?>
                        <tr>
                            <td colspan="6">Noch keine Fahrzeuge vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehicleRows as $vehicle): ?>
                            <?php
                            $vehicleOwner = trim(((string) ($vehicle['first_name'] ?? '')) . ' ' . ((string) ($vehicle['last_name'] ?? '')));
                            $vehicleType = trim((string) ($vehicle['vehicle_type'] ?? ''));
                            $licensePlate = trim((string) ($vehicle['license_plate'] ?? ''));
                            ?>
                            <tr>
                                <td><?= e($vehicleOwner !== '' ? $vehicleOwner : 'Unbekannt') ?></td>
                                <td><?= e((string) (($vehicle['brand'] ?? '') !== '' ? $vehicle['brand'] : '-')) ?></td>
                                <td><?= e((string) (($vehicle['model'] ?? '') !== '' ? $vehicle['model'] : '-')) ?></td>
                                <td><?= e($vehicleType !== '' ? $vehicleType : '-') ?></td>
                                <td><?= e($licensePlate !== '' ? $licensePlate : '-') ?></td>
                                <td>
                                    <a
                                        class="action-icon"
                                        href="vehicle_detail.php?id=<?= e((string) ($vehicle['id'] ?? 0)) ?>"
                                        aria-label="Fahrzeug im Detail anzeigen"
                                        title="Details anzeigen"
                                    >&#128269;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="workload">
            <div class="panel-actions">
                <a class="primary-link" href="workload_reference_detail.php?create=1">+ Neu</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Reinigungspaket</th>
                        <th>Fahrzeugtyp</th>
                        <th>Zeitaufwand</th>
                        <th>Nettopreis</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($workloadReferenceRows === []): ?>
                        <tr>
                            <td colspan="5">Noch keine Arbeits- und Kosten-Aufwände vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($workloadReferenceRows as $workloadReference): ?>
                            <?php
                            $referenceId = (int) ($workloadReference['id'] ?? 0);
                            $packageName = trim((string) ($workloadReference['cleaning_package'] ?? ''));
                            $vehicleType = trim((string) ($workloadReference['vehicle_type'] ?? ''));
                            $timeEffortValue = (float) ($workloadReference['time_effort'] ?? 0);
                            $netPriceValue = (float) ($workloadReference['net_price'] ?? 0);
                            ?>
                            <tr>
                                <td><?= e($packageName !== '' ? $packageName : '-') ?></td>
                                <td><?= e($vehicleType !== '' ? $vehicleType : '-') ?></td>
                                <td><?= e(number_format($timeEffortValue, 2, ',', '.')) ?> h</td>
                                <td><?= e(number_format($netPriceValue, 2, ',', '.')) ?> EUR</td>
                                <td>
                                    <a
                                        class="action-icon"
                                        href="workload_reference_detail.php?id=<?= e((string) $referenceId) ?>"
                                        aria-label="Aufwandseintrag im Detail anzeigen"
                                        title="Details anzeigen"
                                    >&#128269;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="appointments">
            <div class="panel-actions">
                <a class="primary-link ical-download-link" href="verwaltung.php?view=appointments&amp;download=appointments_ical">iCal Download</a>
                <a class="primary-link" href="appointment_detail.php?create=1">+ Neu</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Termin</th>
                        <th>Kunde</th>
                        <th>Fahrzeug</th>
                        <th>Paket</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($appointmentLoadError !== ''): ?>
                        <tr>
                            <td colspan="6"><?= e($appointmentLoadError) ?></td>
                        </tr>
                    <?php elseif ($appointmentRows === []): ?>
                        <tr>
                            <td colspan="6">Noch keine Termine vorhanden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointmentRows as $appointment): ?>
                            <?php
                            $customerName = trim(((string) ($appointment['first_name'] ?? '')) . ' ' . ((string) ($appointment['last_name'] ?? '')));
                            $vehicleLabel = trim(((string) ($appointment['brand'] ?? '')) . ' ' . ((string) ($appointment['model'] ?? '')));
                            $vehicleType = trim((string) ($appointment['vehicle_type'] ?? ''));
                            $preferredDateRaw = trim((string) ($appointment['preferred_date'] ?? ''));
                            $preferredTimeRaw = trim((string) ($appointment['preferred_time'] ?? ''));

                            $datePart = $preferredDateRaw !== '' ? date('d.m.Y', strtotime($preferredDateRaw)) : '-';
                            $appointmentDateTime = $datePart;
                            if ($preferredTimeRaw !== '' && $datePart !== '-') {
                                $appointmentDateTime .= ' ' . $preferredTimeRaw;
                            }

                            $status = strtolower(trim((string) ($appointment['status'] ?? 'new')));
                            $statusLabelMap = [
                                'new' => 'neu',
                                'contacted' => 'kontaktiert',
                                'scheduled' => 'geplant',
                                'completed' => 'abgeschlossen',
                                'cancelled' => 'storniert',
                            ];
                            $statusClassMap = [
                                'new' => 'warning',
                                'contacted' => 'ok',
                                'scheduled' => 'neutral',
                                'completed' => 'ok',
                                'cancelled' => 'neutral',
                            ];
                            $statusLabel = $statusLabelMap[$status] ?? ($status !== '' ? $status : 'neu');
                            $statusClass = $statusClassMap[$status] ?? 'neutral';
                            ?>
                            <tr>
                                <td><?= e($appointmentDateTime) ?></td>
                                <td><?= e($customerName !== '' ? $customerName : 'Unbekannt') ?></td>
                                <td><?= e($vehicleLabel !== '' ? $vehicleLabel . ($vehicleType !== '' ? ' (' . $vehicleType . ')' : '') : '-') ?></td>
                                <td><?= e((string) (($appointment['cleaning_package'] ?? '') !== '' ? $appointment['cleaning_package'] : '-')) ?></td>
                                <td><span class="badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                                <td>
                                    <a
                                        class="action-icon"
                                        href="appointment_detail.php?id=<?= e((string) ($appointment['id'] ?? 0)) ?>"
                                        aria-label="Termin im Detail anzeigen"
                                        title="Details anzeigen"
                                    >&#128269;</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_social">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Social Media & Kontakt</h2>
                    <p>WhatsApp-Nummer, Kontakt-E-Mail sowie Facebook- und Instagram-Links für Website und Kontaktformular bearbeiten.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_social'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_social'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_social" class="settings-form">
                        <input type="hidden" name="auth_action" value="save_social_settings">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="whatsapp_number">WhatsApp Nummer</label>
                        <input
                            id="whatsapp_number"
                            type="text"
                            name="whatsapp_number"
                            value="<?= e((string) ($socialMediaSettings['whatsapp_number'] ?? '')) ?>"
                            placeholder="z. B. +491739982284"
                        >

                        <label for="facebook_url">Facebook URL</label>
                        <input
                            id="facebook_url"
                            type="url"
                            name="facebook_url"
                            value="<?= e((string) ($socialMediaSettings['facebook_url'] ?? '')) ?>"
                            placeholder="https://www.facebook.com/..."
                        >

                        <label for="instagram_url">Instagram URL</label>
                        <input
                            id="instagram_url"
                            type="url"
                            name="instagram_url"
                            value="<?= e((string) ($socialMediaSettings['instagram_url'] ?? '')) ?>"
                            placeholder="https://www.instagram.com/..."
                        >

                        <label for="contact_email">Kontakt-E-Mail</label>
                        <input
                            id="contact_email"
                            type="email"
                            name="contact_email"
                            value="<?= e((string) ($socialMediaSettings['contact_email'] ?? '')) ?>"
                            placeholder="kontakt@beispiel.de"
                            required
                        >

                        <button type="submit">Kontaktdaten speichern</button>
                    </form>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_password">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Passwort Verwaltung</h2>
                    <p>Hier kann das Login-Passwort für den geschützten Verwaltungsbereich geändert werden.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_password'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_password'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_password" class="settings-form">
                        <input type="hidden" name="auth_action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="password-settings-row">
                            <div class="password-settings-item">
                                <label for="current_password">Aktuelles Passwort</label>
                                <div class="password-field-wrap">
                                    <input id="current_password" type="password" name="current_password" autocomplete="current-password" required data-password-field>
                                    <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                                        <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                                    </button>
                                </div>
                            </div>

                            <div class="password-settings-item">
                                <label for="new_password">Neues Passwort</label>
                                <div class="password-field-wrap">
                                    <input id="new_password" type="password" name="new_password" minlength="10" autocomplete="new-password" required data-password-field>
                                    <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                                        <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                                    </button>
                                </div>
                            </div>

                            <div class="password-settings-item">
                                <label for="confirm_password">Neues Passwort wiederholen</label>
                                <div class="password-field-wrap">
                                    <input id="confirm_password" type="password" name="confirm_password" minlength="10" autocomplete="new-password" required data-password-field>
                                    <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                                        <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit">Passwort speichern</button>
                    </form>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_vehicle_types">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Fahrzeugtypen</h2>
                    <p>Fahrzeugtypen für Formulare und Fahrzeugverwaltung pflegen.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_vehicle_types'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_vehicle_types'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_vehicle_types" class="settings-form">
                        <input type="hidden" name="auth_action" value="add_vehicle_type">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="vehicle_type_name">Neuer Fahrzeugtyp</label>
                        <input id="vehicle_type_name" type="text" name="vehicle_type_name" placeholder="z. B. Coupé" required>

                        <button type="submit">Fahrzeugtyp hinzufügen</button>
                    </form>

                    <div class="table-wrap" style="margin-top:0.9rem;">
                        <table>
                            <thead>
                            <tr>
                                <th>Typ</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($vehicleTypeRows === []): ?>
                                <tr>
                                    <td colspan="3">Keine Fahrzeugtypen vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vehicleTypeRows as $vehicleTypeRow): ?>
                                    <?php
                                    $typeName = trim((string) ($vehicleTypeRow['type_name'] ?? ''));
                                    $isActive = (int) ($vehicleTypeRow['is_active'] ?? 1) === 1;
                                    ?>
                                    <tr>
                                        <td><?= e($typeName) ?></td>
                                        <td><?= $isActive ? 'Aktiv' : 'Inaktiv' ?></td>
                                        <td>
                                            <form method="post" action="verwaltung.php?view=settings_vehicle_types" onsubmit="return confirm('Fahrzeugtyp wirklich löschen?');">
                                                <input type="hidden" name="auth_action" value="delete_vehicle_type">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="vehicle_type_name" value="<?= e($typeName) ?>">
                                                <button type="submit" class="danger-button">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_cleaning_packages">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Reinigungspakete</h2>
                    <p>Reinigungspakete für Kontaktformular und Anfrage-/Terminverwaltung pflegen.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_cleaning_packages'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_cleaning_packages'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_cleaning_packages" class="settings-form">
                        <input type="hidden" name="auth_action" value="add_cleaning_package">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="cleaning_package_name">Neues Reinigungspaket</label>
                        <input id="cleaning_package_name" type="text" name="cleaning_package_name" placeholder="z. B. Premium Aufbereitung" required>

                        <button type="submit">Reinigungspaket hinzufügen</button>
                    </form>

                    <div class="table-wrap" style="margin-top:0.9rem;">
                        <table>
                            <thead>
                            <tr>
                                <th>Paket</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($cleaningPackageRows === []): ?>
                                <tr>
                                    <td colspan="3">Keine Reinigungspakete vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cleaningPackageRows as $cleaningPackageRow): ?>
                                    <?php
                                    $packageName = trim((string) ($cleaningPackageRow['package_name'] ?? ''));
                                    $isActive = (int) ($cleaningPackageRow['is_active'] ?? 1) === 1;
                                    ?>
                                    <tr>
                                        <td><?= e($packageName) ?></td>
                                        <td><?= $isActive ? 'Aktiv' : 'Inaktiv' ?></td>
                                        <td>
                                            <form method="post" action="verwaltung.php?view=settings_cleaning_packages" onsubmit="return confirm('Reinigungspaket wirklich löschen?');">
                                                <input type="hidden" name="auth_action" value="delete_cleaning_package">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="cleaning_package_name" value="<?= e($packageName) ?>">
                                                <button type="submit" class="danger-button">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_workload_reference">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Workload Referenz</h2>
                    <p>Die Pflege von Arbeits- und Kosten-Aufwänden erfolgt in der eigenständigen Aufwands-Liste mit Detailansicht.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_workload_reference'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_workload_reference'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <div class="panel-actions" style="margin-top:0.85rem;">
                        <a class="primary-link" href="verwaltung.php?view=workload">Aufwands-Liste öffnen</a>
                        <a class="primary-link" href="workload_reference_detail.php?create=1">+ Neu</a>
                    </div>
                </article>
            </div>
        </section>
    </main>
</div>

<script>
(() => {
    const toggleButtons = Array.from(document.querySelectorAll('[data-password-toggle-btn]'));

    toggleButtons.forEach((button) => {
        const wrapper = button.closest('.password-field-wrap');
        if (!wrapper) {
            return;
        }

        const input = wrapper.querySelector('[data-password-field]');
        if (!input) {
            return;
        }

        const icon = button.querySelector('[data-password-toggle-icon]');

        button.addEventListener('click', () => {
            const nextType = input.type === 'password' ? 'text' : 'password';
            input.type = nextType;

            if (icon) {
                icon.textContent = nextType === 'text' ? '\u{1F648}' : '\u{1F441}\uFE0F';
            }

            button.classList.toggle('is-active', nextType === 'text');
            button.setAttribute('aria-label', nextType === 'text' ? 'Passwort verbergen' : 'Passwort anzeigen');
        });
    });
})();

(() => {
    const initialView = <?= json_encode($initialView, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const adminShell = document.querySelector('.admin-shell');
    const menuItems = Array.from(document.querySelectorAll('.menu-item'));
    const panels = Array.from(document.querySelectorAll('[data-view-panel]'));
    const sidebar = document.querySelector('.sidebar');
    const settingsParent = document.querySelector('[data-settings-parent]');
    const settingsSubmenu = document.querySelector('[data-settings-submenu]');
    const menuToggleButton = document.querySelector('.menu-toggle');
    const contentHead = document.querySelector('.content-head');
    const toolbar = document.querySelector('.toolbar');
    const title = document.querySelector('[data-view-title]');
    const subtitle = document.querySelector('[data-view-subtitle]');
    const menuModeStorageKey = 'verwaltung-menu-mode';
    let menuMode = 'full';

    const readStoredMode = () => {
        try {
            const stored = window.localStorage.getItem(menuModeStorageKey);
            return stored === 'icons' ? 'icons' : 'full';
        } catch (_error) {
            return 'full';
        }
    };

    const writeStoredMode = (mode) => {
        try {
            window.localStorage.setItem(menuModeStorageKey, mode);
        } catch (_error) {
            // Ignore storage errors and keep menu functional.
        }
    };

    const meta = {
        dashboard: ['Übersicht', 'Alle aktuellen Kennzahlen und offenen Punkte auf einen Blick.'],
        requests: ['Anfragen', 'Eingehende Kontakt- und Terminanfragen bearbeiten.'],
        customers: ['Kunden', 'Kundenstamm pflegen, suchen und editieren.'],
        workload: ['Aufwände', 'Arbeits- und Kosten-Aufwände verwalten und bearbeiten.'],
        vehicles: ['Fahrzeuge', 'Alle Kundenfahrzeuge mit Details und Kennzeichen.'],
        appointments: ['Termine', 'Geplante Termine organisieren und Status nachhalten.'],
        calendar: ['Kalender', 'Jahres-, Monats- und Tagesansicht mit blockierbaren Zeiträumen.'],
        settings_social: ['Einstellungen: Social Media', 'Social-Media- und Kontaktinformationen verwalten.'],
        settings_password: ['Einstellungen: Passwort', 'Passwort für den Verwaltungsbereich ändern.'],
        settings_vehicle_types: ['Einstellungen: Fahrzeugtypen', 'Fahrzeugtypen für Kunden- und Fahrzeugformulare pflegen.'],
        settings_cleaning_packages: ['Einstellungen: Reinigungspakete', 'Reinigungspakete für Kontaktformular sowie Anfrage- und Terminformulare pflegen.'],
        settings_workload_reference: ['Einstellungen: Workload Referenz', 'Zeitaufwand und Nettopreis pro Reinigungspaket und Fahrzeugtyp pflegen.'],
    };

    function setSettingsDropdown(open) {
        if (!settingsSubmenu || !settingsParent) {
            return;
        }

        settingsSubmenu.hidden = !open;
        settingsParent.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function activate(view) {
        menuItems.forEach((item) => item.classList.toggle('is-active', item.dataset.view === view));
        panels.forEach((panel) => panel.classList.toggle('is-hidden', panel.dataset.viewPanel !== view));
        setSettingsDropdown(view === 'settings_social' || view === 'settings_password' || view === 'settings_vehicle_types' || view === 'settings_cleaning_packages' || view === 'settings_workload_reference' || view === 'workload');

        const hideHeaderInView = view === 'dashboard';
        if (contentHead) {
            contentHead.hidden = hideHeaderInView;
        }
        if (toolbar) {
            toolbar.hidden = hideHeaderInView;
        }

        if (meta[view]) {
            title.textContent = meta[view][0];
            subtitle.textContent = meta[view][1];
        }
    }

    function applyMenuMode(mode) {
        if (!sidebar) {
            return;
        }

        const iconsOnly = mode === 'icons';
        sidebar.classList.toggle('is-icons-only', iconsOnly);
        if (adminShell) {
            adminShell.classList.toggle('is-icons-only', iconsOnly);
        }
        if (menuToggleButton) {
            menuToggleButton.textContent = '\u2630 Men\u00fc umschalten';
        }
    }

    if (sidebar) {
        menuMode = readStoredMode();
        applyMenuMode(menuMode);
    }

    if (menuToggleButton && sidebar) {
        menuToggleButton.addEventListener('click', () => {
            const nextMode = sidebar.classList.contains('is-icons-only') ? 'full' : 'icons';
            menuMode = nextMode;
            applyMenuMode(nextMode);
            writeStoredMode(nextMode);
        });
    }

    menuItems.forEach((item) => {
        item.addEventListener('click', () => {
            if (item === settingsParent) {
                const isOpen = settingsSubmenu ? !settingsSubmenu.hidden : false;
                if (!isOpen) {
                    activate('settings_social');
                } else {
                    setSettingsDropdown(false);
                }
                return;
            }

            if (item.dataset.view === 'calendar') {
                window.location.href = 'calendar.php';
                return;
            }

            activate(item.dataset.view);
        });
    });

    if (initialView === 'calendar') {
        window.location.href = 'calendar.php';
        return;
    }

    activate(initialView);
})();
</script>
</body>
</html>
