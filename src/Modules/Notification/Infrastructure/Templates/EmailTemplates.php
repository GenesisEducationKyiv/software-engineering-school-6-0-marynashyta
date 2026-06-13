<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Templates;

final class EmailTemplates
{
    public static function confirmation(
        string $repo,
        string $confirmUrl,
        string $unsubscribeUrl
    ): string {
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

    public static function releaseNotification(
        string $repo,
        string $tag,
        string $releaseUrl,
        string $unsubscribeUrl
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
