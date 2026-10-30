<?php

declare(strict_types=1);

namespace TacraftSociality\MicrosoftOAuth\Api;

use Flarum\Http\RequestUtil;
use Flarum\User\LoginProvider;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Reports whether the current user has a Microsoft account linked.
 */
class LinkStatusController implements RequestHandlerInterface
{
    public const PROVIDER = 'microsoft';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        /** @var LoginProvider|null $provider */
        $provider = $actor->loginProviders()
            ->where('provider', self::PROVIDER)
            ->first();

        return new JsonResponse([
            'linked' => $provider !== null,
            'identifier' => $provider === null ? null : self::mask((string) $provider->identifier),
            'createdAt' => $provider?->created_at?->toIso8601String(),
            'lastLoginAt' => $provider?->last_login_at?->toIso8601String(),
        ]);
    }

    /**
     * Microsoft object IDs are GUIDs; only expose the ends.
     */
    private static function mask(string $identifier): string
    {
        if (strlen($identifier) <= 8) {
            return str_repeat('*', strlen($identifier));
        }

        return substr($identifier, 0, 4).'****'.substr($identifier, -4);
    }
}
