<?php

declare(strict_types=1);

namespace App\Metrics;

final class MetricsKeys
{
    public const HTTP    = 'rna:http_requests';
    public const GITHUB  = 'rna:github_api_calls';
    public const NOTIFY  = 'rna:notifications_sent';
    public const SCANNER = 'rna:scanner_cycles';
}
