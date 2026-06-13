<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure;

final class MetricsKeys
{
    private function __construct()
    {
    }

    public const HTTP    = 'rna:http_requests';
    public const GITHUB  = 'rna:github_api_calls';
    public const NOTIFY  = 'rna:notifications_sent';
    public const SCANNER = 'rna:scanner_cycles';

    public const HTTP_DURATION_HIST = 'rna:http_duration_hist';
    public const HTTP_DURATION_SUM  = 'rna:http_duration_sum';

    public const HISTOGRAM_BUCKETS = ['5', '10', '25', '50', '100', '250', '500', '1000'];
}
