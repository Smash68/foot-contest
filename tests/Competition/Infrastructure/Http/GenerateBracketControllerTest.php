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

final class GenerateBracketControllerTest extends WebTestCase
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
    public function it_generates_the_bracket(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket");

        self::assertResponseStatusCodeSame(401);
        self::assertNull($competition->getBracket());
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy('someone-elses-organization')
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_returns_401_for_a_captain_of_the_competition(): void
    {
        $captain = AuthenticatedPlayer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: $captain->playerId)
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: $captain->authorizationHeader());

        self::assertResponseStatusCodeSame(401);
        self::assertNull($competition->getBracket());
    }
}
