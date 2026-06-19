<?php

declare(strict_types=1);

namespace ScannerService\Notification;

interface NotificationPublisherInterface
{
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void;
}
