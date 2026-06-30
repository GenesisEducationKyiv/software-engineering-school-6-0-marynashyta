<?php

declare(strict_types=1);

namespace NotificationService\Consumer;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

final class MessageProcessor
{
    public const QUEUE             = 'notifications';
    public const DEAD_LETTER_QUEUE = 'notifications.dlq';

    private const RETRY_COUNT_HEADER = 'x-retry-count';

    public function __construct(
        private readonly MessageHandler $handler,
        private readonly LoggerInterface $logger,
        private readonly RetryPolicy $retryPolicy = new RetryPolicy(),
    ) {
    }

    public function process(AMQPChannel $channel, AMQPMessage $msg): void
    {
        try {
            $this->handler->handle($msg->getBody());
            $channel->basic_ack($msg->getDeliveryTag());
            $this->logger->info('Message processed');
        } catch (\Throwable $e) {
            $this->handleFailure($channel, $msg, $e);
        }
    }

    private function handleFailure(AMQPChannel $channel, AMQPMessage $msg, \Throwable $e): void
    {
        $attempt = $this->attemptCount($msg) + 1;

        if ($this->retryPolicy->shouldRetry($e, $attempt)) {
            $this->logger->warning('Message processing failed — scheduling retry', [
                'error'   => $e->getMessage(),
                'attempt' => $attempt,
            ]);
            $this->republish($channel, self::QUEUE, $msg, $attempt);
            return;
        }

        $this->logger->error('Message processing failed permanently — routing to dead-letter queue', [
            'error'   => $e->getMessage(),
            'attempt' => $attempt,
        ]);
        $this->republish($channel, self::DEAD_LETTER_QUEUE, $msg, $attempt);
    }

    private function republish(AMQPChannel $channel, string $queue, AMQPMessage $original, int $attempt): void
    {
        $properties = $original->get_properties();
        $properties['application_headers'] = new AMQPTable([self::RETRY_COUNT_HEADER => $attempt]);

        $channel->basic_publish(new AMQPMessage($original->getBody(), $properties), '', $queue);
        $channel->basic_ack($original->getDeliveryTag());
    }

    private function attemptCount(AMQPMessage $msg): int
    {
        $headers = $msg->get_properties()['application_headers'] ?? null;

        if (!$headers instanceof AMQPTable) {
            return 0;
        }

        $value = $headers->getNativeData()[self::RETRY_COUNT_HEADER] ?? 0;

        return is_int($value) ? $value : 0;
    }
}
