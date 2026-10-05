<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
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
        [$token, $organizationId] = $this->authenticatedOrganizer();

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 2,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => $organizationId,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $this->client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 2,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => 'org-1',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        [$token] = $this->authenticatedOrganizer();

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 2,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => 'someone-elses-organization',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_returns_422_when_team_capacity_is_invalid(): void
    {
        [$token, $organizationId] = $this->authenticatedOrganizer();

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 1,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => $organizationId,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_required_field_is_missing(): void
    {
        [$token, $organizationId] = $this->authenticatedOrganizer();

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'minTeams' => 2,
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => $organizationId,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_422_when_a_field_has_the_wrong_type(): void
    {
        [$token, $organizationId] = $this->authenticatedOrganizer();

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 'not-a-number',
            'maxTeams' => 4,
            'format' => 'single_elimination',
            'includeThirdPlaceMatch' => false,
            'organizationId' => $organizationId,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function it_returns_400_when_the_request_body_is_malformed_json(): void
    {
        [$token] = $this->authenticatedOrganizer();

        $this->client->request('POST', '/competitions', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: '{not valid json');

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function authenticatedOrganizer(): array
    {
        $organizers = self::getContainer()->get(OrganizerRepository::class);
        assert($organizers instanceof OrganizerRepository);
        $organizerId = $organizers->nextIdentity();
        $organizers->save(Organizer::register($organizerId, 'organizer@example.com', 'hashed-password'));

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        assert($organizations instanceof OrganizationRepository);
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', $organizerId));

        $accessTokenIssuer = self::getContainer()->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);
        $token = $accessTokenIssuer->issue($organizerId);

        return [$token, $organizationId->value];
    }
}
