<?php

declare(strict_types=1);

namespace App\Tests\Support\Http;

use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\AccessTokenIssuer;
use App\Tests\Support\Builder\PlayerBuilder;
use Psr\Container\ContainerInterface;

/**
 * A player signed in for an HTTP test: unlike a builder, signing in has side effects —
 * the player is saved and an access token is issued.
 */
final readonly class AuthenticatedPlayer
{
    private function __construct(
        public string $token,
        public string $playerId,
    ) {
    }

    public static function signIn(ContainerInterface $container): self
    {
        $players = $container->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $playerId = $players->nextIdentity();
        $players->save(PlayerBuilder::aPlayer()->withId($playerId->value)->build());

        $accessTokenIssuer = $container->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);

        return new self($accessTokenIssuer->issue($playerId), $playerId->value);
    }

    /**
     * @return array{HTTP_AUTHORIZATION: string}
     */
    public function authorizationHeader(): array
    {
        return ['HTTP_AUTHORIZATION' => "Bearer {$this->token}"];
    }
}
