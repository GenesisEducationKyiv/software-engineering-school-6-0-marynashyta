<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application;

use App\Modules\Subscription\Domain\Exception\AlreadySubscribedException;
use App\Modules\Subscription\Domain\Exception\TokenNotFoundException;
use App\Modules\Subscription\Domain\Exception\ValidationException;
use App\Modules\Subscription\Domain\Subscription;
use App\SharedKernel\Domain\HttpExceptionInterface;

interface SubscriptionServiceInterface
{
    /**
     * @throws ValidationException
     * @throws HttpExceptionInterface
     * @throws AlreadySubscribedException
     */
    public function subscribe(SubscribeRequest $request): void;

    /**
     * @throws TokenNotFoundException
     */
    public function confirm(string $token): void;

    /**
     * @throws TokenNotFoundException
     */
    public function unsubscribe(string $token): void;

    /**
     * @throws ValidationException
     * @return list<Subscription>
     */
    public function getSubscriptions(string $email): array;
}
