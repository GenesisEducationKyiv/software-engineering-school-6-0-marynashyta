<?php

declare(strict_types=1);

namespace App\Modules\Observability\Domain;

interface MetricsRendererInterface
{
    public function render(): string;
}
