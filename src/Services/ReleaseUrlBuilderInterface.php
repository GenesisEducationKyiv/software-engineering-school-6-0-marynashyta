<?php

declare(strict_types=1);

namespace App\Services;

interface ReleaseUrlBuilderInterface
{
    public function buildReleaseUrl(string $repo, string $tag): string;
}
