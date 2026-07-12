<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Amqp;

use App\Modules\Notification\Application\ConfirmationMailerInterface;
use App\Modules\Notification\Application\Exception\NotificationDeliveryException;
use JsonException;
use PhpAmqpLib\Exception\AMQPExceptionInterface;

final class AmqpConfirmationMailer implements ConfirmationMailerInterface
{
    public function __construct(private readonly AmqpPublisher $publisher)
    {
    }

    /**
     * @throws NotificationDeliveryException
     */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {
        try {
            $body = json_encode([
                'type'              => 'send_confirmation',
                'email'             => $email,
                'repo'              => $repo,
                'confirm_token'     => $confirmToken,
                'unsubscribe_token' => $unsubscribeToken,
            ], JSON_THROW_ON_ERROR);

            $this->publisher->publish($body);
        } catch (JsonException | AMQPExceptionInterface $e) {
            throw new NotificationDeliveryException('Failed to publish confirmation message: ' . $e->getMessage(), 0, $e);
        }
    }
}
