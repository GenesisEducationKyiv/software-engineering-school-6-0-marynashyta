<?php

declare(strict_types=1);

namespace App\Metrics;

interface MetricsRendererInterface
{
    public function render(): string;
}
