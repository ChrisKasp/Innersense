<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/database.php';

$idleTimeoutSeconds = 600;
$csrfToken = (string) ($_SESSION['verwaltung_csrf'] ?? '');
$notice = '';
$error = '';

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

$pdo = db();
ensureBlockedSlotTable($pdo);
$requestTable = detectFirstExistingTable($pdo, ['customer_requests', 'customer_request']);

$view = (string) ($_GET['view'] ?? 'month');
if (!in_array($view, ['year', 'month', 'day'], true)) {
    $view = 'month';
}

$selectedDateRaw = (string) ($_GET['date'] ?? date('Y-m-d'));
$selectedDate = parseDateOrToday($selectedDateRaw);
$selectedDateStr = $selectedDate->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') !== '') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    $slotDate = (string) ($_POST['slot_date'] ?? '');
    $slotTime = (string) ($_POST['slot_time'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $error = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif (!isValidDate($slotDate) || !isHalfHourSlot($slotTime)) {
        $error = 'Datum oder Zeitfenster ist ungültig.';
    } else {
        try {
            if ($action === 'block_slot') {
                $stmt = $pdo->prepare(
                    'INSERT INTO schedule_blocked_slot (slot_date, slot_time)
                     VALUES (:slot_date, :slot_time)
                     ON DUPLICATE KEY UPDATE slot_date = VALUES(slot_date)'
                );
                $stmt->execute([
                    ':slot_date' => $slotDate,
                    ':slot_time' => $slotTime . ':00',
                ]);
                $notice = 'Zeitfenster wurde blockiert.';
            } elseif ($action === 'unblock_slot') {
                $stmt = $pdo->prepare(
                    'DELETE FROM schedule_blocked_slot
                     WHERE slot_date = :slot_date AND slot_time = :slot_time'
                );
                $stmt->execute([
                    ':slot_date' => $slotDate,
                    ':slot_time' => $slotTime . ':00',
                ]);
                $notice = 'Zeitfenster wurde freigegeben.';
            }

            $selectedDate = parseDateOrToday($slotDate);
            $selectedDateStr = $selectedDate->format('Y-m-d');
            $view = 'day';
        } catch (Throwable $exception) {
            $error = 'Zeitfenster konnte nicht gespeichert werden.';
        }
    }
}

$year = (int) $selectedDate->format('Y');
$month = (int) $selectedDate->format('m');

$yearBlockedCountsRaw = getBlockedCountsForYear($pdo, $year);
$monthBlockedCountsRaw = getBlockedCountsForMonth($pdo, $year, $month);
$blockedDaySlotsRaw = getBlockedSlotsForDate($pdo, $selectedDateStr);

$yearBookedCounts = [];
$monthBookedCounts = [];
$bookedDaySlots = [];

if ($requestTable !== null) {
    $yearBookedCounts = getBookedCountsForYear($pdo, $requestTable, $year);
    $monthBookedCounts = getBookedCountsForMonth($pdo, $requestTable, $year, $month);
    $bookedDaySlots = getBookedSlotsForDate($pdo, $requestTable, $selectedDateStr);
}

$yearBlockedCounts = subtractCountMap($yearBlockedCountsRaw, $yearBookedCounts);
$monthBlockedCounts = subtractCountMap($monthBlockedCountsRaw, $monthBookedCounts);
$blockedDaySlots = array_diff_key($blockedDaySlotsRaw, $bookedDaySlots);
$dailySlots = buildHalfHourSlots('09:00', '20:00');
$bookedDayCount = count($bookedDaySlots);
$monthNamesDe = [
    1 => 'Januar',
    2 => 'Februar',
    3 => 'März',
    4 => 'April',
    5 => 'Mai',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'August',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Dezember',
];

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function parseDateOrToday(string $value): DateTimeImmutable
{
    if (isValidDate($value)) {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }
    }

    return new DateTimeImmutable('today');
}

function isValidDate(string $value): bool
{
    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function isHalfHourSlot(string $time): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return false;
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));
    if ($hour < 0 || $hour > 23) {
        return false;
    }

    return in_array($minute, [0, 30], true);
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

/**
 * @param array<int|string, int> $base
 * @param array<int|string, int> $subtract
 * @return array<int|string, int>
 */
function subtractCountMap(array $base, array $subtract): array
{
    $result = $base;
    foreach ($result as $key => $value) {
        $nextValue = (int) $value - (int) ($subtract[$key] ?? 0);
        $result[$key] = max(0, $nextValue);
    }

    return $result;
}

/**
 * @return array<int, int>
 */
function getBookedCountsForYear(PDO $pdo, string $requestTable, int $year): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(cr.preferred_date, '%Y-%m-%d') AS preferred_date,
                cr.preferred_time,
                wr.time_effort
         FROM {$requestTable} cr
         LEFT JOIN customer_vehicle cv
            ON cv.id = cr.customer_vehicle_id
         LEFT JOIN workload_reference wr
            ON wr.cleaning_package = cr.cleaning_package
           AND wr.vehicle_type = cv.vehicle_type
         WHERE cr.preferred_date IS NOT NULL
           AND YEAR(cr.preferred_date) = :year
           AND TRIM(COALESCE(cr.preferred_time, '')) <> ''"
    );
    $stmt->execute([':year' => $year]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $preferredDate = (string) ($row['preferred_date'] ?? '');
        $preferredTime = (string) ($row['preferred_time'] ?? '');
        $timeEffort = $row['time_effort'] ?? null;

        foreach (expandRequestedSlots($preferredDate, $preferredTime, $timeEffort) as $slotDateTime) {
            if ((int) $slotDateTime->format('Y') !== $year) {
                continue;
            }

            $monthNum = (int) $slotDateTime->format('n');
            if ($monthNum >= 1 && $monthNum <= 12) {
                $counts[$monthNum] = (int) ($counts[$monthNum] ?? 0) + 1;
            }
        }
    }

    return $counts;
}

/**
 * @return array<string, int>
 */
function getBookedCountsForMonth(PDO $pdo, string $requestTable, int $year, int $month): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(cr.preferred_date, '%Y-%m-%d') AS preferred_date,
                cr.preferred_time,
                wr.time_effort
         FROM {$requestTable} cr
         LEFT JOIN customer_vehicle cv
            ON cv.id = cr.customer_vehicle_id
         LEFT JOIN workload_reference wr
            ON wr.cleaning_package = cr.cleaning_package
           AND wr.vehicle_type = cv.vehicle_type
         WHERE cr.preferred_date IS NOT NULL
           AND YEAR(cr.preferred_date) = :year
           AND MONTH(cr.preferred_date) = :month
           AND TRIM(COALESCE(cr.preferred_time, '')) <> ''"
    );
    $stmt->execute([
        ':year' => $year,
        ':month' => $month,
    ]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $preferredDate = (string) ($row['preferred_date'] ?? '');
        $preferredTime = (string) ($row['preferred_time'] ?? '');
        $timeEffort = $row['time_effort'] ?? null;

        foreach (expandRequestedSlots($preferredDate, $preferredTime, $timeEffort) as $slotDateTime) {
            if ((int) $slotDateTime->format('Y') !== $year || (int) $slotDateTime->format('n') !== $month) {
                continue;
            }

            $slotKey = $slotDateTime->format('Y-m-d');
            $counts[$slotKey] = (int) ($counts[$slotKey] ?? 0) + 1;
        }
    }

    return $counts;
}

/**
 * @return array<string, string>
 */
function getBookedSlotsForDate(PDO $pdo, string $requestTable, string $date): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(cr.preferred_date, '%Y-%m-%d') AS preferred_date,
                cr.preferred_time,
                     wr.time_effort,
                     c.first_name,
                     c.last_name
         FROM {$requestTable} cr
            LEFT JOIN customer c
                ON c.id = cr.customer_id
         LEFT JOIN customer_vehicle cv
            ON cv.id = cr.customer_vehicle_id
         LEFT JOIN workload_reference wr
            ON wr.cleaning_package = cr.cleaning_package
           AND wr.vehicle_type = cv.vehicle_type
         WHERE cr.preferred_date = :preferred_date
           AND TRIM(COALESCE(cr.preferred_time, '')) <> ''"
    );
    $stmt->execute([':preferred_date' => $date]);

    $slots = [];
    foreach ($stmt->fetchAll() as $row) {
        $preferredDate = (string) ($row['preferred_date'] ?? '');
        $preferredTime = (string) ($row['preferred_time'] ?? '');
        $timeEffort = $row['time_effort'] ?? null;
        $customerName = trim(
            trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? ''))
        );
        if ($customerName === '') {
            $customerName = 'Unbekannt';
        }

        foreach (expandRequestedSlots($preferredDate, $preferredTime, $timeEffort) as $slotDateTime) {
            if ($slotDateTime->format('Y-m-d') !== $date) {
                continue;
            }

            $slotKey = $slotDateTime->format('H:i');
            if (!isset($slots[$slotKey])) {
                $slots[$slotKey] = $customerName;
                continue;
            }

            $existingNames = array_map('trim', explode(', ', $slots[$slotKey]));
            if (!in_array($customerName, $existingNames, true)) {
                $slots[$slotKey] .= ', ' . $customerName;
            }
        }
    }

    return $slots;
}

/**
 * @return list<DateTimeImmutable>
 */
function expandRequestedSlots(string $preferredDate, string $preferredTime, $timeEffort): array
{
    $normalizedTime = normalizeHalfHourSlot($preferredTime);
    if (!isValidDate($preferredDate) || $normalizedTime === null) {
        return [];
    }

    $startDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $preferredDate . ' ' . $normalizedTime);
    if (!$startDateTime instanceof DateTimeImmutable) {
        return [];
    }

    $slotCount = resolveSlotCountFromTimeEffort($timeEffort);
    $slots = [];
    for ($index = 0; $index < $slotCount; $index++) {
        $slots[] = $startDateTime->modify('+' . ($index * 30) . ' minutes');
    }

    return $slots;
}

function resolveSlotCountFromTimeEffort($timeEffort): int
{
    if (!is_numeric($timeEffort)) {
        return 1;
    }

    $hours = (float) $timeEffort;
    if ($hours <= 0.0) {
        return 1;
    }

    return max(1, (int) ceil($hours * 2.0));
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
    return isHalfHourSlot($normalized) ? $normalized : null;
}

/**
 * @return array<int, int>
 */
function getBlockedCountsForYear(PDO $pdo, int $year): array
{
    $stmt = $pdo->prepare(
        'SELECT MONTH(slot_date) AS month_num, COUNT(*) AS total
         FROM schedule_blocked_slot
         WHERE YEAR(slot_date) = :year
         GROUP BY MONTH(slot_date)'
    );
    $stmt->execute([':year' => $year]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $monthNum = (int) ($row['month_num'] ?? 0);
        $total = (int) ($row['total'] ?? 0);
        if ($monthNum >= 1 && $monthNum <= 12) {
            $counts[$monthNum] = $total;
        }
    }

    return $counts;
}

/**
 * @return array<string, int>
 */
function getBlockedCountsForMonth(PDO $pdo, int $year, int $month): array
{
    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(slot_date, "%Y-%m-%d") AS slot_key, COUNT(*) AS total
         FROM schedule_blocked_slot
         WHERE YEAR(slot_date) = :year AND MONTH(slot_date) = :month
         GROUP BY slot_date'
    );
    $stmt->execute([
        ':year' => $year,
        ':month' => $month,
    ]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (string) ($row['slot_key'] ?? '');
        if ($key !== '') {
            $counts[$key] = (int) ($row['total'] ?? 0);
        }
    }

    return $counts;
}

/**
 * @return array<string, bool>
 */
function getBlockedSlotsForDate(PDO $pdo, string $date): array
{
    $stmt = $pdo->prepare(
        'SELECT DATE_FORMAT(slot_time, "%H:%i") AS slot_key
         FROM schedule_blocked_slot
         WHERE slot_date = :slot_date'
    );
    $stmt->execute([':slot_date' => $date]);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $slot = (string) ($row['slot_key'] ?? '');
        if ($slot !== '') {
            $map[$slot] = true;
        }
    }

    return $map;
}

$monthStart = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month)) ?: new DateTimeImmutable('first day of this month');
$gridStartOffset = ((int) $monthStart->format('N')) - 1;
$gridStart = $monthStart->modify('-' . $gridStartOffset . ' days');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Kalender</title>
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
            <a class="menu-item" href="verwaltung.php?view=dashboard"><span class="menu-icon" aria-hidden="true">&#9638;</span><span>Übersicht</span></a>
            <a class="menu-item" href="verwaltung.php?view=requests"><span class="menu-icon" aria-hidden="true">&#9993;</span><span>Anfragen</span></a>
            <a class="menu-item" href="verwaltung.php?view=customers"><span class="menu-icon" aria-hidden="true">&#128101;</span><span>Kunden</span></a>
            <a class="menu-item" href="verwaltung.php?view=vehicles"><span class="menu-icon" aria-hidden="true">&#128663;</span><span>Fahrzeuge</span></a>
            <a class="menu-item" href="verwaltung.php?view=appointments"><span class="menu-icon" aria-hidden="true">&#128340;</span><span>Termine</span></a>
            <a class="menu-item is-active" href="calendar.php"><span class="menu-icon" aria-hidden="true">&#128197;</span><span>Kalender</span></a>
            <a class="menu-item" href="verwaltung.php?view=settings"><span class="menu-icon" aria-hidden="true">&#9881;</span><span>Einstellungen</span></a>
        </nav>

        <div class="sidebar-footer">
            <a class="logout" href="index.php">Zurück zur Hauptseite</a>
        </div>
    </aside>

    <main class="content-area">
        <section class="detail-header">
            <a class="action-icon" href="verwaltung.php?view=appointments" aria-label="Zurück zur Verwaltung">&#8592;</a>
            <div>
                <h1>Kalender</h1>
                <p>Jahres-, Monats- und Tagesansicht. In der Tagesansicht können halbstündige Zeitfenster blockiert werden.</p>
            </div>
        </section>

        <?php if ($notice !== ''): ?>
            <p class="form-notice"><?= e($notice) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="form-error"><?= e($error) ?></p>
        <?php endif; ?>

        <section class="calendar-nav">
            <a class="calendar-tab<?= $view === 'year' ? ' is-active' : '' ?>" href="calendar.php?view=year&amp;date=<?= e($selectedDateStr) ?>">Jahr</a>
            <a class="calendar-tab<?= $view === 'month' ? ' is-active' : '' ?>" href="calendar.php?view=month&amp;date=<?= e($selectedDateStr) ?>">Monat</a>
            <a class="calendar-tab<?= $view === 'day' ? ' is-active' : '' ?>" href="calendar.php?view=day&amp;date=<?= e($selectedDateStr) ?>">Tag</a>
        </section>

        <main class="detail-page calendar-page">
            <?php if ($view === 'year'): ?>
                <section class="calendar-year-grid">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <?php $monthDate = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $m)); ?>
                        <?php $blockedCount = (int) ($yearBlockedCounts[$m] ?? 0); ?>
                        <?php $bookedCount = (int) ($yearBookedCounts[$m] ?? 0); ?>
                        <a class="calendar-month-card" href="calendar.php?view=month&amp;date=<?= e(($monthDate instanceof DateTimeImmutable) ? $monthDate->format('Y-m-d') : $selectedDateStr) ?>">
                            <strong><?= e($monthNamesDe[$m] ?? (string) $m) ?></strong>
                            <span class="calendar-meta"><?= e((string) $bookedCount) ?> belegt</span>
                            <span class="calendar-meta"><?= e((string) $blockedCount) ?> blockiert</span>
                        </a>
                    <?php endfor; ?>
                </section>
            <?php elseif ($view === 'month'): ?>
                <p class="calendar-mobile-hint">Auf Mobilgeräten seitlich wischen, um die komplette Woche zu sehen.</p>
                <div class="calendar-month-scroll">
                    <section class="calendar-month-grid">
                        <?php foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $weekday): ?>
                            <div class="calendar-weekday"><?= e($weekday) ?></div>
                        <?php endforeach; ?>

                        <?php for ($i = 0; $i < 42; $i++): ?>
                            <?php
                            $cellDate = $gridStart->modify('+' . $i . ' days');
                            $cellKey = $cellDate->format('Y-m-d');
                            $isCurrentMonth = $cellDate->format('m') === $monthStart->format('m');
                            $blockedCount = (int) ($monthBlockedCounts[$cellKey] ?? 0);
                            $bookedCount = (int) ($monthBookedCounts[$cellKey] ?? 0);
                            ?>
                            <a class="calendar-day-cell<?= $isCurrentMonth ? '' : ' is-muted' ?>" href="calendar.php?view=day&amp;date=<?= e($cellKey) ?>">
                                <strong><?= e($cellDate->format('d')) ?></strong>
                                <span class="calendar-meta"><?= e((string) $bookedCount) ?> belegt</span>
                                <span class="calendar-meta"><?= e((string) $blockedCount) ?> blockiert</span>
                            </a>
                        <?php endfor; ?>
                    </section>
                </div>
            <?php else: ?>
                <section class="calendar-day-panel">
                    <h2><?= e($selectedDate->format('d.m.Y')) ?></h2>
                    <p class="calendar-day-summary"><?= e((string) $bookedDayCount) ?> belegte Termine</p>
                    <div class="calendar-slot-list">
                        <?php foreach ($dailySlots as $slot): ?>
                            <?php
                            $isBooked = isset($bookedDaySlots[$slot]);
                            $isBlocked = !$isBooked && isset($blockedDaySlots[$slot]);
                            $bookedLabel = $isBooked
                                ? ('Belegt durch ' . ((string) ($bookedDaySlots[$slot] ?? 'Unbekannt')))
                                : ($isBlocked ? 'Blockiert' : 'Frei');
                            $action = $isBooked ? '' : ($isBlocked ? 'unblock_slot' : 'block_slot');
                            ?>
                            <form method="post" action="calendar.php?view=day&amp;date=<?= e($selectedDateStr) ?>" class="calendar-slot-row">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="slot_date" value="<?= e($selectedDateStr) ?>">
                                <input type="hidden" name="slot_time" value="<?= e($slot) ?>">
                                <input type="hidden" name="action" value="<?= e($action) ?>">

                                <span class="slot-time"><?= e($slot) ?></span>
                                <span class="slot-state <?= $isBooked ? 'is-booked' : ($isBlocked ? 'is-blocked' : 'is-open') ?>">
                                    <?= e($bookedLabel) ?>
                                </span>
                                <button
                                    type="<?= $isBooked ? 'button' : 'submit' ?>"
                                    class="slot-action <?= $isBooked ? 'is-booked' : ($isBlocked ? 'is-unblock' : 'is-block') ?>"
                                    <?= $isBooked ? 'disabled' : '' ?>
                                >
                                    <?= $isBooked ? 'Termin' : ($isBlocked ? 'Freigeben' : 'Blockieren') ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </main>
</div>

<script>
(() => {
    const adminShell = document.querySelector('.admin-shell');
    const sidebar = document.querySelector('.sidebar');
    const menuToggleButton = document.querySelector('.menu-toggle');
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

    if (!sidebar) {
        return;
    }

    const applyMenuMode = (mode) => {
        const iconsOnly = mode === 'icons';
        sidebar.classList.toggle('is-icons-only', iconsOnly);
        if (adminShell) {
            adminShell.classList.toggle('is-icons-only', iconsOnly);
        }
        if (menuToggleButton) {
            menuToggleButton.textContent = '\u2630 Men\u00fc umschalten';
        }
    };

    menuMode = readStoredMode();
    applyMenuMode(menuMode);

    if (menuToggleButton) {
        menuToggleButton.addEventListener('click', () => {
            const nextMode = sidebar.classList.contains('is-icons-only') ? 'full' : 'icons';
            menuMode = nextMode;
            applyMenuMode(nextMode);
            writeStoredMode(nextMode);
        });
    }
})();
</script>
</body>
</html>
