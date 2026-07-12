<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Domain\Saga;

enum SagaState: string
{
    case Started             = 'started';
    case SubscriptionCreated = 'subscription_created';
    case Completed           = 'completed';
    case Compensating        = 'compensating';
    case Compensated         = 'compensated';
}
