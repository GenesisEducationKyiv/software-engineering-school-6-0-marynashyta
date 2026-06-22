<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Grpc;

use App\Modules\Notification\Domain\NotificationMailerInterface;
use Notification\V1\NotificationServiceClient;
use Notification\V1\SendNotificationRequest;
use PHPMailer\PHPMailer\Exception as MailerException;

final class GrpcNotificationMailer implements NotificationMailerInterface
{
    public function __construct(private readonly NotificationServiceClient $client)
    {
    }

    /** @throws MailerException */
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {
        $request = (new SendNotificationRequest())
            ->setEmail($email)
            ->setRepo($repo)
            ->setTag($tag)
            ->setUnsubscribeToken($unsubscribeToken);

        /** @var \Grpc\UnaryCall<\Notification\V1\SendNotificationResponse> $call */
        $call = $this->client->SendNotification($request);

        /**
         * @var \Notification\V1\SendNotificationResponse|null $response
         * @var object{code:int,details:string} $status
         */
        [$response, $status] = $call->wait();

        if ($status->code !== 0) {
            throw new MailerException(
                "gRPC SendNotification failed [code={$status->code}]: {$status->details}",
            );
        }
    }
}
