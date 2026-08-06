<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Security;

use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Infrastructure\Security\LexikAccessTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LexikAccessTokenIssuerTest extends KernelTestCase
{
    #[Test]
    public function it_issues_a_jwt_carrying_the_organizer_identifier(): void
    {
        $jwtTokenManager = self::getContainer()->get(JWTTokenManagerInterface::class);
        assert($jwtTokenManager instanceof JWTTokenManagerInterface);
        $issuer = new LexikAccessTokenIssuer($jwtTokenManager);
        $organizerId = new OrganizerId('11111111-1111-1111-1111-111111111111');

        $token = $issuer->issue($organizerId);

        $payload = $jwtTokenManager->parse($token);
        self::assertSame($organizerId->value, $payload['username']);
    }
}
