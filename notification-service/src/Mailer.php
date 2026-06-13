<?php

declare(strict_types=1);

namespace NotificationService;

use NotificationService\Config\SmtpConfig;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class Mailer implements MailerInterface
{
    public function __construct(
        private readonly SmtpConfig $smtp,
        private readonly string $appUrl,
    ) {
    }

    /** @throws PHPMailerException */
    public function sendConfirmation(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {
        $appUrl         = rtrim($this->appUrl, '/');
        $confirmUrl     = "{$appUrl}/api/confirm/{$confirmToken}";
        $unsubscribeUrl = "{$appUrl}/api/unsubscribe/{$unsubscribeToken}";

        $this->send(
            $email,
            "Confirm your subscription to {$repo} releases",
            $this->confirmationBody($repo, $confirmUrl, $unsubscribeUrl),
        );
    }

    /** @throws PHPMailerException */
    public function sendNotification(
        string $email,
        string $repo,
        string $tag,
        string $unsubscribeToken,
    ): void {
        $appUrl         = rtrim($this->appUrl, '/');
        $releaseUrl     = "https://github.com/{$repo}/releases/tag/{$tag}";
        $unsubscribeUrl = "{$appUrl}/api/unsubscribe/{$unsubscribeToken}";

        $this->send(
            $email,
            "New release: {$repo} {$tag}",
            $this->notificationBody($repo, $tag, $releaseUrl, $unsubscribeUrl),
        );
    }

    /** @throws PHPMailerException */
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

    private function confirmationBody(string $repo, string $confirmUrl, string $unsubscribeUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Subscription</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <h1 style="color: #24292e;">Confirm your GitHub Release subscription</h1>
    <p>You have requested to receive notifications for new releases of <strong>{$repo}</strong>.</p>
    <p>Please confirm your subscription by clicking the button below:</p>
    <p style="text-align: center; margin: 30px 0;">
        <a href="{$confirmUrl}"
           style="background-color: #2ea44f; color: #fff; padding: 12px 24px; text-decoration: none;
                  border-radius: 6px; font-size: 16px; font-weight: bold;">
            Confirm Subscription
        </a>
    </p>
    <p style="font-size: 14px; color: #666;">Or copy and paste this URL into your browser:</p>
    <p style="font-size: 12px; color: #0366d6; word-break: break-all;">{$confirmUrl}</p>
    <hr style="border: none; border-top: 1px solid #e1e4e8; margin: 30px 0;">
    <p style="font-size: 12px; color: #999;">
        If you did not request this subscription, you can safely ignore this email.<br>
        To unsubscribe at any time, <a href="{$unsubscribeUrl}" style="color: #0366d6;">click here</a>.
    </p>
</body>
</html>
HTML;
    }

    private function notificationBody(
        string $repo,
        string $tag,
        string $releaseUrl,
        string $unsubscribeUrl,
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Release</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #333;">
    <h1 style="color: #24292e;">New release available!</h1>
    <p>A new release has been published for <strong>{$repo}</strong>.</p>
    <table style="border: 1px solid #e1e4e8; border-radius: 6px; padding: 16px; width: 100%;
                  border-collapse: collapse; margin: 20px 0;">
        <tr>
            <td style="padding: 8px; font-weight: bold; color: #586069;">Repository</td>
            <td style="padding: 8px;">{$repo}</td>
        </tr>
        <tr style="background-color: #f6f8fa;">
            <td style="padding: 8px; font-weight: bold; color: #586069;">Release Tag</td>
            <td style="padding: 8px;">
                <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">{$tag}</code>
            </td>
        </tr>
    </table>
    <p style="text-align: center; margin: 30px 0;">
        <a href="{$releaseUrl}"
           style="background-color: #0366d6; color: #fff; padding: 12px 24px; text-decoration: none;
                  border-radius: 6px; font-size: 16px; font-weight: bold;">
            View Release on GitHub
        </a>
    </p>
    <p style="font-size: 14px; color: #666;">Or copy and paste this URL into your browser:</p>
    <p style="font-size: 12px; color: #0366d6; word-break: break-all;">{$releaseUrl}</p>
    <hr style="border: none; border-top: 1px solid #e1e4e8; margin: 30px 0;">
    <p style="font-size: 12px; color: #999;">
        You are receiving this email because you subscribed to release notifications for {$repo}.<br>
        To unsubscribe, <a href="{$unsubscribeUrl}" style="color: #0366d6;">click here</a>.
    </p>
</body>
</html>
HTML;
    }
}
