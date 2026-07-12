<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Application;

interface TokenGeneratorInterface
{
    public function generate(): string;
}
