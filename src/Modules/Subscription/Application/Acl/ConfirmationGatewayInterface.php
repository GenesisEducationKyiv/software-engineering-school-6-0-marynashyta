<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application\Acl;

use App\Modules\Notification\Application\Exception\NotificationDeliveryException;

interface ConfirmationGatewayInterface
{
    /**
     * @throws NotificationDeliveryException
     */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken
    ): void;
}
