<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain;

use PHPMailer\PHPMailer\Exception as PHPMailerException;

interface ConfirmationMailerInterface
{
    /**
     * @throws PHPMailerException
     */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken
    ): void;
}
