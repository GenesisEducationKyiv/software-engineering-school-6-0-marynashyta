<?php

declare(strict_types=1);

namespace NotificationService;

use PHPMailer\PHPMailer\Exception as PHPMailerException;

interface MailerInterface
{
    /** @throws PHPMailerException */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void;

    /** @throws PHPMailerException */
    public function sendNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void;
}
