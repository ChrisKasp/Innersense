<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/src/AdminCatalogService.php';
require_once dirname(__DIR__) . '/src/AdminActionService.php';
require_once dirname(__DIR__) . '/src/AdminDashboardDataService.php';
require_once dirname(__DIR__) . '/src/EmailTemplateService.php';
require_once __DIR__ . '/partials/admin_table_bodies.php';
loadEnv(dirname(__DIR__) . '/config/.env');

$adminPassword = trim((string) (getenv('ADMIN_PASSWORD') ?: ''));
if ($adminPassword === '') {
    // Keep login unavailable when no admin password is configured in env.
    $adminPassword = '__ADMIN_PASSWORD_NOT_SET__';
}
$authConfigFile = dirname(__DIR__) . '/config/verwaltung_auth.php';
$publicContactConfigFile = dirname(__DIR__) . '/config/public_contact.php';
$envFile = detectEnvFilePath(dirname(__DIR__));
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
$emailTemplateRows = [];
$emailTypeAssignments = [];
$emailTypeLabels = EmailTemplateService::getEmailTypeLabels();
$allowedEmailPlaceholders = EmailTemplateService::getAllowedPlaceholders();
$dashboardOpenRequests = 0;
$dashboardOpenRequestsSinceYesterday = 0;
$dashboardPlannedAppointments = 0;
$dashboardPlannedAppointmentsThisWeek = 0;
$dashboardCustomerCount = 0;
$dashboardNewCustomersThisMonth = 0;
$dashboardVehicleCount = 0;
$dashboardHomepageHits = 0;
$availableViews = ['dashboard', 'requests', 'customers', 'workload', 'vehicles', 'appointments', 'calendar', 'settings_social', 'settings_password', 'settings_vehicle_types', 'settings_cleaning_packages', 'settings_email_templates', 'settings_workload_reference'];
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
    'business_address' => (string) (getenv('BUSINESS_ADDRESS') ?: ''),
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
        $loginError = 'Seite wurde wegen Nichtbenutzung gesperrt!';
    }
}

if (!$isAuthenticated && $loginError === '' && (string) ($_GET['timed_out'] ?? '') === '1') {
    $loginError = 'Seite wurde wegen Nichtbenutzung gesperrt!';
}

if ((string) $_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginResult = handleAdminLoginAction(
        (string) $_SERVER['REQUEST_METHOD'],
        $_POST,
        $storedPasswordHash,
        $isAuthenticated,
        $loginError
    );

    $isAuthenticated = $loginResult['isAuthenticated'];
    $loginError = $loginResult['loginError'];

    if ($loginResult['shouldRegenerateSession']) {
        session_regenerate_id(true);
        $_SESSION['verwaltung_authenticated'] = true;
        $_SESSION['verwaltung_last_activity'] = time();
    }

    if ($loginResult['shouldRedirect']) {
        header('Location: verwaltung.php');
        exit;
    }
}

if ($isAuthenticated) {
    $_SESSION['verwaltung_last_activity'] = time();

    require_once dirname(__DIR__) . '/config/database.php';
    ensureVehicleTypeTable(db());
    ensureCleaningPackageTable(db());
    ensureWorkloadReferenceTable(db());
    EmailTemplateService::ensureTables(db());

    $actionResult = handleAuthenticatedAdminAction(
        db(),
        (string) $_SERVER['REQUEST_METHOD'],
        $_POST,
        $csrfToken,
        $initialView,
        $settingsError,
        $settingsNotice,
        $storedPasswordHash,
        $socialMediaSettings,
        $authConfigFile,
        $publicContactConfigFile,
        $envFile
    );

    $initialView = $actionResult['initialView'];
    $settingsError = $actionResult['settingsError'];
    $settingsNotice = $actionResult['settingsNotice'];
    $storedPasswordHash = $actionResult['storedPasswordHash'];
    $socialMediaSettings = $actionResult['socialMediaSettings'];

    $customerResult = loadAdminCustomerRows(db());
    $customerRows = $customerResult['rows'];
    $customerLoadError = $customerResult['error'];

    $requestResult = loadAdminRequestRows(db());
    $requestRows = $requestResult['rows'];
    $requestLoadError = $requestResult['error'];

    $vehicleResult = loadAdminVehicleRows(db());
    $vehicleRows = $vehicleResult['rows'];
    $vehicleLoadError = $vehicleResult['error'];

    $appointmentResult = loadAdminAppointmentRows(db());
    $appointmentRows = $appointmentResult['rows'];
    $appointmentLoadError = $appointmentResult['error'];

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

    $dashboardHomepageHits = getAdminPageHitCount(db(), 'index');
    $vehicleTypeRows = getVehicleTypes(db());
    $cleaningPackageRows = getCleaningPackages(db());
    $workloadReferenceRows = getWorkloadReferences(db());
    $emailTemplateRows = EmailTemplateService::getTemplates(db());
    $emailTypeAssignments = EmailTemplateService::getTypeAssignments(db());
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
        'business_address' => (string) ($defaults['business_address'] ?? ''),
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
        'business_address' => (string) ($settings['business_address'] ?? ''),
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

function detectEnvFilePath(string $projectRoot): string
{
    $candidates = [
        rtrim($projectRoot, '/\\') . '/config/.env',
        rtrim($projectRoot, '/\\') . '/.env',
        rtrim(dirname($projectRoot), '/\\') . '/.env',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return $candidates[0];
}

/**
 * @param array<string, string> $values
 */
function upsertEnvValues(string $envFile, array $values): bool
{
    $lines = [];
    if (is_file($envFile)) {
        if (!is_readable($envFile)) {
            return false;
        }

        $loaded = file($envFile, FILE_IGNORE_NEW_LINES);
        if (!is_array($loaded)) {
            return false;
        }

        $lines = $loaded;
    }

    $remaining = $values;
    foreach ($lines as $index => $line) {
        if (!is_string($line)) {
            continue;
        }

        if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $matches) !== 1) {
            continue;
        }

        $key = (string) ($matches[1] ?? '');
        if (!array_key_exists($key, $remaining)) {
            continue;
        }

        $lines[$index] = $key . '=' . encodeEnvValue((string) $remaining[$key]);
        unset($remaining[$key]);
    }

    foreach ($remaining as $key => $value) {
        $lines[] = $key . '=' . encodeEnvValue((string) $value);
    }

    $content = implode("\n", $lines);
    if ($content !== '') {
        $content .= "\n";
    }

    $tmpFile = $envFile . '.tmp';
    $bytes = @file_put_contents($tmpFile, $content, LOCK_EX);
    if ($bytes === false) {
        return false;
    }

    if (!@rename($tmpFile, $envFile)) {
        @unlink($tmpFile);
        return false;
    }

    return true;
}

function encodeEnvValue(string $value): string
{
    if ($value === '') {
        return '""';
    }

    if (preg_match('/^[A-Za-z0-9_:\/.+\-@]+$/', $value) === 1) {
        return $value;
    }

    $escaped = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', '\\n'], $value);
    return '"' . $escaped . '"';
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

$ajaxAction = (string) ($_GET['ajax'] ?? '');
if ($isAuthenticated && $ajaxAction === 'list_search') {
    $panel = trim((string) ($_GET['panel'] ?? ''));
    $query = trim((string) ($_GET['q'] ?? ''));

    header('Content-Type: text/html; charset=UTF-8');

    if ($panel === 'requests') {
        renderRequestsTableBody($requestRows, $requestLoadError, $query, $csrfToken);
        exit;
    }

    if ($panel === 'customers') {
        renderCustomersTableBody($customerRows, $customerLoadError, $query);
        exit;
    }

    if ($panel === 'vehicles') {
        renderVehiclesTableBody($vehicleRows, $vehicleLoadError, $query);
        exit;
    }

    if ($panel === 'workload') {
        renderWorkloadTableBody($workloadReferenceRows, $query);
        exit;
    }

    if ($panel === 'appointments') {
        renderAppointmentsTableBody($appointmentRows, $appointmentLoadError, $query);
        exit;
    }

    http_response_code(400);
    echo e('Ungültige Listenansicht.');
    exit;
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
                <button class="menu-item is-subitem" type="button" data-view="settings_email_templates"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>E-Mail-Vorlagen</span></button>
                <button class="menu-item is-subitem" type="button" data-view="workload"><span class="menu-icon" aria-hidden="true">&#9679;</span><span>Aufwände</span></button>
            </div>
            <button class="menu-item" type="button" data-view="logout"><span class="menu-icon" aria-hidden="true">&#10162;</span><span>Abmelden</span></button>
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

        <?php require __DIR__ . '/partials/admin_main_panels.php'; ?>

        <?php require __DIR__ . '/partials/admin_settings_panels.php'; ?>
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
        settings_email_templates: ['Einstellungen: E-Mail-Vorlagen', 'Betreff/Inhalt pflegen und E-Mail-Typen den Vorlagen zuordnen.'],
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
        setSettingsDropdown(view === 'settings_social' || view === 'settings_password' || view === 'settings_vehicle_types' || view === 'settings_cleaning_packages' || view === 'settings_email_templates' || view === 'settings_workload_reference' || view === 'workload');

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

            if (item.dataset.view === 'logout') {
                window.location.href = 'verwaltung.php?logout=1';
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

(() => {
    const searchInputs = Array.from(document.querySelectorAll('[data-list-search-input]'));
    if (searchInputs.length === 0) {
        return;
    }

    const debounceMs = 280;
    const debounceTimers = new Map();
    const activeControllers = new Map();

    const rebindDynamicHandlers = (_panel) => {
        // Keep this hook for interactive elements inside dynamically reloaded table bodies.
    };

    const updateListBody = async (input) => {
        const panel = (input.dataset.listSearchInput || '').trim();
        const targetId = (input.dataset.listSearchTarget || '').trim();
        const endpoint = (input.dataset.listSearchEndpoint || '').trim();
        const query = (input.value || '').trim();
        const target = document.getElementById(targetId);

        if (!panel || !target || !endpoint) {
            return;
        }

        const previousController = activeControllers.get(panel);
        if (previousController) {
            previousController.abort();
        }

        const controller = new AbortController();
        activeControllers.set(panel, controller);

        input.setAttribute('aria-busy', 'true');

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('q', query);
            url.searchParams.set('view', panel);

            const response = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`List search failed for ${panel}`);
            }

            const html = await response.text();
            target.innerHTML = html;
            rebindDynamicHandlers(panel);
        } catch (error) {
            if (controller.signal.aborted) {
                return;
            }

            console.error(error);
        } finally {
            if (activeControllers.get(panel) === controller) {
                activeControllers.delete(panel);
            }
            input.removeAttribute('aria-busy');
        }
    };

    searchInputs.forEach((input) => {
        input.addEventListener('input', () => {
            const panel = (input.dataset.listSearchInput || '').trim();
            if (panel === '') {
                return;
            }

            const previousTimer = debounceTimers.get(panel);
            if (typeof previousTimer === 'number') {
                window.clearTimeout(previousTimer);
            }

            const timerId = window.setTimeout(() => {
                updateListBody(input);
            }, debounceMs);
            debounceTimers.set(panel, timerId);
        });
    });
})();
</script>
</body>
</html>
