<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/env.php';
loadEnv(dirname(__DIR__) . '/config/.env');
require_once dirname(__DIR__) . '/src/FormBotProtection.php';

$widerrufRecipientEmail = 'Aufbereitung.innersense@gmail.com';
$widerrufStatus = (string) ($_GET['widerruf'] ?? '');
$widerrufErrors = [];
$turnstileSiteKey = trim((string) (getenv('TURNSTILE_SITE_KEY') ?: ''));
$turnstileSecretKey = trim((string) (getenv('TURNSTILE_SECRET_KEY') ?: ''));
$turnstileEnabled = isTurnstileConfigured($turnstileSiteKey, $turnstileSecretKey);

$widerrufFormData = [
    'service_details' => '',
    'order_started_on' => '',
    'customer_name' => '',
    'street_address' => '',
    'customer_email' => '',
    'customer_phone' => '',
    'message' => '',
    'privacy_ack' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['form_id'] ?? '') === 'widerruf_form') {
    $botProtectionValid = validateBotProtection($_POST, 'widerruf_form');
    $turnstileValid = !$turnstileEnabled || validateTurnstileToken(
        $_POST['cf-turnstile-response'] ?? null,
        $turnstileSecretKey,
        getClientIpAddress()
    );

    foreach (array_keys($widerrufFormData) as $key) {
        $widerrufFormData[$key] = trim((string) ($_POST[$key] ?? ''));
    }

    if (!$botProtectionValid) {
        $widerrufErrors[] = 'Deine Anfrage konnte nicht verifiziert werden. Bitte versuche es erneut.';
    }
    if (!$turnstileValid) {
        $widerrufErrors[] = 'Bitte bestaetige die Sicherheitspruefung und versuche es erneut.';
    }

    if ($widerrufFormData['service_details'] === '') {
        $widerrufErrors[] = 'Bitte gib die Dienstleistung an, die widerrufen werden soll.';
    }
    if ($widerrufFormData['order_started_on'] === '') {
        $widerrufErrors[] = 'Bitte gib an, wann bestellt oder begonnen wurde.';
    }
    if ($widerrufFormData['customer_name'] === '') {
        $widerrufErrors[] = 'Bitte gib deinen Namen an.';
    }
    if ($widerrufFormData['street_address'] === '') {
        $widerrufErrors[] = 'Bitte gib deine Anschrift an.';
    }
    if (
        $widerrufFormData['customer_email'] === ''
        || filter_var($widerrufFormData['customer_email'], FILTER_VALIDATE_EMAIL) === false
    ) {
        $widerrufErrors[] = 'Bitte gib eine gueltige E-Mail-Adresse an.';
    }
    if ($widerrufFormData['privacy_ack'] !== '1') {
        $widerrufErrors[] = 'Bitte bestaetige die Uebermittlung deines Widerrufs.';
    }

    if ($widerrufErrors === []) {
        $subjectName = preg_replace('/[\r\n]+/', ' ', $widerrufFormData['customer_name']) ?: 'Kunde';
        $subject = 'Widerruf von ' . $subjectName;

        $bodyLines = [
            'Neuer Widerruf ueber das Formular auf widerrufsbelehrung.php',
            '',
            'Dienstleistung: ' . $widerrufFormData['service_details'],
            'Bestellt am / begonnen am: ' . $widerrufFormData['order_started_on'],
            'Name des Kunden: ' . $widerrufFormData['customer_name'],
            'Anschrift: ' . $widerrufFormData['street_address'],
            'E-Mail: ' . $widerrufFormData['customer_email'],
            'Telefon: ' . ($widerrufFormData['customer_phone'] !== '' ? $widerrufFormData['customer_phone'] : '-'),
            '',
            'Zusaetzliche Nachricht:',
            $widerrufFormData['message'] !== '' ? $widerrufFormData['message'] : '-',
            '',
            'Gesendet am: ' . (new DateTimeImmutable('now'))->format('d.m.Y H:i:s'),
        ];

        $body = implode("\n", $bodyLines);
        $replyTo = preg_replace('/[\r\n]+/', '', $widerrufFormData['customer_email']) ?: '';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: Innersense Website <no-reply@innersense.sellerie.net>',
            'Reply-To: ' . $replyTo,
        ];

        $sent = @mail($widerrufRecipientEmail, $subject, $body, implode("\r\n", $headers));

        if ($sent) {
            header('Location: widerrufsbelehrung.php?widerruf=success#widerruf-form');
            exit;
        }

        $widerrufStatus = 'error';
        $widerrufErrors[] = 'Der Widerruf konnte nicht versendet werden. Bitte versuche es erneut.';
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Widerrufsbelehrung</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/agb.css">
    <link rel="stylesheet" href="assets/widerrufsbelehrung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
    <?php if ($turnstileEnabled): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
</head>
<body>
<?php
$activePage = '';
$hideVerwaltungInTopnav = true;
require __DIR__ . '/partials/site_header.php';
?>
<main class="agb-page" aria-label="Widerrufsbelehrung">
    <section class="agb-card">
        <h1>Widerrufsbelehrung</h1>

        <section class="agb-section">
            <h2>Widerrufsrecht</h2>
            <p>Verbraucher haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p>
            <p>Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses.</p>
            <p>Der Vertrag kommt bei mobilen Dienstleistungen mit Beginn der Ausführung vor Ort beim Kunden zustande.</p>
            <p>Um Ihr Widerrufsrecht auszuüben, müssen Sie uns</p>
            <p>
                Mobile Fahrzeugaufbereitung InnerSense<br>
                Inhaber: Gerome Selle<br>
                Büsdorfer Str. 5A<br>
                50129 Bergheim<br>
                Telefon: 0163 8116597<br>
                E-Mail: <a href="mailto:Aufbereitung.innersense@gmail.com">Aufbereitung.innersense@gmail.com</a>
            </p>
            <p>mittels einer eindeutigen Erklärung (z. B. per E-Mail oder telefonisch) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.</p>
            <p>Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung vor Ablauf der Frist absenden.</p>
        </section>

        <section class="agb-section">
            <h2>Folgen des Widerrufs</h2>
            <p>Wenn Sie diesen Vertrag widerrufen, erstatten wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag, an dem die Mitteilung über Ihren Widerruf bei uns eingegangen ist.</p>
            <p>Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben.</p>
            <p>Haben Sie verlangt, dass die Dienstleistung während der Widerrufsfrist beginnen soll, so haben Sie uns einen angemessenen Betrag zu zahlen, der dem Anteil der bereits erbrachten Leistungen entspricht.</p>
        </section>

        <section class="agb-section">
            <h2>Vorzeitiges Erlöschen des Widerrufsrechts</h2>
            <p>Ihr Widerrufsrecht endet vorzeitig, wenn wir die Dienstleistung vollständig erbracht haben und Sie zuvor ausdrücklich zugestimmt haben, dass wir bereits vor Ablauf der Widerrufsfrist beginnen. Außerdem müssen Sie bestätigt haben, dass Ihnen bewusst ist, dass Sie Ihr Widerrufsrecht in diesem Fall verlieren.</p>
        </section>

        <section class="agb-section">
            <h2>Hinweis zum sofortigen Beginn der Dienstleistung</h2>
            <p>Bei Terminvereinbarung oder vor Beginn der Arbeiten erklären Sie sich ausdrücklich damit einverstanden, dass wir vor Ablauf der Widerrufsfrist mit der Dienstleistung beginnen. Ihnen ist bekannt, dass Sie bei vollständiger Vertragserfüllung Ihr Widerrufsrecht verlieren.</p>
        </section>

        <section class="agb-section">
            <h2>Widerruf online senden</h2>
            <p>Wenn Sie den Vertrag widerrufen möchten, können Sie den Widerruf direkt mit folgendem Formular an uns senden:</p>
            <p>
                An:<br>
                Mobile Fahrzeugaufbereitung InnerSense<br>
                Büsdorfer Str. 5A<br>
                50129 Bergheim<br>
                E-Mail: <a href="mailto:<?= h($widerrufRecipientEmail) ?>"><?= h($widerrufRecipientEmail) ?></a>
            </p>

            <?php if ($widerrufStatus === 'success'): ?>
                <p class="widerruf-feedback widerruf-feedback-success">Ihr Widerruf wurde erfolgreich versendet.</p>
            <?php endif; ?>

            <?php if ($widerrufErrors !== []): ?>
                <div class="widerruf-feedback widerruf-feedback-error" role="alert" aria-live="polite">
                    <p>Bitte korrigieren Sie folgende Angaben:</p>
                    <ul>
                        <?php foreach ($widerrufErrors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form id="widerruf-form" class="widerruf-form" method="post" action="widerrufsbelehrung.php#widerruf-form" novalidate>
                <input type="hidden" name="form_id" value="widerruf_form">
                <input type="hidden" name="_started_at" value="<?= (string) time() ?>">
                <p style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                </p>

                <label for="service_details">Hiermit widerrufe(n) ich/wir den Vertrag über folgende Dienstleistung *</label>
                <textarea id="service_details" name="service_details" rows="3" required><?= h($widerrufFormData['service_details']) ?></textarea>

                <label for="order_started_on">Bestellt am / begonnen am *</label>
                <input id="order_started_on" type="text" name="order_started_on" required value="<?= h($widerrufFormData['order_started_on']) ?>" placeholder="z. B. 25.03.2026">

                <label for="customer_name">Name des Kunden *</label>
                <input id="customer_name" type="text" name="customer_name" required value="<?= h($widerrufFormData['customer_name']) ?>">

                <label for="street_address">Anschrift *</label>
                <textarea id="street_address" name="street_address" rows="2" required><?= h($widerrufFormData['street_address']) ?></textarea>

                <label for="customer_email">E-Mail-Adresse *</label>
                <input id="customer_email" type="email" name="customer_email" required value="<?= h($widerrufFormData['customer_email']) ?>">

                <label for="customer_phone">Telefon (optional)</label>
                <input id="customer_phone" type="text" name="customer_phone" value="<?= h($widerrufFormData['customer_phone']) ?>">

                <label for="message">Zusaetzliche Nachricht (optional)</label>
                <textarea id="message" name="message" rows="4"><?= h($widerrufFormData['message']) ?></textarea>

                <label class="widerruf-consent">
                    <input type="checkbox" name="privacy_ack" value="1" <?= $widerrufFormData['privacy_ack'] === '1' ? 'checked' : '' ?> required>
                    Ich bestaetige, dass mein Widerruf an die oben genannte E-Mail-Adresse uebermittelt werden soll.
                </label>

                <?php if ($turnstileEnabled): ?>
                    <div class="cf-turnstile" data-sitekey="<?= h($turnstileSiteKey) ?>"></div>
                <?php endif; ?>

                <button type="submit" class="widerruf-submit">Widerruf per E-Mail senden</button>
            </form>
        </section>
    </section>
</main>
</body>
</html>
