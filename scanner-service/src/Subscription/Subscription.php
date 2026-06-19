<?php

declare(strict_types=1);

namespace ScannerService\Subscription;

final readonly class Subscription
{
    public function __construct(
        public int $id,
        public string $email,
        public string $repo,
        public ?string $lastSeenTag,
        public string $unsubscribeToken,
    ) {
    }
}
