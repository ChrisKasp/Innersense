<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/CustomerRepository.php';
require_once dirname(__DIR__) . '/src/helpers.php';

$repository = new CustomerRepository(db());
