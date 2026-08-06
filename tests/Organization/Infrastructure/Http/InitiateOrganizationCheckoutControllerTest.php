<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Http;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizerRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InitiateOrganizationCheckoutControllerTest extends WebTestCase
{
    #[Test]
    public function it_initiates_a_checkout_for_the_authenticated_organizer(): void
    {
        $client = static::createClient();

        $organizers = new InMemoryOrganizerRepository();
        self::getContainer()->set(OrganizerRepository::class, $organizers);
        $organizerId = $organizers->nextIdentity();
        $organizers->save(Organizer::register($organizerId, 'organizer@example.com', 'hashed-password'));

        $accessTokenIssuer = self::getContainer()->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);
        $token = $accessTokenIssuer->issue($organizerId);

        $client->request('POST', '/organizations/checkout', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'organizationName' => 'Ligue amateur du Nord',
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('checkoutReference', $payload);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $client = static::createClient();

        $client->request('POST', '/organizations/checkout', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'organizationName' => 'Ligue amateur du Nord',
        ]));

        self::assertResponseStatusCodeSame(401);
    }
}
