<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateCompetitionControllerTest extends WebTestCase
{
    #[Test]
    public function it_creates_a_competition(): void
    {
        $client = static::createClient();

        $client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 2,
            'maxTeams' => 4,
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }
}