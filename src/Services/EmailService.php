<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\SmtpConfig;
use App\Services\Templates\EmailTemplates;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class EmailService implements ConfirmationMailerInterface, NotificationMailerInterface
{
    private string $appUrl;

    public function __construct(
        private readonly SmtpConfig $smtp,
        string $appUrl,
        private readonly ReleaseUrlBuilderInterface $releaseUrlBuilder,
    ) {
        $this->appUrl = rtrim($appUrl, '/');
    }

    /**
     * @throws PHPMailerException
     */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken
    ): void {
        $confirmUrl     = "{$this->appUrl}/api/confirm/{$confirmToken}";
        $unsubscribeUrl = "{$this->appUrl}/api/unsubscribe/{$unsubscribeToken}";

        $this->send(
            $email,
            "Confirm your subscription to {$repo} releases",
            EmailTemplates::confirmation($repo, $confirmUrl, $unsubscribeUrl)
        );
    }

    /**
     * @throws PHPMailerException
     */
    public function sendReleaseNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken
    ): void {
        $releaseUrl     = $this->releaseUrlBuilder->buildReleaseUrl($repo, $tag);
        $unsubscribeUrl = "{$this->appUrl}/api/unsubscribe/{$unsubscribeToken}";

        $this->send(
            $email,
            "New release: {$repo} {$tag}",
            EmailTemplates::releaseNotification($repo, $tag, $releaseUrl, $unsubscribeUrl)
        );
    }

    /**
     * @throws PHPMailerException
     */
    private function send(string $to, string $subject, string $body): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->smtp->host;
        $mail->Port = $this->smtp->port;

        if ($this->smtp->username !== '') {
            $mail->SMTPAuth   = true;
            $mail->SMTPSecure = $this->smtp->port === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username = $this->smtp->username;
            $mail->Password = $this->smtp->password;
        } else {
            $mail->SMTPAuth   = false;
            $mail->SMTPSecure = '';
        }

        $mail->setFrom($this->smtp->fromAddress, $this->smtp->fromName);
        $mail->addAddress($to);

        $mail->isHTML();
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
    }
}
