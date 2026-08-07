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
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => 'org-1',
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }

    #[Test]
    public function it_returns_422_when_team_capacity_is_invalid(): void
    {
        $client = static::createClient();

        $client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 1,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => 'org-1',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_required_field_is_missing(): void
    {
        $client = static::createClient();

        $client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'minTeams' => 2,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => 'org-1',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_field_has_the_wrong_type(): void
    {
        $client = static::createClient();

        $client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 'not-a-number',
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => 'org-1',
        ]));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_400_when_the_request_body_is_malformed_json(): void
    {
        $client = static::createClient();

        $client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: '{not valid json');

        self::assertResponseStatusCodeSame(400);
    }
}
