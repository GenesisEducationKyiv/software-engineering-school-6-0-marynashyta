<?php

declare(strict_types=1);

namespace NotificationService;

final class NullMailer implements MailerInterface
{
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {}

    public function sendNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {}
}
