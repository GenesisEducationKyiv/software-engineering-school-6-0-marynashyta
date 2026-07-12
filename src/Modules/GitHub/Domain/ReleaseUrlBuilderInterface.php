<?php

declare(strict_types=1);

namespace App\Modules\GitHub\Domain;

interface ReleaseUrlBuilderInterface
{
    public function buildReleaseUrl(string $repo, string $tag): string;
}
