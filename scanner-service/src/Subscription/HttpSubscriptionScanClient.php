<?php

declare(strict_types=1);

namespace ScannerService\Subscription;

use GuzzleHttp\ClientInterface;
use ScannerService\Config\ApiConfig;
use ScannerService\Infrastructure\CircuitBreaker;

final class HttpSubscriptionScanClient implements SubscriptionScanClientInterface
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly ApiConfig $config,
        private readonly ?CircuitBreaker $circuitBreaker = null,
    ) {
    }

    /** @return list<Subscription> */
    public function findAllConfirmed(): array
    {
        $call = function (): array {
            $response = $this->http->request('GET', $this->config->baseUrl . '/internal/subscriptions/confirmed', [
                'headers' => $this->headers(),
            ]);

            /** @var array{subscriptions: list<array<string, mixed>>} $body */
            $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

            return array_map(
                fn (array $row) => new Subscription(
                    id:               is_numeric($row['id']) ? (int) $row['id'] : 0,
                    email:            is_string($row['email']) ? $row['email'] : '',
                    repo:             is_string($row['repo']) ? $row['repo'] : '',
                    lastSeenTag:      is_string($row['last_seen_tag']) ? $row['last_seen_tag'] : null,
                    unsubscribeToken: is_string($row['unsubscribe_token']) ? $row['unsubscribe_token'] : '',
                ),
                $body['subscriptions'] ?? [],
            );
        };

        /** @var list<Subscription> */
        return $this->circuitBreaker !== null
            ? $this->circuitBreaker->call($call)
            : $call();
    }

    public function updateLastSeenTag(int $id, string $tag): void
    {
        $call = function () use ($id, $tag): void {
            $this->http->request('PATCH', $this->config->baseUrl . "/internal/subscriptions/{$id}/last-seen-tag", [
                'headers' => array_merge($this->headers(), ['Content-Type' => 'application/json']),
                'json'    => ['tag' => $tag],
            ]);
        };

        if ($this->circuitBreaker !== null) {
            $this->circuitBreaker->call($call);
        } else {
            $call();
        }
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['X-API-Key' => $this->config->apiKey];
    }
}
