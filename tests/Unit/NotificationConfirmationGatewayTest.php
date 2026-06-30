<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Notification\Application\ConfirmationMailerInterface;
use App\Modules\Subscription\Infrastructure\Acl\NotificationConfirmationGateway;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NotificationConfirmationGatewayTest extends TestCase
{
    #[Test]
    public function sendConfirmationDelegatesToConfirmationMailer(): void
    {
        /** @var ConfirmationMailerInterface&MockObject $mailer */
        $mailer = $this->createMock(ConfirmationMailerInterface::class);
        $gateway = new NotificationConfirmationGateway($mailer);

        $mailer->expects($this->once())
            ->method('sendConfirmation')
            ->with('user@example.com', 'owner/repo', 'confirm-tok', 'unsub-tok');

        $gateway->sendConfirmation('user@example.com', 'owner/repo', 'confirm-tok', 'unsub-tok');
    }
}
