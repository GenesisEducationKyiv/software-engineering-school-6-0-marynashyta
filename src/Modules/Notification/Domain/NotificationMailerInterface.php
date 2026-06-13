<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain;

use PHPMailer\PHPMailer\Exception as PHPMailerException;

interface NotificationMailerInterface
{
    /**
     * @throws PHPMailerException
     */
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken
    ): void;
}
