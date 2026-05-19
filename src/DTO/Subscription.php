<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class Subscription implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $email,
        public string $repo,
        public bool $confirmed,
        public ?string $lastSeenTag,
        public string $unsubscribeToken,
    ) {
    }

    /**
     * @return array{email: string, repo: string, confirmed: bool, last_seen_tag: string|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'email'         => $this->email,
            'repo'          => $this->repo,
            'confirmed'     => $this->confirmed,
            'last_seen_tag' => $this->lastSeenTag,
        ];
    }
}
