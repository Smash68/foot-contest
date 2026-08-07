<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Player;
use App\Competition\Infrastructure\Password\NativePasswordHasher;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrinePlayerRepository;
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

        $hashedPassword = (new NativePasswordHasher())->hash('super-secret');
        $players->save(Player::register($players->nextIdentity(), 'Captain', 'captain@example.com', $hashedPassword));

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
