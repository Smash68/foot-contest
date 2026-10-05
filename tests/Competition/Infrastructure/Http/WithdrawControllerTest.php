<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Http\AuthenticatedOrganizer;
use App\Tests\Support\Http\AuthenticatedPlayer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WithdrawControllerTest extends WebTestCase
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
    public function it_withdraws_a_team_when_requested_by_its_captain(): void
    {
        $captain = AuthenticatedPlayer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: $captain->playerId, id: 'team-a')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a", server: $captain->authorizationHeader());

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_withdraws_a_team_when_requested_by_the_owning_organizer(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a");

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_neither_the_captain_nor_the_owning_organizer(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy('someone-elses-organization')
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $this->competitions->save($competition);

        $player = AuthenticatedPlayer::signIn(self::getContainer());

        $this->client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a", server: $player->authorizationHeader());

        self::assertResponseStatusCodeSame(403);
    }
}
