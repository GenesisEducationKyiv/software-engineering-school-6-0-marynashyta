<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Subscription\Infrastructure\Persistence\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PdoTransactionManagerTest extends TestCase
{
    private PDO&MockObject $db;
    private PdoTransactionManager $transactions;

    #[Test]
    public function commitsAndReturnsTheOperationResultOnSuccess(): void
    {
        $this->db->expects($this->once())->method('beginTransaction');
        $this->db->expects($this->once())->method('commit');
        $this->db->expects($this->never())->method('rollBack');

        $result = $this->transactions->transactional(static fn (): string => 'ok');

        $this->assertSame('ok', $result);
    }

    #[Test]
    public function rollsBackAndRethrowsWhenTheOperationFails(): void
    {
        $this->db->expects($this->once())->method('beginTransaction');
        $this->db->expects($this->never())->method('commit');
        $this->db->expects($this->once())->method('rollBack');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->transactions->transactional(static function (): never {
            throw new \RuntimeException('boom');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->db           = $this->createMock(PDO::class);
        $this->transactions = new PdoTransactionManager($this->db);
    }
}
