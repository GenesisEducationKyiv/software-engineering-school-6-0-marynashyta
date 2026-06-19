<?php

declare(strict_types=1);

namespace App\Modules\Subscription\Infrastructure\Http;

use App\Modules\Subscription\Domain\SubscriptionScanRepositoryInterface;
use App\SharedKernel\Infrastructure\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class InternalSubscriptionController
{
    public function __construct(
        private readonly SubscriptionScanRepositoryInterface $repository,
    ) {
    }

    public function confirmed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $subscriptions = $this->repository->findAllConfirmed();

        $data = array_map(fn ($sub) => [
            'id'                => $sub->id,
            'email'             => $sub->email,
            'repo'              => $sub->repo,
            'last_seen_tag'     => $sub->lastSeenTag,
            'unsubscribe_token' => $sub->unsubscribeToken,
        ], $subscriptions);

        $response->getBody()->write(Json::encode(['subscriptions' => $data]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array<string, string> $args
     */
    public function updateLastSeenTag(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args,
    ): ResponseInterface {
        $id = (int) $args['id'];

        /** @var array{tag?: mixed} $body */
        $body = (array) $request->getParsedBody();
        $tag  = $body['tag'] ?? null;

        if (!is_string($tag) || $tag === '') {
            $response->getBody()->write(Json::encode(['error' => 'tag is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $this->repository->updateLastSeenTag($id, $tag);

        return $response->withStatus(204);
    }
}
