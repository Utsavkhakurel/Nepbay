<?php
// Creates a single PDO connection that every page shares via db().

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // throw errors instead of failing silently
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
        ]);
        // Keep MySQL's NOW() in the same timezone as PHP (Nepal is UTC+5:45)
        $pdo->exec("SET time_zone = '+05:45'");
    }

    return $pdo;
}
