<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Unsere Preise</title>
    <link rel="stylesheet" href="assets/preise.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<?php
$activePage = 'preise';
require __DIR__ . '/partials/site_header.php';
?>

<main>
    <section class="hero-pricing">
        <div class="container">
            <h1>Transparente Preise. Ehrlich und fair.</h1>
            <p>
                Wie wir auf der Startseite schon sagen: Wir sind nicht billig - aber fair. Unsere Preise sind fest und richten
                sich nach der Größe deines Fahrzeugs. Dabei unterscheiden wir zwischen Kleinwagen, Kompaktklasse,
                Mittelklasse sowie Oberklasse bzw. SUV/Großraumfahrzeugen.
            </p>
            <p>
                Du möchtest mehr als eine Basisaufbereitung? Kein Problem - wir bieten zusätzlich individuelle Pakete an,
                die du ganz einfach dazubuchen kannst. So bekommst du genau das, was du brauchst - ohne Überraschungen.
            </p>

            <div class="service-grid" aria-label="Leistungskarten">
                <article class="service-card">
                    <img src="assets/Bilder/Basis_Innenreinigung.avif" alt="Basis-Innenreinigung">
                    <h2>Basis-<br>Innenreinigung</h2>
                    <p>Für leichte Verschmutzungen &amp; regelmäßige Pflege</p>
                    <ul>
                        <li>Saugen (Innenraum &amp; Kofferraum)</li>
                        <li>Cockpit- &amp; Kunststoffpflege</li>
                        <li>Türeinstege &amp; Verkleidungen reinigen</li>
                        <li>Fußmatten reinigen (Gummi/Textil)</li>
                        <li>Innenscheiben streifenfrei säubern</li>
                    </ul>
                </article>
                <article class="service-card">
                    <img src="assets/Bilder/Intensiv_Innenreinigung.avif" alt="Intensiv-Innenreinigung">
                    <h2>Intensiv-<br>Innenreinigung</h2>
                    <p>Bei stärkerer Verschmutzung - inkl. Basis-Reinigung</p>
                    <ul>
                        <li>Tiefenreinigung von Armaturen &amp; Lüftungsschlitzen</li>
                        <li>Shampoonieren &amp; Extraktion der Sitze &amp; Kofferraum</li>
                    </ul>
                </article>
                <article class="service-card">
                    <img src="assets/Bilder/Textil_Alcantara_Versiegelung.avif" alt="Textil- und Alcantara-Versiegelung">
                    <h2>Textil- &amp;<br>Alcantara-<br>Versiegelung</h2>
                    <p>
                        Nach der Reinigung empfiehlt sich eine Textil- und Alcantara-Versiegelung,
                        um die Materialien langfristig zu schützen.
                    </p>
                </article>
                <article class="service-card">
                    <img src="assets/Bilder/Tiefenreinigung_Kindersitz.avif" alt="Tiefenreinigung Kindersitz">
                    <h2>Tiefenreinigung<br>Kindersitz</h2>
                    <p>
                        Egal ob Essensreste, Getränkeflecken, Krümel oder unangenehme Gerüche.
                        Die regelmäßige Reinigung sorgt für Hygiene und ein gutes Gefühl.
                    </p>
                </article>
                <article class="service-card">
                    <img src="assets/Bilder/Tierhaarentfernung.avif" alt="Tierhaarentfernung">
                    <h2>Tierhaarent-<br>fernung</h2>
                    <p>
                        Tierhaare im Auto? Kein Problem. Mit der richtigen Technik und passenden Geräten
                        machen wir dein Fahrzeug wieder haarfrei.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <section class="price-list-wrap">
        <div class="container">
            <h2>Unsere Preise</h2>

            <article class="price-row">
                <div>
                    <h3>Kleinwagen z.b. Hyundai i10</h3>
                    <ul>
                        <li>Basis-Innenraumreinigung - 100,00€<br>Dauer : 1 bis 1,5 Stunden</li>
                        <li>Intensiv-Innenraumreinigung - 110,00€<br>Dauer : 1,5 bis 2 Stunden</li>
                    </ul>
                </div>
                <img src="assets/Bilder/Kleinwagen.avif" alt="Kleinwagen">
            </article>

            <article class="price-row">
                <div>
                    <h3>Kompaktklasse z.b. VW Golf</h3>
                    <ul>
                        <li>Basis-Innenraumreinigung - 110,00€<br>Dauer : 1,5 bis 1,75 Stunden</li>
                        <li>Intensiv-Innenraumreinigung - 120,00€<br>Dauer : 2 bis 2,5 Stunden</li>
                    </ul>
                </div>
                <img src="assets/Bilder/Kompaktklasse.avif" alt="Kompaktklasse">
            </article>

            <article class="price-row">
                <div>
                    <h3>Mittelklasse z.b. VW Passat</h3>
                    <ul>
                        <li>Basis-Innenraumreinigung - 130,00€<br>Dauer : 1,75 bis 2 Stunden</li>
                        <li>Intensiv-Innenraumreinigung - 140,00€<br>Dauer : 2,5 bis 3 Stunden</li>
                    </ul>
                </div>
                <img src="assets/Bilder/Mittelklasse.avif" alt="Mittelklasse">
            </article>

            <article class="price-row">
                <div>
                    <h3>Oberklasse/SUV/Großraum z.b. Mercedes E-Klasse od. VW Tiguan</h3>
                    <ul>
                        <li>Basis-Innenraumreinigung - 150,00€<br>Dauer : 2,75 bis 3 Stunden</li>
                        <li>Intensiv-Innenraumreinigung - 160,00€<br>Dauer : 3 bis 4 Stunden</li>
                    </ul>
                </div>
                <img src="assets/Bilder/Oberklasse.avif" alt="Oberklasse SUV">
            </article>

            <article class="price-row">
                <div>
                    <h3>Hundebesitzer Paket</h3>
                    <ul>
                        <li>Gründliche Entfernung sämtlicher Hundehaare - 25,00 €<br>Dauer : 30 bis 45 Minuten</li>
                    </ul>
                </div>
                <img src="assets/Bilder/Hundebesitzer.avif" alt="Hundebesitzer Paket">
            </article>

            <article class="price-row">
                <div>
                    <h3>Tiefenreinigung Kindersitz</h3>
                    <ul>
                        <li>Tiefenreine Entfernung von Essensresten, Flecken und Gerüchen mit Sprühextraktion für porentiefe Sauberkeit - 20 €<br>Dauer : 15 bis 30 Minuten</li>
                    </ul>
                </div>
                <img src="assets/Bilder/Tiefenreinigung.avif" alt="Tiefenreinigung Kindersitz">
            </article>

        </div>
    </section>
</main>

<?php require __DIR__ . '/partials/site_footer.php'; ?>

<a class="floating-wa" href="<?= htmlspecialchars(($whatsAppHref ?? '#'), ENT_QUOTES, 'UTF-8') ?>" aria-label="WhatsApp Kontakt" target="_blank" rel="noopener noreferrer">w</a>
</body>
</html>
