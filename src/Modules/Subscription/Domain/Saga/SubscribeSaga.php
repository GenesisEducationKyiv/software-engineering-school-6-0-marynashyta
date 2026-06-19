<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Saga;

final readonly class SubscribeSaga
{
    public function __construct(
        public string $id,
        public string $email,
        public string $repo,
        public SagaState $state,
        public ?string $confirmToken,
        public ?string $unsubscribeToken,
        public ?string $compensationReason,
    ) {
    }

    public static function start(string $email, string $repo): self
    {
        return new self(
            id:                 bin2hex(random_bytes(16)),
            email:              $email,
            repo:               $repo,
            state:              SagaState::Started,
            confirmToken:       null,
            unsubscribeToken:   null,
            compensationReason: null,
        );
    }

    public function withSubscriptionCreated(string $confirmToken, string $unsubscribeToken): self
    {
        return new self(
            id:                 $this->id,
            email:              $this->email,
            repo:               $this->repo,
            state:              SagaState::SubscriptionCreated,
            confirmToken:       $confirmToken,
            unsubscribeToken:   $unsubscribeToken,
            compensationReason: null,
        );
    }

    public function withCompleted(): self
    {
        return new self(
            id:                 $this->id,
            email:              $this->email,
            repo:               $this->repo,
            state:              SagaState::Completed,
            confirmToken:       $this->confirmToken,
            unsubscribeToken:   $this->unsubscribeToken,
            compensationReason: null,
        );
    }

    public function withCompensating(): self
    {
        return new self(
            id:                 $this->id,
            email:              $this->email,
            repo:               $this->repo,
            state:              SagaState::Compensating,
            confirmToken:       $this->confirmToken,
            unsubscribeToken:   $this->unsubscribeToken,
            compensationReason: null,
        );
    }

    public function withCompensated(string $reason): self
    {
        return new self(
            id:                 $this->id,
            email:              $this->email,
            repo:               $this->repo,
            state:              SagaState::Compensated,
            confirmToken:       $this->confirmToken,
            unsubscribeToken:   $this->unsubscribeToken,
            compensationReason: $reason,
        );
    }
}
