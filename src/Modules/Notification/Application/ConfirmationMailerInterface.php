<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Modules\Notification\Application\Exception\NotificationDeliveryException;

interface ConfirmationMailerInterface
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
