<?php

declare(strict_types=1);

namespace NotificationService\Consumer;

use NotificationService\Config\AmqpConfig;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

final class NotificationConsumer
{
    public function __construct(
        private readonly MessageProcessor $processor,
        private readonly LoggerInterface $logger,
        private readonly AmqpConfig $amqpConfig,
    ) {
    }

    public function run(): void
    {
        $connection = new AMQPStreamConnection(
            host:     $this->amqpConfig->host,
            port:     $this->amqpConfig->port,
            user:     $this->amqpConfig->user,
            password: $this->amqpConfig->password,
        );

        try {
            $channel = $connection->channel();
            $channel->queue_declare(MessageProcessor::QUEUE, false, true, false, false);
            $channel->queue_declare(MessageProcessor::DEAD_LETTER_QUEUE, false, true, false, false);
            $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

            $this->logger->info('Consumer started', ['queue' => MessageProcessor::QUEUE]);

            $running = true;
            pcntl_signal(SIGTERM, function () use (&$running, $channel): void {
                $this->logger->info('SIGTERM received — shutting down');
                $running = false;
                $channel->stopConsume();
            });

            $channel->basic_consume(
                queue:    MessageProcessor::QUEUE,
                callback: function (AMQPMessage $msg) use ($channel): void {
                    $this->processor->process($channel, $msg);
                },
            );

            while ($running && $channel->is_consuming()) {
                try {
                    $channel->wait(null, false, 1.0);
                } catch (AMQPTimeoutException) {
                }
                pcntl_signal_dispatch();
            }

            $this->logger->info('Consumer stopped');
            $channel->close();
        } finally {
            $connection->close();
        }
    }
}
