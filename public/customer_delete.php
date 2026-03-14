<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Methode nicht erlaubt.';
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $repository->delete($id);
}

redirect('customers.php?status=deleted');
