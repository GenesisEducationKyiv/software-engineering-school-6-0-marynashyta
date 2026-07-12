<?php

declare(strict_types=1);

namespace App\Modules\GitHub\Domain;

final readonly class GitHubRelease
{
    public function __construct(
        public ?string $tagName,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            tagName: is_string($data['tag_name']) && $data['tag_name'] !== '' ? $data['tag_name'] : null,
        );
    }
}
