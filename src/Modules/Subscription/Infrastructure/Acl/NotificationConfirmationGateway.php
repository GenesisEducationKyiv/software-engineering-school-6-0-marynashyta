<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Infrastructure\Acl;

use App\Modules\Notification\Application\ConfirmationMailerInterface;
use App\Modules\Subscription\Application\Acl\ConfirmationGatewayInterface;

final class NotificationConfirmationGateway implements ConfirmationGatewayInterface
{
    public function __construct(private readonly ConfirmationMailerInterface $mailer)
    {
    }

    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken
    ): void {
        $this->mailer->sendConfirmation($email, $repo, $confirmToken, $unsubscribeToken);
    }
}
