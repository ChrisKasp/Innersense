<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$query = trim((string) ($_GET['q'] ?? ''));
$customers = $repository->all($query);
$status = (string) ($_GET['status'] ?? '');

$statusMessages = [
    'created' => 'Kunde wurde angelegt.',
    'updated' => 'Kunde wurde aktualisiert.',
    'deleted' => 'Kunde wurde gelöscht.',
];

$message = $statusMessages[$status] ?? '';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Innersense Kundenverwaltung</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<?php
$activePage = '';
require __DIR__ . '/partials/site_header.php';
?>

<main class="container">
    <header class="header">
        <h1>Innersense Kundenverwaltung</h1>
        <a class="button" href="customer_create.php">Neuen Kunden anlegen</a>
    </header>

    <form class="search" method="get">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Name, E-Mail oder Firma suchen">
        <button type="submit">Suchen</button>
    </form>

    <?php if ($message !== ''): ?>
        <p class="notice"><?= e($message) ?></p>
    <?php endif; ?>

    <section class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>E-Mail</th>
                    <th>Telefon</th>
                    <th>Firma</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customers === []): ?>
                    <tr>
                        <td colspan="5">Keine Kunden gefunden.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></td>
                        <td><?= e((string) $customer['email']) ?></td>
                        <td><?= e((string) $customer['phone']) ?></td>
                        <td><?= e((string) $customer['company']) ?></td>
                        <td class="actions">
                            <a href="customer_edit.php?id=<?= (int) $customer['id'] ?>">Bearbeiten</a>
                            <form method="post" action="customer_delete.php" onsubmit="return confirm('Kunde wirklich löschen?');">
                                <input type="hidden" name="id" value="<?= (int) $customer['id'] ?>">
                                <button type="submit">Löschen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
<?php require __DIR__ . '/partials/site_footer.php'; ?>
</body>
</html>
