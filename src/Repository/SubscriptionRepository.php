<?php

declare(strict_types=1);

namespace App\Repository;

use App\DTO\Subscription;
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

    public function findByConfirmToken(string $token): ?Subscription
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, repo, confirmed, last_seen_tag, unsubscribe_token
             FROM subscriptions WHERE confirm_token = ?'
        );
        $stmt->execute([$token]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function confirm(int $id, ?string $lastSeenTag): void
    {
        $this->db->prepare(
            'UPDATE subscriptions SET confirmed = 1, last_seen_tag = ? WHERE id = ?'
        )->execute([$lastSeenTag, $id]);
    }

    public function findByUnsubscribeToken(string $token): ?Subscription
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, repo, confirmed, last_seen_tag, unsubscribe_token
             FROM subscriptions WHERE unsubscribe_token = ?'
        );
        $stmt->execute([$token]);

        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function delete(int $id): void
    {
        $this->db->prepare(
            'DELETE FROM subscriptions WHERE id = ?'
        )->execute([$id]);
    }

    /** @return list<Subscription> */
    public function findConfirmedByEmail(string $email): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, repo, confirmed, last_seen_tag, unsubscribe_token
             FROM subscriptions
             WHERE email = ? AND confirmed = 1'
        );
        $stmt->execute([$email]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return array_map(fn(array $row): Subscription => $this->hydrate($row), $rows);
    }

    /** @return list<Subscription> */
    public function findAllConfirmed(): array
    {
        $stmt = $this->db->query(
            'SELECT id, email, repo, confirmed, last_seen_tag, unsubscribe_token
             FROM subscriptions
             WHERE confirmed = 1'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return array_map(fn(array $row): Subscription => $this->hydrate($row), $rows);
    }

    public function updateLastSeenTag(int $id, string $tag): void
    {
        $this->db->prepare('UPDATE subscriptions SET last_seen_tag = ? WHERE id = ?')
            ->execute([$tag, $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Subscription
    {
        return new Subscription(
            id:               is_numeric($row['id']) ? (int) $row['id'] : 0,
            email:            is_string($row['email']) ? $row['email'] : '',
            repo:             is_string($row['repo']) ? $row['repo'] : '',
            confirmed:        is_numeric($row['confirmed']) ? (bool) $row['confirmed'] : false,
            lastSeenTag:      is_string($row['last_seen_tag']) ? $row['last_seen_tag'] : null,
            unsubscribeToken: is_string($row['unsubscribe_token']) ? $row['unsubscribe_token'] : '',
        );
    }
}
