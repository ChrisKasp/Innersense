<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Returns a shared PDO instance for MariaDB.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    loadEnv(dirname(__DIR__) . '/config/.env');

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'innersense';
    $user = getenv('DB_USER') ?: 'innersense_user';
    $pass = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: 'innersense_pass');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}
