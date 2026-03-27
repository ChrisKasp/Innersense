<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Datenschutzerklärung</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/datenschutz.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<?php
$activePage = '';
$hideVerwaltungInTopnav = true;
require __DIR__ . '/partials/site_header.php';
?>
<main class="privacy-page" aria-label="Datenschutzerklärung">
    <section class="privacy-card">
        <h1>Datenschutzerklärung (Website)</h1>
        <p>Mobile Fahrzeugaufbereitung InnerSense</p>

        <section>
            <h2>1. Allgemeine Hinweise</h2>
            <p>Der Schutz Ihrer persönlichen Daten ist uns ein wichtiges Anliegen. Wir behandeln Ihre personenbezogenen Daten vertraulich und entsprechend der gesetzlichen Datenschutzvorschriften (DSGVO, BDSG).</p>
        </section>

        <section>
            <h2>2. Verantwortlicher</h2>
            <p>
                Mobile Fahrzeugaufbereitung InnerSense<br>
                Büsdorferstraße 5A<br>
                50129 Bergheim<br>
                0163 - 811 65 97
            </p>
        </section>

        <section>
            <h2>3. Erhebung und Speicherung personenbezogener Daten beim Besuch der Website</h2>
            <p>Beim Aufrufen unserer Website werden automatisch Informationen durch den Browser an unseren Server gesendet. Diese werden temporär in sogenannten Logfiles gespeichert.</p>
            <p>Erfasst werden können:</p>
            <ul>
                <li>IP-Adresse</li>
                <li>Datum und Uhrzeit der Anfrage</li>
                <li>Browsertyp und Version</li>
                <li>Betriebssystem</li>
                <li>Referrer-URL</li>
            </ul>
            <p>Zweck:</p>
            <ul>
                <li>Sicherstellung eines reibungslosen Verbindungsaufbaus</li>
                <li>Systemsicherheit und -stabilität</li>
            </ul>
            <p>Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO</p>
        </section>

        <section>
            <h2>4. Kontaktaufnahme per E-Mail</h2>
            <p>Wenn Sie uns per E-Mail kontaktieren, werden Ihre Angaben zur Bearbeitung der Anfrage gespeichert.</p>
            <p>Verarbeitete Daten:</p>
            <ul>
                <li>Name</li>
                <li>E-Mail-Adresse</li>
                <li>Inhalt der Nachricht</li>
            </ul>
            <p>Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO</p>
        </section>

        <section>
            <h2>5. Kontakt über WhatsApp</h2>
            <p>Wenn Sie uns über WhatsApp kontaktieren, erfolgt dies freiwillig.</p>
            <p>Anbieter: WhatsApp Ireland Limited</p>
            <p>Hinweis: Bei Nutzung von WhatsApp können personenbezogene Daten an Server außerhalb der EU (z. B. USA) übertragen werden.</p>
            <p>Rechtsgrundlage: Art. 6 Abs. 1 lit. a DSGVO (Einwilligung)</p>
        </section>

        <section>
            <h2>6. Social Media Buttons (Facebook und Instagram)</h2>
            <p>Auf unserer Website befinden sich Buttons zu unseren Profilen bei:</p>
            <ul>
                <li>Facebook</li>
                <li>Instagram</li>
            </ul>
            <p>Beim Anklicken der Buttons werden Sie direkt auf die jeweilige Plattform weitergeleitet.</p>
            <p>Anbieter: Meta Platforms Ireland Ltd.</p>
            <p>Hinweis: Beim Besuch dieser Plattformen gelten die Datenschutzbestimmungen der jeweiligen Anbieter. Es kann zu einer Verarbeitung Ihrer Daten außerhalb der EU kommen.</p>
        </section>

        <section>
            <h2>7. Keine automatische Datenübertragung durch Buttons</h2>
            <p>Unsere Social-Media-Buttons sind so eingebunden, dass keine Daten automatisch übertragen werden, solange Sie diese nicht anklicken.</p>
        </section>

        <section>
            <h2>8. Speicherdauer</h2>
            <p>Personenbezogene Daten werden nur so lange gespeichert, wie dies für die jeweiligen Zwecke erforderlich ist oder gesetzliche Aufbewahrungspflichten bestehen.</p>
        </section>

        <section>
            <h2>9. Ihre Rechte</h2>
            <p>Sie haben das Recht auf:</p>
            <ul>
                <li>Auskunft</li>
                <li>Berichtigung</li>
                <li>Löschung</li>
                <li>Einschränkung der Verarbeitung</li>
                <li>Datenübertragbarkeit</li>
                <li>Widerspruch gegen die Verarbeitung</li>
            </ul>
        </section>

        <section>
            <h2>10. Widerruf Ihrer Einwilligung</h2>
            <p>Sie können eine erteilte Einwilligung jederzeit mit Wirkung für die Zukunft widerrufen.</p>
        </section>

        <section>
            <h2>11. Beschwerderecht</h2>
            <p>Sie haben das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren.</p>
            <p>Zuständig für NRW: Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen (LDI NRW)</p>
        </section>

        <section>
            <h2>12. Datensicherheit</h2>
            <p>Wir verwenden geeignete technische und organisatorische Sicherheitsmaßnahmen, um Ihre Daten zu schützen.</p>
        </section>

        <section>
            <h2>13. Aktualität und Änderung dieser Datenschutzerklärung</h2>
            <p>Diese Datenschutzerklärung ist aktuell gültig und kann bei Bedarf angepasst werden.</p>
        </section>
    </section>
</main>
</body>
</html>
