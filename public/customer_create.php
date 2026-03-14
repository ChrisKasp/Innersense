<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$errors = [];
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'company' => '',
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
            $repository->create($formData);
            redirect('customers.php?status=created');
        } catch (PDOException $exception) {
            $errors[] = 'Kunde konnte nicht gespeichert werden (E-Mail eventuell bereits vorhanden).';
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kunde anlegen</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<main class="container narrow">
    <h1>Kunde anlegen</h1>
    <p><a href="customers.php">Zurueck zur Uebersicht</a></p>

    <?php foreach ($errors as $error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endforeach; ?>

    <form method="post" class="form-grid">
        <label>Vorname<input type="text" name="first_name" value="<?= e($formData['first_name']) ?>" required></label>
        <label>Nachname<input type="text" name="last_name" value="<?= e($formData['last_name']) ?>" required></label>
        <label>E-Mail<input type="email" name="email" value="<?= e($formData['email']) ?>" required></label>
        <label>Telefon<input type="text" name="phone" value="<?= e($formData['phone']) ?>"></label>
        <label>Firma<input type="text" name="company" value="<?= e($formData['company']) ?>"></label>
        <button type="submit">Speichern</button>
    </form>
</main>
</body>
</html>
