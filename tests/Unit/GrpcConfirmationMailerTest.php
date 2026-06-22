<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Notification\Infrastructure\Grpc\GrpcConfirmationMailer;
use Notification\V1\NotificationServiceClient;
use Notification\V1\SendConfirmationRequest;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('grpc')]
final class GrpcConfirmationMailerTest extends TestCase
{
    private NotificationServiceClient&MockObject $client;
    private GrpcConfirmationMailer $mailer;

    #[Test]
    public function itForwardsAllFieldsToTheGrpcClient(): void
    {
        $this->client
            ->expects($this->once())
            ->method('SendConfirmation')
            ->with($this->callback(static function (SendConfirmationRequest $r): bool {
                return $r->getEmail() === 'user@example.com'
                    && $r->getRepo() === 'owner/repo'
                    && $r->getConfirmToken() === 'confirm-tok'
                    && $r->getUnsubscribeToken() === 'unsub-tok';
            }))
            ->willReturn($this->makeCall(0));

        $this->mailer->sendConfirmation('user@example.com', 'owner/repo', 'confirm-tok', 'unsub-tok');
    }

    #[Test]
    public function itSucceedsWithoutThrowingOnStatusCodeZero(): void
    {
        $this->client->method('SendConfirmation')->willReturn($this->makeCall(0));

        $this->mailer->sendConfirmation('user@example.com', 'owner/repo', 'tok', 'unsub');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function itThrowsMailerExceptionOnNonZeroStatusCode(): void
    {
        $this->client->method('SendConfirmation')->willReturn($this->makeCall(13, 'internal error'));

        $this->expectException(MailerException::class);
        $this->expectExceptionMessageMatches('/code=13.*internal error/');

        $this->mailer->sendConfirmation('user@example.com', 'owner/repo', 'tok', 'unsub');
    }

    #[Test]
    public function itIncludesStatusCodeAndDetailsInExceptionMessage(): void
    {
        $this->client->method('SendConfirmation')->willReturn($this->makeCall(3, 'invalid argument'));

        try {
            $this->mailer->sendConfirmation('a@b.com', 'r/r', 't', 'u');
            $this->fail('Expected MailerException was not thrown');
        } catch (MailerException $e) {
            $this->assertStringContainsString('code=3', $e->getMessage());
            $this->assertStringContainsString('invalid argument', $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = $this->createMock(NotificationServiceClient::class);
        $this->mailer = new GrpcConfirmationMailer($this->client);
    }

    private function makeCall(int $code, string $details = ''): object
    {
        $status          = new \stdClass();
        $status->code    = $code;
        $status->details = $details;

        return new class ($status) {
            public function __construct(private readonly \stdClass $status) {}

            /** @return array{0: null, 1: \stdClass} */
            public function wait(): array
            {
                return [null, $this->status];
            }
        };
    }
}
