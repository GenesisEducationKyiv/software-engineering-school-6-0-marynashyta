<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function existsByEmailAndRepo(string $email, string $repo): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM subscriptions WHERE email = ? AND repo = ?'
        );
        $stmt->execute([$email, $repo]);
        return $stmt->fetch() !== false;
    }

    public function create(
        string $email,
        string $repo,
        string $confirmToken,
        string $unsubscribeToken,
    ): void {
        $this->db->prepare(
            'INSERT INTO subscriptions (email, repo, confirmed, confirm_token, unsubscribe_token)
             VALUES (?, ?, 0, ?, ?)'
        )->execute([$email, $repo, $confirmToken, $unsubscribeToken]);
    }

    /**
     * @return array{id: int, repo: string, confirmed: int}|null
     */
    public function findByConfirmToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, repo, confirmed FROM subscriptions WHERE confirm_token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => is_numeric($row['id']) ? (int)$row['id'] : 0,
            'repo' => is_string($row['repo']) ? $row['repo'] : '',
            'confirmed' => is_numeric($row['confirmed']) ? (int)$row['confirmed'] : 0,
        ];
    }

    public function confirm(int $id, ?string $lastSeenTag): void
    {
        $this->db->prepare(
            'UPDATE subscriptions SET confirmed = 1, last_seen_tag = ? WHERE id = ?'
        )->execute([$lastSeenTag, $id]);
    }

    /**
     * @return array{id: int}|null
     */
    public function findByUnsubscribeToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM subscriptions WHERE unsubscribe_token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return ['id' => is_numeric($row['id']) ? (int)$row['id'] : 0];
    }

    public function delete(int $id): void
    {
        $this->db->prepare(
            'DELETE FROM subscriptions WHERE id = ?'
        )->execute([$id]);
    }

    /**
     * @return list<array{email: string, repo: string, confirmed: bool, last_seen_tag: string|null}>
     */
    public function findConfirmedByEmail(string $email): array
    {
        $stmt = $this->db->prepare(
            'SELECT email, repo, confirmed, last_seen_tag
             FROM subscriptions
             WHERE email = ? AND confirmed = 1'
        );
        $stmt->execute([$email]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): array => [
                'email' => is_string($row['email']) ? $row['email'] : '',
                'repo' => is_string($row['repo']) ? $row['repo'] : '',
                'confirmed' => true,
                'last_seen_tag' => is_string($row['last_seen_tag']) ? $row['last_seen_tag'] : null,
            ],
            $rows
        );
    }
}
