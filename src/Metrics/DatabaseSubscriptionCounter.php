<?php

declare(strict_types=1);

namespace App\Metrics;

use PDO;

final class DatabaseSubscriptionCounter implements ActiveSubscriptionCounterInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function countActive(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM subscriptions WHERE confirmed = 1');
        return $stmt !== false ? (int) $stmt->fetchColumn() : 0;
    }
}
