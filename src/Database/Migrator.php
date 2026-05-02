<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

class Migrator
{
    public function __construct(private PDO $db)
    {
    }

    public function run(): void
    {
        $migrationsPath = dirname(__DIR__, 2) . '/migrations';

        if (!is_dir($migrationsPath)) {
            throw new RuntimeException("Migrations directory not found: {$migrationsPath}");
        }

        $files = glob($migrationsPath . '/*.sql');

        if ($files === false || count($files) === 0) {
            echo "No migration files found.\n";
            return;
        }

        sort($files);

        foreach ($files as $file) {
            $filename = basename($file);
            echo "Running migration: {$filename}\n";

            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new RuntimeException("Could not read migration file: {$file}");
            }

            $this->db->exec($sql);

            echo "Migration {$filename} completed.\n";
        }
    }
}
