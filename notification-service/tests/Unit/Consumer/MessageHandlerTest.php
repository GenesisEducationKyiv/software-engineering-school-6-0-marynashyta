<?php

declare(strict_types=1);

namespace Tests\Unit\Consumer;

use NotificationService\Consumer\MessageHandler;
use NotificationService\MailerInterface;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class MessageHandlerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private MessageHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer  = $this->createMock(MailerInterface::class);
        $this->handler = new MessageHandler($this->mailer);
    }

    // ── send_notification ────────────────────────────────────────────────────────

    #[Test]
    public function dispatchesSendNotification(): void
    {
        $this->mailer
            ->expects($this->once())
            ->method('sendNotification')
            ->with('user@example.com', 'owner/repo', 'v1.2.3', 'tok123');

        $this->handler->handle($this->notificationJson());
    }

    #[Test]
    public function throwsWhenTagMissingForNotification(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tag/');

        $this->handler->handle(json_encode([
            'type'              => 'send_notification',
            'email'             => 'user@example.com',
            'repo'              => 'owner/repo',
            'unsubscribe_token' => 'tok123',
        ], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function throwsWhenEmailMissingForNotification(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/email/');

        $this->handler->handle(json_encode([
            'type'              => 'send_notification',
            'repo'              => 'owner/repo',
            'tag'               => 'v1.0.0',
            'unsubscribe_token' => 'tok123',
        ], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function throwsWhenUnsubscribeTokenMissingForNotification(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unsubscribe_token/');

        $this->handler->handle(json_encode([
            'type'  => 'send_notification',
            'email' => 'user@example.com',
            'repo'  => 'owner/repo',
            'tag'   => 'v1.0.0',
        ], JSON_THROW_ON_ERROR));
    }

    // ── send_confirmation ────────────────────────────────────────────────────────

    #[Test]
    public function dispatchesSendConfirmation(): void
    {
        $this->mailer
            ->expects($this->once())
            ->method('sendConfirmation')
            ->with('user@example.com', 'owner/repo', 'ctok', 'utok');

        $this->handler->handle($this->confirmationJson());
    }

    #[Test]
    public function throwsWhenConfirmTokenMissingForConfirmation(): void
    {
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/confirm_token/');

        $this->handler->handle(json_encode([
            'type'              => 'send_confirmation',
            'email'             => 'user@example.com',
            'repo'              => 'owner/repo',
            'unsubscribe_token' => 'utok',
        ], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function throwsWhenEmailMissingForConfirmation(): void
    {
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/email/');

        $this->handler->handle(json_encode([
            'type'              => 'send_confirmation',
            'repo'              => 'owner/repo',
            'confirm_token'     => 'ctok',
            'unsubscribe_token' => 'utok',
        ], JSON_THROW_ON_ERROR));
    }

    // ── malformed / unknown payloads ─────────────────────────────────────────────

    #[Test]
    public function throwsOnUnknownType(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown message type/');

        $this->handler->handle(json_encode([
            'type'  => 'delete_everything',
            'email' => 'user@example.com',
        ], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function throwsWhenTypeMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/type/');

        $this->handler->handle(json_encode(['email' => 'user@example.com'], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function throwsWhenTypeIsEmpty(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/type/');

        $this->handler->handle(json_encode(['type' => ''], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function throwsOnInvalidJson(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\JsonException::class);

        $this->handler->handle('{not valid json');
    }

    #[Test]
    public function throwsWhenPayloadIsJsonScalar(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/JSON object/');

        $this->handler->handle('"not-an-object"');
    }

    #[Test]
    public function throwsWhenPayloadIsJsonNull(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');
        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/JSON object/');

        $this->handler->handle('null');
    }

    // ── mailer exception propagation ─────────────────────────────────────────────

    #[Test]
    public function propagatesMailerExceptionForNotification(): void
    {
        $this->mailer
            ->method('sendNotification')
            ->willThrowException(new PHPMailerException('SMTP error'));

        $this->expectException(PHPMailerException::class);

        $this->handler->handle($this->notificationJson());
    }

    #[Test]
    public function propagatesMailerExceptionForConfirmation(): void
    {
        $this->mailer
            ->method('sendConfirmation')
            ->willThrowException(new PHPMailerException('SMTP error'));

        $this->expectException(PHPMailerException::class);

        $this->handler->handle($this->confirmationJson());
    }

    // ── helpers ──────────────────────────────────────────────────────────────────

    private function notificationJson(): string
    {
        return json_encode([
            'type'              => 'send_notification',
            'email'             => 'user@example.com',
            'repo'              => 'owner/repo',
            'tag'               => 'v1.2.3',
            'unsubscribe_token' => 'tok123',
        ], JSON_THROW_ON_ERROR);
    }

    private function confirmationJson(): string
    {
        return json_encode([
            'type'              => 'send_confirmation',
            'email'             => 'user@example.com',
            'repo'              => 'owner/repo',
            'confirm_token'     => 'ctok',
            'unsubscribe_token' => 'utok',
        ], JSON_THROW_ON_ERROR);
    }
}
