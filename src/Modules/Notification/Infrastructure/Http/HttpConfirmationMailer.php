<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Http;

use App\Modules\Notification\Application\ConfirmationMailerInterface;
use App\Modules\Notification\Application\Exception\NotificationDeliveryException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

final class HttpConfirmationMailer implements ConfirmationMailerInterface
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @throws NotificationDeliveryException
     */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {
        try {
            $this->http->request('POST', rtrim($this->baseUrl, '/') . '/send-confirmation', [
                'json' => [
                    'email'            => $email,
                    'repo'             => $repo,
                    'confirm_token'    => $confirmToken,
                    'unsubscribe_token' => $unsubscribeToken,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new NotificationDeliveryException('Notification service unreachable: ' . $e->getMessage(), 0, $e);
        }
    }
}
