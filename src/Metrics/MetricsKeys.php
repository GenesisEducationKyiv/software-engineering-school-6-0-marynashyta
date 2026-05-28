<?php

declare(strict_types=1);

namespace App\Metrics;

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

    public const HISTOGRAM_BUCKETS = ['0.005', '0.01', '0.025', '0.05', '0.1', '0.25', '0.5', '1', '2.5', '5', '10'];
}
