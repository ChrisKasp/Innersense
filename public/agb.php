<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Allgemeine Geschäftsbedingungen</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/agb.css">
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
<main class="agb-page" aria-label="Allgemeine Geschäftsbedingungen">
    <section class="agb-card">
        <h1>Allgemeine Geschäftsbedingungen</h1>
        <section class="agb-section">
            <h2>1. Geltungsbereich</h2>
            <p>(1) Diese AGB gelten fuer alle Vertraege zwischen [Mobile Fahrzeugaufbereitung InnerSense] (nachfolgend "Anbieter") und seinen Kunden (Verbraucher und Unternehmer).</p>
            <p>(2) Verbraucher im Sinne dieser AGB sind natuerliche Personen, die ein Rechtsgeschaeft zu privaten Zwecken abschliessen. Unternehmer sind natuerliche oder juristische Personen, die in Ausuebung ihrer gewerblichen Taetigkeit handeln.</p>
        </section>

        <section class="agb-section">
            <h2>2. Vertragsschluss</h2>
            <p>(1) Angebote des Anbieters sind freibleibend und unverbindlich.</p>
            <p>(2) Ein Vertrag kommt durch Terminvereinbarung (telefonisch, online oder schriftlich) zustande.</p>
            <p>(3) Der Anbieter behaelt sich vor, Auftraege ohne Angabe von Gruenden abzulehnen.</p>
        </section>

        <section class="agb-section">
            <h2>3. Leistungsumfang</h2>
            <p>(1) Der Anbieter erbringt Dienstleistungen im Bereich Fahrzeugaufbereitung (Innen- und Aussenreinigung, Pflege, Politur etc.).</p>
            <p>(2) Der konkrete Leistungsumfang ergibt sich aus dem individuell vereinbarten Angebot.</p>
            <p>(3) Abweichungen sind zulaessig, soweit sie technisch erforderlich oder zumutbar sind.</p>
        </section>

        <section class="agb-section">
            <h2>4. Preise und Zahlungsbedingungen</h2>
            <p>(1) Es gelten die zum Zeitpunkt der Buchung vereinbarten Preise.</p>
            <p>(2) Fuer Verbraucher verstehen sich Preise als Endpreise inkl. gesetzlicher MwSt. (sofern erhoben).</p>
            <p>(3) Fuer Unternehmer verstehen sich Preise zzgl. gesetzlicher MwSt. (falls ausweisbar).</p>
            <p>(4) Die Zahlung erfolgt unmittelbar nach Leistungserbringung in bar, Direktbezahlung (PayPal) oder per Ueberweisung.</p>
            <p>(5) Bei groesseren Auftraegen kann eine Anzahlung verlangt werden.</p>
        </section>

        <section class="agb-section">
            <h2>5. Termine und Stornierung</h2>
            <p>(1) Vereinbarte Termine sind verbindlich.</p>
            <p>(2) Eine kostenfreie Stornierung ist bis 24 Stunden vor dem Termin moeglich.</p>
            <p>(3) Bei kurzfristiger Absage oder Nichterscheinen kann eine Ausfallpauschale von 30 Euro berechnet werden.</p>
            <p>(4) Der Anbieter ist berechtigt, Termine aus wichtigen Gruenden zu verschieben (z. B. Wetter, technische Probleme).</p>
        </section>

        <section class="agb-section">
            <h2>6. Mitwirkungspflichten des Kunden</h2>
            <p>Der Kunde hat sicherzustellen, dass:</p>
            <ul>
                <li>ausreichend Platz fuer die Durchfuehrung vorhanden ist</li>
                <li>Zugang zu Wasser und/oder Strom besteht (falls erforderlich)</li>
                <li>das Fahrzeug leergeraeumt ist (keine Wertgegenstaende im Innenraum)</li>
            </ul>
        </section>

        <section class="agb-section">
            <h2>7. Haftung</h2>
            <p>(1) Der Anbieter haftet unbeschraenkt bei Vorsatz und grober Fahrlaessigkeit.</p>
            <p>(2) Bei einfacher Fahrlaessigkeit haftet der Anbieter nur bei Verletzung wesentlicher Vertragspflichten (Kardinalpflichten) und begrenzt auf den vorhersehbaren Schaden.</p>
            <p>(3) Keine Haftung besteht fuer:</p>
            <ul>
                <li>bereits vorhandene Schaeden (z. B. Kratzer, Lackdefekte)</li>
                <li>Schaeden durch alters- oder nutzungsbedingte Materialschwaeche</li>
                <li>lose oder unsachgemaess befestigte Fahrzeugteile</li>
                <li>persoenliche Gegenstaende im Fahrzeug</li>
            </ul>
            <p>(4) Bei empfindlichen Materialien erfolgt die Behandlung nach bestem Wissen, jedoch auf Risiko des Kunden, sofern dieser vorab informiert wurde.</p>
        </section>

        <section class="agb-section">
            <h2>8. Witterung und Durchfuehrung</h2>
            <p>(1) Die Leistungserbringung erfolgt wetterabhaengig.</p>
            <p>(2) Bei ungeeigneten Wetterbedingungen (Regen, Frost, Sturm) kann der Termin verschoben werden.</p>
            <p>(3) Hieraus entstehen keine Schadensersatzansprueche.</p>
        </section>

        <section class="agb-section">
            <h2>9. Gewaehrleistung und Reklamation</h2>
            <p>(1) Der Kunde ist verpflichtet, die Leistung unmittelbar nach Durchfuehrung zu pruefen.</p>
            <p>(2) Offensichtliche Maengel sind sofort vor Ort anzuzeigen.</p>
            <p>(3) Spaetere Reklamationen muessen innerhalb von 24 Stunden gemeldet werden.</p>
            <p>(4) Bei berechtigten Maengeln erfolgt Nachbesserung.</p>
        </section>

        <section class="agb-section">
            <h2>10. Widerrufsrecht (nur fuer Verbraucher)</h2>
            <p>(1) Verbraucher haben grundsaetzlich ein gesetzliches Widerrufsrecht.</p>
            <p>(2) Das Widerrufsrecht erlischt vorzeitig, wenn:</p>
            <ul>
                <li>der Kunde ausdruecklich verlangt, dass die Dienstleistung vor Ablauf der Widerrufsfrist beginnt</li>
                <li>und die Leistung vollstaendig erbracht wurde</li>
            </ul>
        </section>

        <section class="agb-section">
            <h2>11. Datenschutz</h2>
            <p>Personenbezogene Daten werden ausschliesslich zur Auftragsabwicklung verwendet und gemaess den gesetzlichen Datenschutzbestimmungen verarbeitet.</p>
        </section>

        <section class="agb-section">
            <h2>12. Gerichtsstand und Schlussbestimmungen</h2>
            <p>(1) Es gilt das Recht der Bundesrepublik Deutschland.</p>
            <p>(2) Fuer Unternehmer ist Gerichtsstand der Sitz des Anbieters.</p>
            <p>(3) Sollten einzelne Bestimmungen unwirksam sein, bleibt der Vertrag im Uebrigen wirksam.</p>
        </section>
    </section>
</main>
</body>
</html>
