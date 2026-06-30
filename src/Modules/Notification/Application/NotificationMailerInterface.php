<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Modules\Notification\Application\Exception\NotificationDeliveryException;

interface NotificationMailerInterface
{
    /**
     * @throws NotificationDeliveryException
     */
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken
    ): void;
}
