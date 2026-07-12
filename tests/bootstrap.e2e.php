<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$_ENV['APP_URL'] = (string) (getenv('APP_URL') ?: 'http://localhost:8080');
$_ENV['API_KEY'] = (string) (getenv('API_KEY') ?: '');
