<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Impressum</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/impressum.css">
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
<main class="imprint-page" aria-label="Impressum">
    <section class="imprint-card">
        <h1>Impressum</h1>

        <p class="imprint-company">Mobile Fahrzeugaufbereitung InnerSense</p>

        <p>
            Gerome Selle<br>
            Büsdorferstr. 5A<br>
            50129 Bergheim<br>
            Deutschland
        </p>

        <p>
            Telefon: <a href="tel:+491638116597">+49 163 811 65 97</a><br>
            E-Mail: <a href="mailto:aufbereitung.innersense@gmail.com">aufbereitung.innersense@gmail.com</a><br>
            Website: <a href="https://www.aufbereitung-innersense.de" target="_blank" rel="noopener noreferrer">www.aufbereitung-innersense.de</a>
        </p>
    </section>
</main>
</body>
</html>
