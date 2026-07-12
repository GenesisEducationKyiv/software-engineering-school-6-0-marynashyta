<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Amqp;

use App\Modules\Notification\Application\NotificationMailerInterface;

final class AmqpNotificationMailer implements NotificationMailerInterface
{
    public function __construct(private readonly AmqpPublisher $publisher)
    {
    }

    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {
        $body = json_encode([
            'type'              => 'send_notification',
            'email'             => $email,
            'repo'              => $repo,
            'tag'               => $tag,
            'unsubscribe_token' => $unsubscribeToken,
        ], JSON_THROW_ON_ERROR);

        $this->publisher->publish($body);
    }
}
