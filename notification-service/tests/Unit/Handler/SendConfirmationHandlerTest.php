<?php

declare(strict_types=1);

namespace Tests\Unit\Handler;

use NotificationService\Handler\SendConfirmationHandler;
use NotificationService\MailerInterface;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\RequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

final class SendConfirmationHandlerTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private SendConfirmationHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer  = $this->createMock(MailerInterface::class);
        $this->handler = new SendConfirmationHandler($this->mailer);
    }

    #[Test]
    public function sendConfirmationReturns204WhenMailerSucceeds(): void
    {
        $this->mailer
            ->expects($this->once())
            ->method('sendConfirmation')
            ->with('user@example.com', 'owner/repo', 'a' . str_repeat('0', 63), 'b' . str_repeat('0', 63));

        $res = ($this->handler)(
            $this->makeRequest([
                'email'             => 'user@example.com',
                'repo'              => 'owner/repo',
                'confirm_token'     => 'a' . str_repeat('0', 63),
                'unsubscribe_token' => 'b' . str_repeat('0', 63),
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(204, $res->getStatusCode());
    }

    #[Test]
    public function sendConfirmationReturns400WhenEmailMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendConfirmation');

        $res = ($this->handler)(
            $this->makeRequest([
                'repo'              => 'owner/repo',
                'confirm_token'     => str_repeat('0', 64),
                'unsubscribe_token' => str_repeat('0', 64),
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(400, $res->getStatusCode());
    }

    #[Test]
    public function sendConfirmationReturns400WhenRepoMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendConfirmation');

        $res = ($this->handler)(
            $this->makeRequest([
                'email'             => 'user@example.com',
                'confirm_token'     => str_repeat('0', 64),
                'unsubscribe_token' => str_repeat('0', 64),
            ]),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(400, $res->getStatusCode());
    }

    #[Test]
    public function sendConfirmationReturns400WhenTokensMissing(): void
    {
        $this->mailer->expects($this->never())->method('sendConfirmation');

        $res = ($this->handler)(
            $this->makeRequest(['email' => 'user@example.com', 'repo' => 'owner/repo']),
            (new ResponseFactory())->createResponse(),
        );

        $this->assertSame(400, $res->getStatusCode());
    }

    #[Test]
    public function sendConfirmationReturns500WhenMailerThrows(): void
    {
        $this->mailer
            ->method('sendConfirmation')
            ->willThrowException(new PHPMailerException('SMTP connect failed'));

        $res = ($this->handler)(
            $this->makeRequest([
                'email'             => 'user@example.com',
                'repo'              => 'owner/repo',
                'confirm_token'     => str_repeat('0', 64),
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
            ->createRequest('POST', '/send-confirmation')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream)
            ->withParsedBody($body);
    }
}
