<?php
$whatsAppHref = $whatsAppHref ?? '#';
$facebookHref = $facebookHref ?? '#';
$instagramHref = $instagramHref ?? '#';
?>
<footer class="page-footer">
    <div class="container footer-inner">
        <div class="footer-social">
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

        <nav class="footer-links" aria-label="Rechtliches">
            <a href="impressum.php">Impressum</a>
            <a href="datenschutz.php">Datenschutz</a>
            <a href="#">Cookie-Einstellungen</a>
            <a href="widerrufsbelehrung.php">Widerrufsbelehrung</a>
            <a href="agb.php">AGB</a>
        </nav>
    </div>
</footer>
