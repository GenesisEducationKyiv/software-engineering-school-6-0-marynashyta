<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['API_BASE_URL'] = (string) (getenv('API_BASE_URL') ?: 'http://localhost:8080');
$_ENV['API_KEY']      = (string) (getenv('API_KEY')      ?: '');

$_ENV['DB_HOST'] = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$_ENV['DB_PORT'] = (string) (getenv('DB_PORT') ?: '3306');
$_ENV['DB_NAME'] = (string) (getenv('DB_NAME') ?: 'release_notifications');
$_ENV['DB_USER'] = (string) (getenv('DB_USER') ?: 'app');
$_ENV['DB_PASS'] = (string) (getenv('DB_PASS') ?: 'secret');
