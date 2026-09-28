<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Infrastructure\Password\NativePasswordHasher;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrinePlayerRepository;
use App\Tests\Support\Builder\PlayerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    #[Test]
    public function it_logs_in_and_returns_an_access_token(): void
    {
        $client = static::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $players = new DoctrinePlayerRepository($entityManager);

        $players->save(PlayerBuilder::aPlayer()
            ->withEmail('captain@example.com')
            ->withHashedPassword((new NativePasswordHasher())->hash('super-secret'))
            ->build());

        $client->request('POST', '/players/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'captain@example.com',
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

        $client->request('POST', '/players/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'unknown@example.com',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(401);
    }
}
