<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Tests\Support\Http\AuthenticatedOrganizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateCompetitionControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    #[Test]
    public function it_creates_a_competition(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->postCompetition($this->validPayload($organizer->organizationId), $organizer);

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $this->postCompetition($this->validPayload('org-1'));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->postCompetition($this->validPayload('someone-elses-organization'), $organizer);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_returns_422_when_team_capacity_is_invalid(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->postCompetition([...$this->validPayload($organizer->organizationId), 'minTeams' => 1], $organizer);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_required_field_is_missing(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->postCompetition($this->withoutField($this->validPayload($organizer->organizationId), 'name'), $organizer);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_the_minimum_roster_size_is_missing(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->postCompetition($this->withoutField($this->validPayload($organizer->organizationId), 'minRosterSize'), $organizer);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_field_has_the_wrong_type(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->postCompetition([...$this->validPayload($organizer->organizationId), 'minTeams' => 'not-a-number'], $organizer);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_400_when_the_request_body_is_malformed_json(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            ...$organizer->authorizationHeader(),
        ], content: '{not valid json');

        self::assertResponseStatusCodeSame(400);
    }

    /** @return array<string, mixed> */
    private function validPayload(string $organizationId): array
    {
        return [
            'name' => 'Summer Cup',
            'minTeams' => 2,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'minRosterSize' => 1,
            'organizationId' => $organizationId,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function withoutField(array $payload, string $field): array
    {
        unset($payload[$field]);

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function postCompetition(array $payload, ?AuthenticatedOrganizer $organizer = null): void
    {
        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            ...($organizer?->authorizationHeader() ?? []),
        ], content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
