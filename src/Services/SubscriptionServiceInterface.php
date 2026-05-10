<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AlreadySubscribedException;
use App\Exceptions\InvalidRepositoryFormatException;
use App\Exceptions\RateLimitException;
use App\Exceptions\RepositoryNotFoundException;
use App\Exceptions\TokenNotFoundException;
use App\Exceptions\ValidationException;

interface SubscriptionServiceInterface
{
    /**
     * @throws ValidationException
     * @throws InvalidRepositoryFormatException
     * @throws RepositoryNotFoundException
     * @throws RateLimitException
     * @throws AlreadySubscribedException
     */
    public function subscribe(string $email, string $repo): void;

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
     * @return list<array{email: string, repo: string, confirmed: bool, last_seen_tag: string|null}>
     */
    public function getSubscriptions(string $email): array;
}
