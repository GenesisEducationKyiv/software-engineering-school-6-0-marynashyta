<?php

declare(strict_types=1);

namespace NotificationService\Handler;

use NotificationService\MailerInterface;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SendConfirmationHandler
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function __invoke(Request $req, Response $res): Response
    {
        $body = $req->getParsedBody();
        $body = is_array($body) ? $body : [];

        $email            = trim((string) ($body['email'] ?? ''));
        $repo             = trim((string) ($body['repo'] ?? ''));
        $confirmToken     = trim((string) ($body['confirm_token'] ?? ''));
        $unsubscribeToken = trim((string) ($body['unsubscribe_token'] ?? ''));

        if ($email === '' || $repo === '' || $confirmToken === '' || $unsubscribeToken === '') {
            return $this->json($res, ['message' => 'Fields email, repo, confirm_token, unsubscribe_token are required'], 400);
        }

        try {
            $this->mailer->sendConfirmation($email, $repo, $confirmToken, $unsubscribeToken);
            return $res->withStatus(204);
        } catch (PHPMailerException $e) {
            return $this->json($res, ['message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    private function json(Response $res, mixed $data, int $status): Response
    {
        $res->getBody()->write((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $res->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
