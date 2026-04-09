<?php
$activePage = $activePage ?? '';

require_once dirname(__DIR__, 2) . '/config/env.php';
loadEnv(dirname(__DIR__, 2) . '/config/.env');

$publicContactConfigFile = dirname(__DIR__, 2) . '/config/public_contact.php';
$publicContactSettings = [];
if (is_file($publicContactConfigFile) && is_readable($publicContactConfigFile)) {
    $loadedSettings = require $publicContactConfigFile;
    if (is_array($loadedSettings)) {
        $publicContactSettings = $loadedSettings;
    }
}

$whatsAppNumberRaw = (string) ($publicContactSettings['whatsapp_number'] ?? (getenv('WHATSAPP_NUMBER') ?: ''));
$whatsAppDigits = preg_replace('/\D+/', '', $whatsAppNumberRaw) ?? '';
$whatsAppHref = $whatsAppDigits !== '' ? 'https://wa.me/' . $whatsAppDigits : '#';

$facebookUrl = trim((string) ($publicContactSettings['facebook_url'] ?? (getenv('FACEBOOK_URL') ?: '')));
$facebookHref = $facebookUrl !== '' ? $facebookUrl : '#';

$instagramUrl = trim((string) ($publicContactSettings['instagram_url'] ?? (getenv('INSTAGRAM_URL') ?: '')));
$instagramHref = $instagramUrl !== '' ? $instagramUrl : '#';
?>
<header class="topbar">
    <a class="brand" href="index.php" aria-label="Innersense Startseite">
        <img src="assets/icons/Innersense_Logo.avif" alt="Innersense Logo">
    </a>

    <nav class="main-nav" aria-label="Hauptnavigation">
        <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="index.php">Home</a>
        <a class="<?= $activePage === 'kontakt' ? 'active' : '' ?>" href="kontakt.php">Kontakt</a>
        <a class="<?= $activePage === 'preise' ? 'active' : '' ?>" href="preise.php">Preise</a>
    </nav>

    <div class="social-links" aria-label="Social Media">
        <a href="<?= htmlspecialchars($facebookHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="Facebook" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/Facebook.webp" alt="" loading="lazy" decoding="async">
        </a>
        <a href="<?= htmlspecialchars($instagramHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="Instagram" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/Instagram.webp" alt="" loading="lazy" decoding="async">
        </a>
        <a href="<?= htmlspecialchars($whatsAppHref, ENT_QUOTES, 'UTF-8') ?>" aria-label="WhatsApp" target="_blank" rel="noopener noreferrer">
            <img src="assets/icons/WhatsApp.webp" alt="" loading="lazy" decoding="async">
        </a>
    </div>
</header>
