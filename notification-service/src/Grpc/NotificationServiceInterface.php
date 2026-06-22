<?php

declare(strict_types=1);

namespace NotificationService\Grpc;

use Notification\V1\SendConfirmationRequest;
use Notification\V1\SendConfirmationResponse;
use Notification\V1\SendNotificationRequest;
use Notification\V1\SendNotificationResponse;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\ServiceInterface;

interface NotificationServiceInterface extends ServiceInterface
{
    /** gRPC fully-qualified service name – must match the proto package + service name. */
    public const NAME = 'notification.v1.NotificationService';

    public function SendConfirmation(
        ContextInterface $ctx,
        SendConfirmationRequest $in,
    ): SendConfirmationResponse;

    public function SendNotification(
        ContextInterface $ctx,
        SendNotificationRequest $in,
    ): SendNotificationResponse;
}
