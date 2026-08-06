<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Security;

use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Service\AccessTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class LexikAccessTokenIssuer implements AccessTokenIssuer
{
    public function __construct(private JWTTokenManagerInterface $jwtTokenManager)
    {
    }

    public function issue(OrganizerId $organizerId): string
    {
        return $this->jwtTokenManager->create(new SecurityOrganizer($organizerId));
    }
}
