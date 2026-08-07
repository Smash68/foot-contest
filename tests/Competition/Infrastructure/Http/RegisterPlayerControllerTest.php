<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegisterPlayerControllerTest extends WebTestCase
{
    #[Test]
    public function it_registers_a_player(): void
    {
        $client = static::createClient();

        $client->request('POST', '/players', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Captain',
            'email' => 'captain@example.com',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }

    #[Test]
    public function it_returns_422_when_the_email_is_invalid(): void
    {
        $client = static::createClient();

        $client->request('POST', '/players', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Captain',
            'email' => 'not-an-email',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_required_field_is_missing(): void
    {
        $client = static::createClient();

        $client->request('POST', '/players', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'email' => 'captain@example.com',
            'password' => 'super-secret',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_400_when_the_request_body_is_malformed_json(): void
    {
        $client = static::createClient();

        $client->request('POST', '/players', server: ['CONTENT_TYPE' => 'application/json'], content: '{not valid json');

        self::assertResponseStatusCodeSame(400);
    }
}
