#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\Database\Connection;
use App\SharedKernel\Infrastructure\Database\Migrator;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "Running database migrations...\n";

try {
    $pdo      = Connection::getInstance();
    $migrator = new Migrator($pdo);
    $migrator->run();
    echo "All migrations completed successfully.\n";
    exit(0);
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
