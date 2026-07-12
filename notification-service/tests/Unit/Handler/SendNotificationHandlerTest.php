<?php

declare(strict_types=1);

namespace Tests\Unit\Handler;

use NotificationService\Handler\SendNotificationHandler;
use NotificationService\MailerInterface;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

final class SendNotificationHandlerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private SendNotificationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer  = $this->createMock(MailerInterface::class);
        $this->handler = new SendNotificationHandler($this->mailer);
    }

    #[Test]
    public function sendNotificationReturns204WhenMailerSucceeds(): void
    {
        $this->mailer
            ->expects($this->once())
            ->method('sendNotification')
            ->with('user@example.com', 'owner/repo', 'v1.2.3', str_repeat('0', 64));

        $res = ($this->handler)(
            $this->makeRequest([
                'email'             => 'user@example.com',
                'repo'              => 'owner/repo',
                'tag'               => 'v1.2.3',
                'unsubscribe_token' => str_repeat('0', 64),
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(204, $res->getStatusCode());
    }

    #[Test]
    public function sendNotificationReturns400WhenTagMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');

        $res = ($this->handler)(
            $this->makeRequest([
                'email'             => 'user@example.com',
                'repo'              => 'owner/repo',
                'unsubscribe_token' => str_repeat('0', 64),
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(400, $res->getStatusCode());
    }

    #[Test]
    public function sendNotificationReturns400WhenAllFieldsMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');

        $res = ($this->handler)(
            $this->makeRequest([]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(400, $res->getStatusCode());
    }

    #[Test]
    public function sendNotificationReturns400WhenUnsubscribeTokenMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendNotification');

        $res = ($this->handler)(
            $this->makeRequest([
                'email' => 'user@example.com',
                'repo'  => 'owner/repo',
                'tag'   => 'v1.0.0',
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(400, $res->getStatusCode());
    }

    #[Test]
    public function sendNotificationReturns500WhenMailerThrows(): void
    {
        $this->mailer
            ->method('sendNotification')
            ->willThrowException(new PHPMailerException('Connection refused'));

        $res = ($this->handler)(
            $this->makeRequest([
                'email'             => 'user@example.com',
                'repo'              => 'owner/repo',
                'tag'               => 'v1.0.0',
                'unsubscribe_token' => str_repeat('0', 64),
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(500, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertArrayHasKey('message', (array) $body);
    }

    /** @param array<string, string> $body */
    private function makeRequest(array $body): \Psr\Http\Message\ServerRequestInterface
    {
        $stream = (new StreamFactory())->createStream((string) json_encode($body));

        return (new RequestFactory())
            ->createRequest('POST', '/send-notification')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream)
            ->withParsedBody($body);
    }
}
