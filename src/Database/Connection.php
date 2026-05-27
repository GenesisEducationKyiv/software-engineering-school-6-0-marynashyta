<?php

declare(strict_types=1);

namespace App\Database;

use App\Infrastructure\Env;
use PDO;
use PDOException;

final class Connection
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = Env::string('DB_HOST', 'localhost');
            $port = Env::string('DB_PORT', '3306');
            $name = Env::string('DB_NAME', 'release_notifications');
            $user = Env::string('DB_USER', 'root');
            $pass = Env::string('DB_PASS', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }

    /**
     * Reset the singleton (used in tests to get a fresh connection).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
