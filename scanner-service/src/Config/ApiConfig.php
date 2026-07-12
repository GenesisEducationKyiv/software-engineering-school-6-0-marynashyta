<?php

declare(strict_types=1);

namespace ScannerService\Config;

final readonly class ApiConfig
{
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
    ) {
    }
}
