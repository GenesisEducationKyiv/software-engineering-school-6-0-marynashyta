<?php

declare(strict_types=1);

namespace App\Services;

final class GitHubReleaseUrlBuilder implements ReleaseUrlBuilderInterface
{
    public function buildReleaseUrl(string $repo, string $tag): string
    {
        return "https://github.com/{$repo}/releases/tag/{$tag}";
    }
}
