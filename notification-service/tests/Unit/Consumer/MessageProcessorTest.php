<?php

declare(strict_types=1);

namespace Tests\Unit\Consumer;

use NotificationService\Consumer\MessageHandler;
use NotificationService\Consumer\MessageProcessor;
use NotificationService\Consumer\RetryPolicy;
use NotificationService\MailerInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class MessageProcessorTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private MessageHandler $handler;
    private LoggerInterface&MockObject $logger;
    private AMQPChannel&MockObject $channel;

    #[Test]
    public function acksMessageWhenHandlerSucceeds(): void
    {
        $msg = $this->confirmationMessage();

        $this->mailer->expects($this->once())->method('sendConfirmation');
        $this->channel->expects($this->once())->method('basic_ack')->with(7);
        $this->channel->expects($this->never())->method('basic_publish');

        (new MessageProcessor($this->handler, $this->logger))->process($this->channel, $msg);
    }

    #[Test]
    public function republishesToMainQueueOnFirstTransientFailure(): void
    {
        $msg = $this->confirmationMessage();

        $this->mailer->method('sendConfirmation')->willThrowException(new \RuntimeException('SMTP timeout'));

        $this->channel->expects($this->once())
            ->method('basic_publish')
            ->with(
                $this->callback(fn (AMQPMessage $republished): bool => $this->attemptOf($republished) === 1),
                '',
                MessageProcessor::QUEUE,
            );
        $this->channel->expects($this->once())->method('basic_ack')->with(7);

        (new MessageProcessor($this->handler, $this->logger, new RetryPolicy(maxAttempts: 3)))
            ->process($this->channel, $msg);
    }

    #[Test]
    public function routesToDeadLetterQueueOnceMaxAttemptsExceeded(): void
    {
        $msg = $this->confirmationMessage(attempt: 3);

        $this->mailer->method('sendConfirmation')->willThrowException(new \RuntimeException('SMTP timeout'));

        $this->channel->expects($this->once())
            ->method('basic_publish')
            ->with($this->anything(), '', MessageProcessor::DEAD_LETTER_QUEUE);
        $this->channel->expects($this->once())->method('basic_ack')->with(7);

        (new MessageProcessor($this->handler, $this->logger, new RetryPolicy(maxAttempts: 3)))
            ->process($this->channel, $msg);
    }

    #[Test]
    public function routesMalformedPayloadStraightToDeadLetterQueueWithoutRetrying(): void
    {
        $msg = $this->message('not valid json');

        $this->mailer->expects($this->never())->method('sendConfirmation');
        $this->mailer->expects($this->never())->method('sendNotification');

        $this->channel->expects($this->once())
            ->method('basic_publish')
            ->with($this->anything(), '', MessageProcessor::DEAD_LETTER_QUEUE);
        $this->channel->expects($this->once())->method('basic_ack')->with(7);

        (new MessageProcessor($this->handler, $this->logger, new RetryPolicy(maxAttempts: 3)))
            ->process($this->channel, $msg);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailer  = $this->createMock(MailerInterface::class);
        $this->handler = new MessageHandler($this->mailer);
        $this->logger  = $this->createMock(LoggerInterface::class);
        $this->channel = $this->createMock(AMQPChannel::class);
    }

    private function confirmationMessage(int $attempt = 0): AMQPMessage
    {
        return $this->message(json_encode([
            'type'              => 'send_confirmation',
            'email'             => 'user@example.com',
            'repo'              => 'owner/repo',
            'confirm_token'     => 'ctok',
            'unsubscribe_token' => 'utok',
        ], JSON_THROW_ON_ERROR), $attempt);
    }

    private function message(string $body, int $attempt = 0): AMQPMessage
    {
        $properties = $attempt > 0
            ? ['application_headers' => new AMQPTable(['x-retry-count' => $attempt])]
            : [];

        $msg = new AMQPMessage($body, $properties);
        $msg->setDeliveryTag(7);

        return $msg;
    }

    private function attemptOf(AMQPMessage $msg): int
    {
        /** @var AMQPTable $headers */
        $headers = $msg->get_properties()['application_headers'];

        return $headers->getNativeData()['x-retry-count'];
    }
}
