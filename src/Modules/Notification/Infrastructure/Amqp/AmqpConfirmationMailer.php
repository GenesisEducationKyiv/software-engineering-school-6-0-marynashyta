<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Amqp;

use App\Modules\Notification\Domain\ConfirmationMailerInterface;

final class AmqpConfirmationMailer implements ConfirmationMailerInterface
{
    public function __construct(private readonly AmqpPublisher $publisher)
    {
    }

    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {
        $body = json_encode([
            'type'              => 'send_confirmation',
            'email'             => $email,
            'repo'              => $repo,
            'confirm_token'     => $confirmToken,
            'unsubscribe_token' => $unsubscribeToken,
        ], JSON_THROW_ON_ERROR);

        $this->publisher->publish($body);
    }
}
