<?php

declare(strict_types=1);

namespace App\Services;

interface EmailServiceInterface extends ConfirmationMailerInterface, NotificationMailerInterface
{
}
