<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$customer = $repository->findById($id);

if ($id <= 0 || $customer === null) {
    http_response_code(404);
    echo 'Kunde nicht gefunden.';
    exit;
}

$errors = [];
$formData = [
    'first_name' => (string) $customer['first_name'],
    'last_name' => (string) $customer['last_name'],
    'email' => (string) $customer['email'],
    'phone' => (string) $customer['phone'],
    'company' => (string) $customer['company'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $field => $_unused) {
        $formData[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    if ($formData['first_name'] === '') {
        $errors[] = 'Vorname ist erforderlich.';
    }
    if ($formData['last_name'] === '') {
        $errors[] = 'Nachname ist erforderlich.';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte eine gueltige E-Mail angeben.';
    }

    if ($errors === []) {
        try {
            $repository->update($id, $formData);
            redirect('customers.php?status=updated');
        } catch (PDOException $exception) {
            $errors[] = 'Aenderungen konnten nicht gespeichert werden.';
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kunde bearbeiten</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<?php
$activePage = '';
require __DIR__ . '/partials/site_header.php';
?>

<main class="container narrow">
    <h1>Kunde bearbeiten</h1>
    <p><a href="customers.php">Zurück zur Übersicht</a></p>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="form-grid">
        <input type="hidden" name="id" value="<?= $id ?>">
        <label>Vorname<input type="text" name="first_name" value="<?= e($formData['first_name']) ?>" required></label>
        <label>Nachname<input type="text" name="last_name" value="<?= e($formData['last_name']) ?>" required></label>
        <label>E-Mail<input type="email" name="email" value="<?= e($formData['email']) ?>" required></label>
        <label>Telefon<input type="text" name="phone" value="<?= e($formData['phone']) ?>"></label>
        <label>Firma<input type="text" name="company" value="<?= e($formData['company']) ?>"></label>
        <button type="submit">Aenderungen speichern</button>
    </form>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
