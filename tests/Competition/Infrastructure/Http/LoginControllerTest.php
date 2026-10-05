<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Infrastructure\Password\NativePasswordHasher;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrinePlayerRepository;
use App\Tests\Support\Builder\PlayerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    #[Test]
    public function it_logs_in_and_returns_an_access_token(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        assert($entityManager instanceof EntityManagerInterface);
        $players = new DoctrinePlayerRepository($entityManager);

        $players->save(PlayerBuilder::aPlayer()
            ->withEmail('captain@example.com')
            ->withHashedPassword((new NativePasswordHasher())->hash('super-secret'))
            ->build());

        $this->client->request('POST', '/players/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'captain@example.com',
            'password' => 'super-secret',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('token', $payload);
    }

    #[Test]
    public function it_returns_401_when_credentials_are_invalid(): void
    {
        $this->client->request('POST', '/players/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'unknown@example.com',
            'password' => 'super-secret',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }
}
