<?php

declare(strict_types=1);

namespace ScannerService\Notification;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use ScannerService\Config\AmqpConfig;

final class AmqpNotificationPublisher implements NotificationPublisherInterface
{
    private const QUEUE = 'notifications';

    private ?AMQPChannel $channel = null;
    private ?AMQPStreamConnection $connection = null;

    public function __construct(private readonly AmqpConfig $config)
    {
    }

    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {
        $body = json_encode([
            'type'              => 'send_notification',
            'email'             => $email,
            'repo'              => $repo,
            'tag'               => $tag,
            'unsubscribe_token' => $unsubscribeToken,
        ], JSON_THROW_ON_ERROR);

        $this->channel()->basic_publish(
            new AMQPMessage($body, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
            '',
            self::QUEUE,
        );
    }

    public function __destruct()
    {
        $this->channel?->close();
        $this->connection?->close();
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection(
            $this->config->host,
            $this->config->port,
            $this->config->user,
            $this->config->password,
        );
        $channel = $this->connection->channel();
        $channel->queue_declare(self::QUEUE, false, true, false, false);

        return $this->channel = $channel;
    }
}
