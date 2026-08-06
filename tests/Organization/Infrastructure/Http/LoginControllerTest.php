<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Http;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Infrastructure\Password\NativePasswordHasher;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizerRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    #[Test]
    public function it_logs_in_and_returns_an_access_token(): void
    {
        $client = static::createClient();

        $organizers = new InMemoryOrganizerRepository();
        self::getContainer()->set(OrganizerRepository::class, $organizers);

        $hashedPassword = (new NativePasswordHasher())->hash('super-secret');
        $organizer = Organizer::register($organizers->nextIdentity(), 'organizer@example.com', $hashedPassword);
        $organizers->save($organizer);

        $client->request('POST', '/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'organizer@example.com',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $payload);
    }

    #[Test]
    public function it_returns_401_when_credentials_are_invalid(): void
    {
        $client = static::createClient();

        $organizers = new InMemoryOrganizerRepository();
        self::getContainer()->set(OrganizerRepository::class, $organizers);

        $client->request('POST', '/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'unknown@example.com',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(401);
    }
}
