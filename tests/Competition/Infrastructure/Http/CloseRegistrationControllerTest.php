<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Http\AuthenticatedOrganizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CloseRegistrationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private InMemoryCompetitionRepository $competitions;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $this->competitions);
    }

    #[Test]
    public function it_closes_registration(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_409_naming_the_incomplete_teams(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withMinimumRosterSize(2)
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', 'player-a')
            ->withTeam('Team B', captainId: 'captain-b', id: 'team-b')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(409);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertIsArray($payload);
        self::assertArrayHasKey('error', $payload);
        self::assertSame([['id' => 'team-b', 'name' => 'Team B']], $payload['incompleteTeams'] ?? null);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration");

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()->ownedBy('someone-elses-organization')->build();
        $this->competitions->save($competition);

        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(403);
    }
}
