<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application;

use App\Modules\Subscription\Domain\Exception\AlreadySubscribedException;
use App\Modules\Subscription\Domain\Exception\TokenNotFoundException;
use App\Modules\Subscription\Domain\Exception\ValidationException;
use App\Modules\Subscription\Domain\Subscription;
use App\Modules\GitHub\Domain\Exception\InvalidRepositoryFormatException;
use App\Modules\GitHub\Domain\Exception\RateLimitException;
use App\Modules\GitHub\Domain\Exception\RepositoryNotFoundException;

interface SubscriptionServiceInterface
{
    /**
     * @throws ValidationException
     * @throws InvalidRepositoryFormatException
     * @throws RepositoryNotFoundException
     * @throws RateLimitException
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
