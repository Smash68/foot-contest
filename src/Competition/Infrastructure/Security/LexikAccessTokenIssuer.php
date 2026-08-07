<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Security;

use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Service\AccessTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class LexikAccessTokenIssuer implements AccessTokenIssuer
{
    public function __construct(private JWTTokenManagerInterface $jwtTokenManager)
    {
    }

    public function issue(PlayerId $playerId): string
    {
        return $this->jwtTokenManager->create(new SecurityPlayer($playerId));
    }
}
