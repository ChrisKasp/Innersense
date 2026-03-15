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
ensurePageHitCounterTable($pdo);

if (!isset($_GET['ajax'])) {
    incrementPageHitCounter($pdo, 'index');
}

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
if ($contactStatus === 'success') {
    $contactMessage = 'Danke. Deine Anfrage wurde erfolgreich versendet.';
}
if ($contactStatus === 'error') {
    $contactMessage = 'Die Anfrage konnte nicht versendet werden. Bitte versuche es erneut.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['form_id'] ?? '') === 'contact_wizard') {
    $to = $contactToEmail;
    $from = getenv('CONTACT_FROM_EMAIL') ?: 'no-reply@innersense.sellerie.net';

    $products = array_values(array_filter(array_map('trim', (array) ($_POST['products'] ?? []))));
    $needs = trim((string) ($_POST['needs'] ?? ''));
    $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
    $preferredTime = trim((string) ($_POST['preferred_time'] ?? ''));
    $helpfulLinks = trim((string) ($_POST['helpful_links'] ?? ''));
    $budget = trim((string) ($_POST['budget'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $consent = isset($_POST['consent']) && $_POST['consent'] === '1';
    $humanCheck = isset($_POST['human_check']) && $_POST['human_check'] === '1';

    $preferredTimeOptions = [];
    if ($preferredDate !== '' && isValidDateOrEmpty($preferredDate)) {
        $preferredTimeOptions = getAvailableHalfHourSlots($pdo, $preferredDate);
    }

    $preferredDateTimeCombinationValid = true;
    if ($preferredTime !== '' && $preferredDate === '') {
        $preferredDateTimeCombinationValid = false;
    }
    if ($preferredTime !== '' && $preferredDate !== '') {
        $preferredDateTimeCombinationValid = in_array($preferredTime, $preferredTimeOptions, true);
    }

    $valid = $name !== ''
        && filter_var($email, FILTER_VALIDATE_EMAIL)
        && isValidDateOrEmpty($preferredDate)
        && ($preferredTime === '' || isValidHalfHourSlot($preferredTime))
        && $preferredDateTimeCombinationValid
        && $consent
        && $humanCheck;

    if ($valid) {
        $subject = 'Neue Anfrage Innersense Kontaktformular';
        $body = "Neue Anfrage ueber das Kontaktformular\n\n"
            . 'Produkte/Dienstleistungen: ' . ($products === [] ? '-' : implode(', ', $products)) . "\n"
            . 'Was brauchst du?: ' . ($needs !== '' ? $needs : '-') . "\n"
            . 'Wunschdatum: ' . ($preferredDate !== '' ? $preferredDate : '-') . "\n"
            . 'Wunschzeit: ' . ($preferredTime !== '' ? $preferredTime : '-') . "\n"
            . 'Hilfreiche Links: ' . ($helpfulLinks !== '' ? $helpfulLinks : '-') . "\n"
            . 'Verfuegbares Budget: ' . ($budget !== '' ? $budget : '-') . "\n"
            . 'Name: ' . $name . "\n"
            . 'E-Mail: ' . $email . "\n"
            . 'Telefon: ' . ($phone !== '' ? $phone : '-') . "\n"
            . "\n"
            . 'Datenschutz-Einwilligung: Ja' . "\n"
            . 'Mensch-Check: Ja' . "\n";

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: Innersense Website <' . $from . '>',
            'Reply-To: ' . $email,
        ];

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        header('Location: index.php?contact=' . ($sent ? 'success' : 'error') . '#kontakt');
        exit;
    }

    header('Location: index.php?contact=error#kontakt');
    exit;
}

function isValidDateOrEmpty(string $value): bool
{
    if ($value === '') {
        return true;
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

function ensurePageHitCounterTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS site_page_hit (
            page_key VARCHAR(64) NOT NULL,
            hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (page_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function incrementPageHitCounter(PDO $pdo, string $pageKey): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO site_page_hit (page_key, hit_count)
         VALUES (:page_key, 1)
         ON DUPLICATE KEY UPDATE hit_count = hit_count + 1'
    );
    $stmt->execute([':page_key' => $pageKey]);
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

$showcaseDirCandidates = [
    __DIR__ . '/assets/Bilder',
    __DIR__ . '/assets/bilder',
];

$showcaseBaseDir = null;
foreach ($showcaseDirCandidates as $candidate) {
    if (is_dir($candidate)) {
        $showcaseBaseDir = $candidate;
        break;
    }
}

$showcaseImages = [];
if ($showcaseBaseDir !== null) {
    $patterns = ['*.avif', '*.webp', '*.jpg', '*.jpeg', '*.png', '*.gif'];
    foreach ($patterns as $pattern) {
        $matches = glob($showcaseBaseDir . '/' . $pattern);
        if ($matches !== false) {
            $showcaseImages = array_merge($showcaseImages, $matches);
        }
    }

    // Keep only showcase files that use the expected prefix format (e.g. 1_... or #_...).
    $showcaseImages = array_values(
        array_filter(
            $showcaseImages,
            static function (string $path): bool {
                $name = pathinfo(basename($path), PATHINFO_FILENAME);
                return preg_match('/^(?:#_|\d+_)/', $name) === 1;
            }
        )
    );

    usort(
        $showcaseImages,
        static function (string $a, string $b): int {
            $nameA = pathinfo(basename($a), PATHINFO_FILENAME);
            $nameB = pathinfo(basename($b), PATHINFO_FILENAME);

            preg_match('/\d+/', $nameA, $matchA);
            preg_match('/\d+/', $nameB, $matchB);

            $orderA = isset($matchA[0]) ? (int) $matchA[0] : PHP_INT_MAX;
            $orderB = isset($matchB[0]) ? (int) $matchB[0] : PHP_INT_MAX;

            if ($orderA !== $orderB) {
                return $orderA <=> $orderB;
            }

            return strnatcasecmp($nameA, $nameB);
        }
    );
}

$showcaseWebPath = $showcaseBaseDir !== null ? 'assets/' . basename($showcaseBaseDir) : 'assets/Bilder';

$showcaseItems = array_map(
    static function (string $path) use ($showcaseWebPath): array {
        $filename = basename($path);
        $caption = pathinfo($filename, PATHINFO_FILENAME);
        $caption = preg_replace('/^(?:#_|\d+_)+/', '', $caption) ?? $caption;
        return [
            'src' => $showcaseWebPath . '/' . rawurlencode($filename),
            'caption' => $caption,
        ];
    },
    $showcaseImages
);
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Mobile Fahrzeugaufbereitung</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<?php
$activePage = 'home';
$hideVerwaltungInTopnav = true;
require __DIR__ . '/partials/site_header.php';
?>

<main id="home" class="home-split">
    <section class="panel panel-left">
        <div class="copy-wrap">
            <h1>Herzlich Willkommen</h1>
            <p>
                Als mobiler Fahrzeugaufbereiter bringen wir professionelle Pflege direkt zu dir,
                ob zuhause oder am Arbeitsplatz. Mit Fokus auf Sauberkeit, Präzision und Effizienz
                sorgen wir für ein rundum gepflegtes Fahrzeug, ohne dass du einen Finger rühren musst.
            </p>
            <p>
                Innersense steht für zuverlässige, flexible und hochwertige Fahrzeugaufbereitung,
                genau da, wo du sie brauchst.
            </p>
        </div>

        <a class="scroll-hint" href="#warum" aria-label="Nach unten scrollen">↓</a>
    </section>

    <section class="panel panel-right">
        <div class="hero-visual">
            <img src="assets/icons/Innersense_Logo.avif" alt="Innersense Mobile Fahrzeugaufbereitung">
            <a class="admin-hotspot" href="verwaltung.php" aria-label="Verwaltung oeffnen"></a>
        </div>
    </section>
</main>

<section id="warum" class="section-light">
    <div class="container text-block">
        <h2>Warum Mobil?</h2>
        <p>
            Jeder verdient ein sauberes, gepflegtes Auto. Doch im hektischen Alltag fehlt oft die Zeit,
            sich selbst darum zu kümmern. Genau hier kommen wir ins Spiel: Ob du gerade im Büro
            bist, zu Hause entspannst oder einen Shoppingtag geniesst. Wir kommen direkt zu dir und
            sorgen dafür, dass dein Innenraum wieder in neuem Glanz erstrahlt.
        </p>
    </div>
</section>

<section id="showcase" class="section-dark showcase">
    <div class="container">
        <h2>Wir zeigen's - statt zu behaupten</h2>

        <div class="carousel" data-carousel>
            <button class="arrow prev" type="button" aria-label="Vorheriges Bild" data-prev>&lsaquo;</button>

            <div class="carousel-frame">
                <div class="slides" data-slides>
                    <?php if ($showcaseItems === []): ?>
                        <article class="slide is-active">
                            <img src="assets/icons/Innersense_Logo.avif" alt="Innersense">
                            <h3>Innersense</h3>
                        </article>
                    <?php else: ?>
                        <?php foreach ($showcaseItems as $index => $item): ?>
                            <article class="slide<?= $index === 0 ? ' is-active' : '' ?>">
                                <img src="<?= htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php if ($index >= 3): ?>
                                    <h4><?= htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8') ?></h4>
                                <?php else: ?>
                                    <h3><?= htmlspecialchars($item['caption'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <button class="arrow next" type="button" aria-label="Nächstes Bild" data-next>&rsaquo;</button>
        </div>

        <div class="dots" data-dots aria-label="Slide Auswahl">
            <?php $dotCount = max(1, count($showcaseItems)); ?>
            <?php for ($dotIndex = 0; $dotIndex < $dotCount; $dotIndex++): ?>
                <button class="dot<?= $dotIndex === 0 ? ' is-active' : '' ?>" type="button" aria-label="Slide <?= $dotIndex + 1 ?>"></button>
            <?php endfor; ?>
        </div>
    </div>
</section>

<section id="preise" class="section-light">
    <div class="container text-block text-block-cta">
        <h2>Die Frage aller Fragen - Wat kos dä Spass?</h2>
        <p>
            Klar, auch wir wollen mit unserer Arbeit Geld verdienen, aber nicht um jeden Preis.
            Für uns steht die Qualität immer an erster Stelle. Wir haben lange überlegt, den
            Markt genau angeschaut und uns bewusst für faire Preise entschieden. Nicht billig,
            aber ehrlich. Klick den Button und überzeuge dich selbst.
        </p>
        <a class="primary-btn" href="preise.php">Der Button</a>
    </div>
</section>

<section id="kontakt" class="section-dark contact-section">
    <div class="container contact-grid">
        <div class="contact-copy">
            <h2>Fragen? Wünsche? Einfach melden!</h2>
            <p>
                Ob Termin, Paket oder individuelle Anliegen - schreiben Sie uns ganz unkompliziert
                über das Formular. Wir sind gerne für Sie da.
            </p>
        </div>

        <form class="contact-card" action="index.php#kontakt" method="post" data-wizard>
            <input type="hidden" name="form_id" value="contact_wizard">

            <?php if ($contactMessage !== ''): ?>
                <p class="contact-alert <?= $contactStatus === 'success' ? 'is-success' : 'is-error' ?>">
                    <?= htmlspecialchars($contactMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <div class="progress-row">
                <div class="progress-bar"><span></span></div>
                <span data-step-counter>1 / 9</span>
            </div>

            <p class="wizard-error" data-wizard-error aria-live="polite"></p>

            <section class="wizard-step is-active" data-step>
                <p class="form-question">Für welches unserer Produkte oder Dienstleistungen möchtest du Informationen anfordern?</p>
                <label class="choice-row"><input type="checkbox" name="products[]" value="Basis-Paket"> Basis-Paket</label>
                <label class="choice-row"><input type="checkbox" name="products[]" value="Intensiv-Paket"> Intensiv-Paket</label>
                <label class="choice-row"><input type="checkbox" name="products[]" value="Zusatz-Pakete"> Zusatz-Pakete</label>
                <label class="choice-row"><input type="checkbox" name="products[]" value="Allgemeine Anfragen"> Allgemeine Anfragen</label>
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">Was brauchst du?</p>
                <textarea class="form-input" name="needs" rows="5"></textarea>
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">Wunschtermin und mögliches Zeitfenster für eine Besprechung (Mo-Fr, 09:00-20:00 Uhr)</p>
                <div class="appointment-row">
                    <input class="form-input" type="date" name="preferred_date" data-preferred-date>
                    <select class="form-input" name="preferred_time" data-preferred-time>
                        <option value="">Uhrzeit waehlen</option>
                    </select>
                </div>
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">Hier kannst du uns Links zur Verfügung stellen, die deiner Meinung nach hilfreich sein könnten:</p>
                <textarea class="form-input" name="helpful_links" rows="5"></textarea>
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">Verfügbares Budget</p>
                <input class="form-input" type="text" name="budget">
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">Name *</p>
                <input class="form-input" type="text" name="name" required>
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">E-Mail *</p>
                <input class="form-input" type="email" name="email" required>
            </section>

            <section class="wizard-step" data-step>
                <p class="form-question">Telefon</p>
                <input class="form-input" type="tel" name="phone">
            </section>

            <section class="wizard-step" data-step>
                <label class="consent-row"><input type="checkbox" name="consent" value="1" required> Durch Klicken des "Abschicken"-Buttons stimme ich zu, dass die im Formular eingegebenen personenbezogenen Daten zur Bearbeitung der Anfrage und der personalisierten Beratung verarbeitet werden. Meine Einwilligungen kann ich jederzeit mit Wirkung für die Zukunft postalisch oder per E-Mail widerrufen.</label>
                <p class="consent-text">Es gilt unsere <a href="#">Datenschutzerklärung</a>.</p>
                <label class="human-row"><input type="checkbox" name="human_check" value="1" required> Ich bin ein Mensch</label>
            </section>

            <div class="form-actions" data-wizard-actions>
                <button class="ghost-btn" type="button" data-back>Zurück</button>
                <button class="primary-btn" type="button" data-next>Weiter</button>
                <button class="primary-btn" type="submit" data-submit hidden>Abschicken</button>
            </div>
        </form>
    </div>
</section>

<?php require __DIR__ . '/partials/site_footer.php'; ?>

<a class="floating-wa" href="<?= htmlspecialchars(($whatsAppHref ?? '#'), ENT_QUOTES, 'UTF-8') ?>" aria-label="WhatsApp Kontakt" target="_blank" rel="noopener noreferrer">w</a>

<script>
(() => {
    const carousel = document.querySelector('[data-carousel]');
    if (!carousel) {
        return;
    }

    const slidesEl = carousel.querySelector('[data-slides]');
    const frameEl = carousel.querySelector('.carousel-frame');
    const slides = Array.from(slidesEl.querySelectorAll('.slide'));
    const dots = Array.from(document.querySelectorAll('[data-dots] .dot'));
    const prevBtn = carousel.querySelector('[data-prev]');
    const nextBtn = carousel.querySelector('[data-next]');
    const autoIntervalMs = 10000;
    let index = 0;
    let autoTimer = null;
    let touchStartX = null;
    let touchStartY = null;

    const update = (nextIndex) => {
        index = (nextIndex + slides.length) % slides.length;
        slidesEl.style.transform = `translateX(-${index * 100}%)`;
        slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    };

    const restartAuto = () => {
        if (autoTimer !== null) {
            clearInterval(autoTimer);
        }
        autoTimer = setInterval(() => update(index + 1), autoIntervalMs);
    };

    prevBtn.addEventListener('click', () => {
        update(index - 1);
        restartAuto();
    });
    nextBtn.addEventListener('click', () => {
        update(index + 1);
        restartAuto();
    });
    dots.forEach((dot, i) => dot.addEventListener('click', () => {
        update(i);
        restartAuto();
    }));

    if (frameEl) {
        frameEl.addEventListener('touchstart', (event) => {
            const touch = event.changedTouches[0];
            touchStartX = touch.clientX;
            touchStartY = touch.clientY;
        }, { passive: true });

        frameEl.addEventListener('touchend', (event) => {
            if (touchStartX === null || touchStartY === null) {
                return;
            }

            const touch = event.changedTouches[0];
            const deltaX = touch.clientX - touchStartX;
            const deltaY = touch.clientY - touchStartY;

            touchStartX = null;
            touchStartY = null;

            if (Math.abs(deltaX) < 45 || Math.abs(deltaX) <= Math.abs(deltaY)) {
                return;
            }

            update(deltaX < 0 ? index + 1 : index - 1);
            restartAuto();
        }, { passive: true });
    }

    restartAuto();
})();

(() => {
    const wizard = document.querySelector('[data-wizard]');
    if (!wizard) {
        return;
    }

    const steps = Array.from(wizard.querySelectorAll('[data-step]'));
    const backBtn = wizard.querySelector('[data-back]');
    const nextBtn = wizard.querySelector('[data-next]');
    const submitBtn = wizard.querySelector('[data-submit]');
    const counter = wizard.querySelector('[data-step-counter]');
    const progressFill = wizard.querySelector('.progress-bar span');
    const errorEl = wizard.querySelector('[data-wizard-error]');
    let current = 0;

    const validateStep = (idx) => {
        errorEl.textContent = '';
        const step = steps[idx];

        if (idx === 0) {
            const hasAny = step.querySelector('input[type="checkbox"]:checked') !== null;
            if (!hasAny) {
                errorEl.textContent = 'Bitte waehle mindestens eine Option aus.';
                return false;
            }
        }

        const fields = Array.from(step.querySelectorAll('input, textarea, select'));
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    };

    const render = () => {
        steps.forEach((step, i) => {
            step.classList.toggle('is-active', i === current);
            step.hidden = i !== current;
        });

        counter.textContent = `${current + 1} / ${steps.length}`;
        progressFill.style.width = `${((current + 1) / steps.length) * 100}%`;
        backBtn.disabled = current === 0;

        const isLast = current === steps.length - 1;
        nextBtn.hidden = isLast;
        submitBtn.hidden = !isLast;
        submitBtn.disabled = !isLast;
    };

    backBtn.addEventListener('click', () => {
        if (current > 0) {
            current -= 1;
            render();
        }
    });

    nextBtn.addEventListener('click', () => {
        if (!validateStep(current)) {
            return;
        }
        if (current < steps.length - 1) {
            current += 1;
            render();
        }
    });

    wizard.addEventListener('submit', (event) => {
        if (!validateStep(current)) {
            event.preventDefault();
        }
    });

    render();
})();

(() => {
    const dateInput = document.querySelector('[data-preferred-date]');
    const timeSelect = document.querySelector('[data-preferred-time]');
    if (!dateInput || !timeSelect) {
        return;
    }

    const updateTimeOptions = async () => {
        const dateValue = dateInput.value;
        const currentValue = timeSelect.value;
        timeSelect.innerHTML = '<option value="">Uhrzeit waehlen</option>';

        if (!dateValue) {
            return;
        }

        try {
            const response = await fetch(`index.php?ajax=available_slots&date=${encodeURIComponent(dateValue)}`, {
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
})();
</script>
</body>
</html>
