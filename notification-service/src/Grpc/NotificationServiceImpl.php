<?php

declare(strict_types=1);

namespace NotificationService\Grpc;

use Notification\V1\SendConfirmationRequest;
use Notification\V1\SendConfirmationResponse;
use Notification\V1\SendNotificationRequest;
use Notification\V1\SendNotificationResponse;
use NotificationService\MailerInterface;
use PHPMailer\PHPMailer\Exception as MailerException;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\StatusCode;

final class NotificationServiceImpl implements NotificationServiceInterface
{
    public function __construct(private readonly MailerInterface $mailer) {}

    /** @throws GRPCException */
    public function SendConfirmation(
        ContextInterface $ctx,
        SendConfirmationRequest $in,
    ): SendConfirmationResponse {
        $email            = $in->getEmail();
        $repo             = $in->getRepo();
        $confirmToken     = $in->getConfirmToken();
        $unsubscribeToken = $in->getUnsubscribeToken();

        if ($email === '' || $repo === '' || $confirmToken === '' || $unsubscribeToken === '') {
            throw new GRPCException(
                message: 'Fields email, repo, confirm_token, unsubscribe_token are required',
                code: StatusCode::INVALID_ARGUMENT,
            );
        }

        try {
            $this->mailer->sendConfirmation($email, $repo, $confirmToken, $unsubscribeToken);
        } catch (MailerException $e) {
            throw new GRPCException(
                message: 'Failed to send confirmation email: ' . $e->getMessage(),
                code: StatusCode::INTERNAL,
                previous: $e,
            );
        }

        return new SendConfirmationResponse();
    }

    /** @throws GRPCException */
    public function SendNotification(
        ContextInterface $ctx,
        SendNotificationRequest $in,
    ): SendNotificationResponse {
        $email            = $in->getEmail();
        $repo             = $in->getRepo();
        $tag              = $in->getTag();
        $unsubscribeToken = $in->getUnsubscribeToken();

        if ($email === '' || $repo === '' || $tag === '' || $unsubscribeToken === '') {
            throw new GRPCException(
                message: 'Fields email, repo, tag, unsubscribe_token are required',
                code: StatusCode::INVALID_ARGUMENT,
            );
        }

        try {
            $this->mailer->sendNotification($email, $repo, $tag, $unsubscribeToken);
        } catch (MailerException $e) {
            throw new GRPCException(
                message: 'Failed to send notification email: ' . $e->getMessage(),
                code: StatusCode::INTERNAL,
                previous: $e,
            );
        }

        return new SendNotificationResponse();
    }
}
