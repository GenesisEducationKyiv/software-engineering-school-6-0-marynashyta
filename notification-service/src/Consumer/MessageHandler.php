<?php

declare(strict_types=1);

namespace NotificationService\Consumer;

use NotificationService\MailerInterface;

final class MessageHandler
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    /** @throws \InvalidArgumentException|\JsonException */
    public function handle(string $rawBody): void
    {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Payload must be a JSON object');
        }

        $this->dispatch($payload);
    }

    /** @param array<mixed> $payload */
    private function dispatch(array $payload): void
    {
        $type = $this->requireString($payload, 'type');

        match ($type) {
            'send_confirmation' => $this->mailer->sendConfirmation(
                email:            $this->requireString($payload, 'email'),
                repo:             $this->requireString($payload, 'repo'),
                confirmToken:     $this->requireString($payload, 'confirm_token'),
                unsubscribeToken: $this->requireString($payload, 'unsubscribe_token'),
            ),
            'send_notification' => $this->mailer->sendNotification(
                email:            $this->requireString($payload, 'email'),
                repo:             $this->requireString($payload, 'repo'),
                tag:              $this->requireString($payload, 'tag'),
                unsubscribeToken: $this->requireString($payload, 'unsubscribe_token'),
            ),
            default => throw new \InvalidArgumentException("Unknown message type: {$type}"),
        };
    }

    /** @param array<mixed> $payload */
    private function requireString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Missing or empty required field: {$key}");
        }
        return $value;
    }
}
