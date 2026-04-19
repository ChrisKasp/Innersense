<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/helpers.php';

$id = max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if ($id > 0) {
    redirect('customer_detail.php?id=' . $id);
}

redirect('verwaltung.php?view=customers');
