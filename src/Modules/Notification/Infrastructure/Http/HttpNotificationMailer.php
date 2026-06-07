<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Http;

use App\Modules\Notification\Domain\NotificationMailerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

final class HttpNotificationMailer implements NotificationMailerInterface
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @throws PHPMailerException
     */
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {
        try {
            $this->http->request('POST', rtrim($this->baseUrl, '/') . '/send-notification', [
                'json' => [
                    'email'            => $email,
                    'repo'             => $repo,
                    'tag'              => $tag,
                    'unsubscribe_token' => $unsubscribeToken,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new PHPMailerException('Notification service unreachable: ' . $e->getMessage(), 0, $e);
        }
    }
}
