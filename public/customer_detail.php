<?php
declare(strict_types=1);

session_start();

header('Content-Type: text/html; charset=UTF-8');

require_once dirname(__DIR__) . '/config/database.php';

$idleTimeoutSeconds = 600;
$customerId = max(0, (int) ($_GET['id'] ?? $_POST['customer_id'] ?? 0));
$csrfToken = (string) ($_SESSION['verwaltung_csrf'] ?? '');
$detailNotice = '';
$detailError = '';
$customerDetail = null;
$recordDeleted = false;

if (!isset($_SESSION['verwaltung_authenticated']) || $_SESSION['verwaltung_authenticated'] !== true) {
    header('Location: verwaltung.php');
    exit;
}

$lastActivity = (int) ($_SESSION['verwaltung_last_activity'] ?? 0);
if ($lastActivity > 0 && (time() - $lastActivity) > $idleTimeoutSeconds) {
    $_SESSION['verwaltung_authenticated'] = false;
    unset($_SESSION['verwaltung_last_activity']);
    header('Location: verwaltung.php');
    exit;
}
$_SESSION['verwaltung_last_activity'] = time();

if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(24));
    $_SESSION['verwaltung_csrf'] = $csrfToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'update_customer') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $customerTyp = trim((string) ($_POST['customer_typ'] ?? 'Privatperson'));
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $streetAddress = trim((string) ($_POST['street_address'] ?? ''));
    $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($customerId <= 0) {
        $detailError = 'Ungültige Kundenauswahl.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $detailError = 'Bitte eine gültige E-Mail-Adresse eingeben oder leer lassen.';
    } elseif (!in_array($customerTyp, ['Firma', 'Privatperson'], true)) {
        $detailError = 'Bitte einen gültigen Kundentyp auswählen.';
    } elseif ($customerTyp === 'Firma' && $companyName === '') {
        $detailError = 'Bitte einen Firmennamen angeben.';
    } else {
        try {
            $stmt = db()->prepare(
                'UPDATE customer
                 SET first_name = :first_name,
                     last_name = :last_name,
                     email = :email,
                     phone = :phone,
                     customer_typ = :customer_typ,
                     company_name = :company_name,
                     street_address = :street_address,
                     postal_code = :postal_code,
                     city = :city
                 WHERE id = :id'
            );
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':email' => $email,
                ':phone' => $phone,
                ':customer_typ' => $customerTyp,
                ':company_name' => $companyName,
                ':street_address' => $streetAddress,
                ':postal_code' => $postalCode,
                ':city' => $city,
                ':id' => $customerId,
            ]);

            $detailNotice = 'Kundendaten wurden erfolgreich gespeichert.';
        } catch (Throwable $exception) {
            $detailError = 'Kundendaten konnten nicht gespeichert werden.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['auth_action'] ?? '') === 'delete_customer') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $postedToken)) {
        $detailError = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif ($customerId <= 0) {
        $detailError = 'Ungültige Kundenauswahl.';
    } else {
        try {
            $stmt = db()->prepare('DELETE FROM customer WHERE id = :id');
            $stmt->execute([':id' => $customerId]);

            if ($stmt->rowCount() > 0) {
                $detailNotice = 'Kunde wurde erfolgreich gelöscht.';
                $recordDeleted = true;
                $customerDetail = null;
            } else {
                $detailError = 'Kunde konnte nicht gelöscht werden.';
            }
        } catch (Throwable $exception) {
            if (isReferenceDeleteError($exception)) {
                $detailError = 'Kunde kann nicht gelöscht werden, da Referenzen in anderen Tabellen bestehen.';
            } else {
                $detailError = 'Kunde konnte nicht gelöscht werden.';
            }
        }
    }
}

if (!$recordDeleted && $customerId > 0) {
    try {
        $stmt = db()->prepare(
            'SELECT id, first_name, last_name, email, phone, customer_typ, company_name, street_address, postal_code, city
             FROM customer
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $customerId]);
        $row = $stmt->fetch();

        if (is_array($row)) {
            $customerDetail = $row;
        } else {
            $detailError = 'Der ausgewählte Kunde wurde nicht gefunden.';
        }
    } catch (Throwable $exception) {
        $detailError = 'Kundendaten konnten nicht geladen werden.';
    }
} elseif (!$recordDeleted) {
    $detailError = 'Bitte wähle zuerst einen Kunden in der Kundenliste aus.';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isReferenceDeleteError(Throwable $exception): bool
{
    if ($exception instanceof PDOException) {
        $sqlState = (string) $exception->getCode();
        if ($sqlState === '23000') {
            return true;
        }
    }

    return stripos($exception->getMessage(), 'foreign key') !== false;
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense | Kunden-Details</title>
    <link rel="stylesheet" href="assets/verwaltung.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Manrope:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body>
<main class="detail-page">
    <section class="detail-header">
        <a class="action-icon" href="verwaltung.php?view=customers" aria-label="Zurück zur Kundenliste">&#8592;</a>
        <div>
            <h1>Kunden-Detailansicht</h1>
            <p>Hier können alle Kundendaten gepflegt und gespeichert werden.</p>
        </div>
    </section>

    <article class="customer-detail-card">
        <?php if ($detailNotice !== ''): ?>
            <p class="form-notice"><?= e($detailNotice) ?></p>
        <?php endif; ?>
        <?php if ($detailError !== ''): ?>
            <p class="form-error"><?= e($detailError) ?></p>
        <?php endif; ?>

        <?php if (is_array($customerDetail)): ?>
            <form id="customer-detail-form" method="post" action="customer_detail.php?id=<?= e((string) ($customerDetail['id'] ?? 0)) ?>" class="customer-detail-form">
                <input type="hidden" name="auth_action" value="update_customer">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="customer_id" value="<?= e((string) ($customerDetail['id'] ?? 0)) ?>">

                <label for="customer_typ">Kundentyp *</label>
                <select id="customer_typ" name="customer_typ" required>
                    <?php $selectedCustomerType = (string) ($customerDetail['customer_typ'] ?? 'Privatperson'); ?>
                    <option value="Privatperson"<?= $selectedCustomerType === 'Privatperson' ? ' selected' : '' ?>>Privatperson</option>
                    <option value="Firma"<?= $selectedCustomerType === 'Firma' ? ' selected' : '' ?>>Firma</option>
                </select>

                <label for="customer_company_name">Firmenname</label>
                <input id="customer_company_name" type="text" name="company_name" value="<?= e((string) ($customerDetail['company_name'] ?? '')) ?>">

                <label for="customer_first_name">Vorname</label>
                <input id="customer_first_name" type="text" name="first_name" value="<?= e((string) ($customerDetail['first_name'] ?? '')) ?>">

                <label for="customer_last_name">Nachname</label>
                <input id="customer_last_name" type="text" name="last_name" value="<?= e((string) ($customerDetail['last_name'] ?? '')) ?>">

                <label for="customer_email">E-Mail</label>
                <input id="customer_email" type="email" name="email" value="<?= e((string) ($customerDetail['email'] ?? '')) ?>">

                <label for="customer_phone">Telefon</label>
                <input id="customer_phone" type="text" name="phone" value="<?= e((string) ($customerDetail['phone'] ?? '')) ?>">

                <label for="customer_street_address">Straße und Hausnummer</label>
                <input id="customer_street_address" type="text" name="street_address" value="<?= e((string) ($customerDetail['street_address'] ?? '')) ?>">

                <label for="customer_postal_code">PLZ</label>
                <input id="customer_postal_code" type="text" name="postal_code" value="<?= e((string) ($customerDetail['postal_code'] ?? '')) ?>">

                <label for="customer_city">Ort</label>
                <input id="customer_city" type="text" name="city" value="<?= e((string) ($customerDetail['city'] ?? '')) ?>">

            </form>

            <div class="detail-form-actions">
                <button type="submit" form="customer-detail-form" class="primary-button">Kundendaten speichern</button>

                <form method="post" action="customer_detail.php?id=<?= e((string) ($customerDetail['id'] ?? 0)) ?>" class="detail-delete-form" onsubmit="return confirm('Kunde wirklich löschen?');">
                    <input type="hidden" name="auth_action" value="delete_customer">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="customer_id" value="<?= e((string) ($customerDetail['id'] ?? 0)) ?>">
                    <button type="submit" class="danger-button">Löschen</button>
                </form>
            </div>
        <?php endif; ?>
    </article>
</main>
<script>
(() => {
    const customerTypeField = document.getElementById('customer_typ');
    const companyNameField = document.getElementById('customer_company_name');

    if (!(customerTypeField instanceof HTMLSelectElement) || !(companyNameField instanceof HTMLInputElement)) {
        return;
    }

    const syncCompanyFieldState = () => {
        const isCompany = customerTypeField.value === 'Firma';
        companyNameField.disabled = !isCompany;
        if (!isCompany) {
            companyNameField.value = '';
            companyNameField.placeholder = 'Nur bei Kundentyp Firma';
        } else {
            companyNameField.placeholder = '';
        }
    };

    customerTypeField.addEventListener('change', syncCompanyFieldState);
    syncCompanyFieldState();
})();
</script>
</body>
</html>
