<?php
declare(strict_types=1);

/**
 * Connexion PDO unique (singleton) à MySQL.
 * Les identifiants viennent des variables d'environnement (docker-compose.yml).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_NAME') ?: 'todo_app'
        );
        $pdo = new PDO($dsn, getenv('DB_USER') ?: 'root', getenv('DB_PASS') ?: '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées
        ]);
    }
    return $pdo;
}
