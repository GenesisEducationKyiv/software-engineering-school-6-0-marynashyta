<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Database;

use PDO;

final class PdoTransactionManager implements TransactionManagerInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function transactional(callable $operation): mixed
    {
        $this->db->beginTransaction();

        try {
            $result = $operation();
            $this->db->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();

            throw $e;
        }
    }
}
