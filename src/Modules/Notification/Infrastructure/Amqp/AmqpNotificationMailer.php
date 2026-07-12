<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Amqp;

use App\Modules\Notification\Application\Exception\NotificationDeliveryException;
use App\Modules\Notification\Application\NotificationMailerInterface;
use JsonException;
use PhpAmqpLib\Exception\AMQPExceptionInterface;

final class AmqpNotificationMailer implements NotificationMailerInterface
{
    public function __construct(private readonly AmqpPublisher $publisher)
    {
    }

    /**
     * @throws NotificationDeliveryException
     */
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {
        try {
            $body = json_encode([
                'type'              => 'send_notification',
                'email'             => $email,
                'repo'              => $repo,
                'tag'               => $tag,
                'unsubscribe_token' => $unsubscribeToken,
            ], JSON_THROW_ON_ERROR);

            $this->publisher->publish($body);
        } catch (JsonException | AMQPExceptionInterface $e) {
            throw new NotificationDeliveryException('Failed to publish release notification message: ' . $e->getMessage(), 0, $e);
        }
    }
}
