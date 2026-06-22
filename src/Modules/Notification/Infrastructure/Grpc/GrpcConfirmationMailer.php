<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Grpc;

use App\Modules\Notification\Domain\ConfirmationMailerInterface;
use Notification\V1\NotificationServiceClient;
use Notification\V1\SendConfirmationRequest;
use PHPMailer\PHPMailer\Exception as MailerException;

final class GrpcConfirmationMailer implements ConfirmationMailerInterface
{
    public function __construct(private readonly NotificationServiceClient $client)
    {
    }

    /** @throws MailerException */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {
        $request = (new SendConfirmationRequest())
            ->setEmail($email)
            ->setRepo($repo)
            ->setConfirmToken($confirmToken)
            ->setUnsubscribeToken($unsubscribeToken);

        /** @var \Grpc\UnaryCall<\Notification\V1\SendConfirmationResponse> $call */
        $call = $this->client->SendConfirmation($request);

        /**
         * @var \Notification\V1\SendConfirmationResponse|null $response
         * @var object{code:int,details:string} $status
         */
        [$response, $status] = $call->wait();

        if ($status->code !== 0) {
            throw new MailerException(
                "gRPC SendConfirmation failed [code={$status->code}]: {$status->details}",
            );
        }
    }
}
