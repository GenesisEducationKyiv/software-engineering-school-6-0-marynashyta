<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Notification\V1;

/**
 * NotificationService handles transactional email delivery on behalf of the API service.
 */
class NotificationServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * SendConfirmation sends a subscription-confirmation email with a confirm link and
     * an unsubscribe link. Called synchronously from the Subscribe saga orchestrator.
     * @param \Notification\V1\SendConfirmationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Notification\V1\SendConfirmationResponse>
     */
    public function SendConfirmation(\Notification\V1\SendConfirmationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/notification.v1.NotificationService/SendConfirmation',
        $argument,
        ['\Notification\V1\SendConfirmationResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * SendNotification sends a release-alert email to a confirmed subscriber.
     * @param \Notification\V1\SendNotificationRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Notification\V1\SendNotificationResponse>
     */
    public function SendNotification(\Notification\V1\SendNotificationRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/notification.v1.NotificationService/SendNotification',
        $argument,
        ['\Notification\V1\SendNotificationResponse', 'decode'],
        $metadata, $options);
    }

}
