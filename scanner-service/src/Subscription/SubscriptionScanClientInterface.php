<?php

declare(strict_types=1);

namespace ScannerService\Subscription;

interface SubscriptionScanClientInterface
{
    /** @return list<Subscription> */
    public function findAllConfirmed(): array;

    public function updateLastSeenTag(int $id, string $tag): void;
}
