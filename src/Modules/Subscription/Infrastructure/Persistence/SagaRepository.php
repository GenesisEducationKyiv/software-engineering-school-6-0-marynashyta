<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Infrastructure\Persistence;

use App\Modules\Subscription\Domain\Saga\SagaRepositoryInterface;
use App\Modules\Subscription\Domain\Saga\SagaState;
use App\Modules\Subscription\Domain\Saga\SubscribeSaga;
use PDO;

final class SagaRepository implements SagaRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function save(SubscribeSaga $saga): void
    {
        $this->db->prepare(
            'INSERT INTO subscription_sagas
                (id, email, repo, state, confirm_token, unsubscribe_token, compensation_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                state               = VALUES(state),
                confirm_token       = VALUES(confirm_token),
                unsubscribe_token   = VALUES(unsubscribe_token),
                compensation_reason = VALUES(compensation_reason),
                updated_at          = CURRENT_TIMESTAMP'
        )->execute([
            $saga->id,
            $saga->email,
            $saga->repo,
            $saga->state->value,
            $saga->confirmToken,
            $saga->unsubscribeToken,
            $saga->compensationReason,
        ]);
    }

    public function findById(string $id): ?SubscribeSaga
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, repo, state, confirm_token, unsubscribe_token, compensation_reason
             FROM subscription_sagas WHERE id = ?'
        );
        $stmt->execute([$id]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): SubscribeSaga
    {
        return new SubscribeSaga(
            id:                 is_string($row['id']) ? $row['id'] : '',
            email:              is_string($row['email']) ? $row['email'] : '',
            repo:               is_string($row['repo']) ? $row['repo'] : '',
            state:              SagaState::from(is_string($row['state']) ? $row['state'] : ''),
            confirmToken:       is_string($row['confirm_token']) ? $row['confirm_token'] : null,
            unsubscribeToken:   is_string($row['unsubscribe_token']) ? $row['unsubscribe_token'] : null,
            compensationReason: is_string($row['compensation_reason']) ? $row['compensation_reason'] : null,
        );
    }
}
