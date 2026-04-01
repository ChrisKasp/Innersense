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
        <h1>Datenschutzerklärung</h1>

        <section>
            <h2>1. Verantwortlicher</h2>
            <p>
                Mobile Fahrzeugaufbereitung InnerSense<br>
                Büsdorferstr. 5A<br>
                50129 Bergheim<br>
                Deutschland
            </p>
            <p>E-Mail: <a href="mailto:aufbereitung.innersense@gmail.com">aufbereitung.innersense@gmail.com</a><br>Telefon: +49 163 811 65 97</p>
            <p>Ein Datenschutzbeauftragter ist nicht bestellt, da hierzu keine gesetzliche Verpflichtung besteht.</p>
        </section>

        <section>
            <h2>2. Hosting</h2>
            <p>Unsere Website wird bei einem deutschen Hosting-Anbieter betrieben. Die Server befinden sich in Deutschland.</p>
            <p>Die Nutzung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einer sicheren und effizienten Bereitstellung unseres Online-Angebots).</p>
        </section>

        <section>
            <h2>3. Zugriffsdaten (Server-Logfiles)</h2>
            <p>Der Hosting-Anbieter erhebt und speichert automatisch Informationen in sogenannten Server-Logfiles, die Ihr Browser automatisch übermittelt. Dies sind insbesondere:</p>
            <ul>
                <li>IP-Adresse</li>
                <li>Datum und Uhrzeit der Anfrage</li>
                <li>aufgerufene Seite</li>
                <li>Browsertyp und -version</li>
                <li>Betriebssystem</li>
            </ul>
            <p>Diese Daten dienen ausschließlich der Sicherstellung eines störungsfreien Betriebs der Website und werden nicht mit anderen Datenquellen zusammengeführt.</p>
            <p>Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO.</p>
        </section>

        <section>
            <h2>4. Kontaktformular</h2>

            <h3>Erhobene Daten</h3>
            <p>Wenn Sie uns über das Kontaktformular kontaktieren, werden folgende Daten erhoben:</p>
            <ul>
                <li>Name</li>
                <li>E-Mail-Adresse</li>
                <li>Telefonnummer</li>
                <li>Nachrichtentext</li>
                <li>gewünschtes Leistungspaket</li>
                <li>Sonderwünsche</li>
                <li>Budgetrahmen</li>
            </ul>

            <h3>Zweck der Verarbeitung</h3>
            <p>Die Verarbeitung erfolgt zur:</p>
            <ul>
                <li>Bearbeitung Ihrer Anfrage</li>
                <li>Kontaktaufnahme per E-Mail oder Telefon</li>
                <li>Angebotserstellung</li>
                <li>Verwaltung der Anfrage in unserer internen Verwaltungssoftware</li>
            </ul>

            <h3>Technischer Ablauf</h3>
            <ul>
                <li>Ihre Anfrage wird per E-Mail an uns übermittelt</li>
                <li>Sie erhalten eine automatische Bestätigungs-E-Mail</li>
                <li>Eine persönliche Rückmeldung erfolgt durch uns</li>
                <li>Die Daten werden in einer internen Datenbank (MariaDB auf dem Server) gespeichert</li>
            </ul>

            <h3>Rechtsgrundlage</h3>
            <p>Die Verarbeitung erfolgt auf Grundlage von:</p>
            <ul>
                <li>Art. 6 Abs. 1 lit. b DSGVO (vorvertragliche Maßnahmen)</li>
                <li>Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an effizienter Bearbeitung von Anfragen)</li>
            </ul>
        </section>

        <section>
            <h2>5. Speicherung und Verarbeitung in Datenbank</h2>
            <p>Die im Rahmen der Anfrage erhobenen Daten werden in einer eigens entwickelten Verwaltungssoftware gespeichert, die auf der Hosting-Umgebung betrieben wird.</p>
            <p>Zweck:</p>
            <ul>
                <li>Organisation und Nachverfolgung von Anfragen</li>
                <li>Kundenverwaltung</li>
                <li>spätere Kommunikation</li>
            </ul>
        </section>

        <section>
            <h2>6. Speicherdauer</h2>
            <ul>
                <li>Anfragen werden gespeichert, bis die Bearbeitung abgeschlossen ist</li>
                <li>Anschließend werden die Daten archiviert</li>
            </ul>
            <p>Die Speicherung erfolgt nur so lange, wie dies für die jeweiligen Zwecke erforderlich ist oder gesetzliche Aufbewahrungspflichten bestehen.</p>
        </section>

        <section>
            <h2>7. E-Mail-Kommunikation</h2>
            <p>Die Kommunikation erfolgt über die E-Mail-Infrastruktur des Hosting-Anbieters.</p>
            <p>Hinweis: Die Datenübertragung im Internet (z. B. bei der Kommunikation per E-Mail) kann Sicherheitslücken aufweisen. Ein lückenloser Schutz der Daten vor dem Zugriff durch Dritte ist nicht möglich.</p>
        </section>

        <section>
            <h2>8. Datenweitergabe</h2>
            <p>Eine Weitergabe Ihrer Daten an Dritte erfolgt nicht.</p>
        </section>

        <section>
            <h2>9. Cookies und Tracking</h2>
            <p>Unsere Website verwendet:</p>
            <ul>
                <li>verwendet technisch notwendige Cookies (z. B. zur Sitzungsverwaltung), die für den Betrieb der Seite erforderlich sind.</li>
                <li>kein Tracking (z. B. Google Analytics)</li>
                <li>keine externen Inhalte (z. B. YouTube oder Google Maps)</li>
            </ul>
        </section>

        <section>
            <h2>10. Externe Links</h2>
            <p>Unsere Website enthält Links zu externen Plattformen (z. B. Instagram, Facebook, WhatsApp).</p>
            <p>Beim Anklicken dieser Links verlassen Sie unsere Website. Für die Verarbeitung Ihrer Daten auf diesen Plattformen sind ausschließlich die jeweiligen Anbieter verantwortlich.</p>
        </section>

        <section>
            <h2>11. Ihre Rechte</h2>
            <p>Sie haben jederzeit das Recht auf:</p>
            <ul>
                <li>Auskunft über Ihre gespeicherten Daten (Art. 15 DSGVO)</li>
                <li>Berichtigung unrichtiger Daten (Art. 16 DSGVO)</li>
                <li>Löschung Ihrer Daten (Art. 17 DSGVO)</li>
                <li>Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
                <li>Datenübertragbarkeit (Art. 20 DSGVO)</li>
                <li>Widerspruch gegen die Verarbeitung (Art. 21 DSGVO)</li>
            </ul>
        </section>

        <section>
            <h2>12. Beschwerderecht</h2>
            <p>Sie haben das Recht, sich bei einer Datenschutzaufsichtsbehörde zu beschweren.</p>
            <p>Zuständig ist die Aufsichtsbehörde des Bundeslandes Nordrhein-Westfalen.</p>
        </section>

        <section>
            <h2>13. Datensicherheit</h2>
            <p>Diese Website nutzt aus Sicherheitsgründen und zum Schutz der Übertragung vertraulicher Inhalte eine SSL- bzw. TLS-Verschlüsselung.</p>
        </section>

        <section>
            <h2>14. Pflicht zur Bereitstellung von Daten</h2>
            <p>Die Bereitstellung Ihrer Daten im Kontaktformular ist für die Bearbeitung Ihrer Anfrage erforderlich. Ohne diese Daten können wir Ihre Anfrage nicht bearbeiten.</p>
        </section>
    </section>
</main>
</body>
</html>
