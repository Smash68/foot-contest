<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RegisterTeam;

use App\Competition\Application\RegisterTeam\RegisterTeamCommand;
use App\Competition\Application\RegisterTeam\RegisterTeamHandler;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryTeamRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Builder\PlayerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegisterTeamHandlerTest extends TestCase
{
    #[Test]
    public function it_registers_a_team_to_an_open_competition(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $players = new InMemoryPlayerRepository();
        $players->save(PlayerBuilder::aPlayer()->withId('captain-a')->build());

        $handler = new RegisterTeamHandler($competitions, $players, new InMemoryTeamRepository());

        $handler(new RegisterTeamCommand($competition->getId()->value, 'Team A', 'captain-a'));

        self::assertSame(1, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_registration_for_an_unknown_competition(): void
    {
        $handler = new RegisterTeamHandler(
            new InMemoryCompetitionRepository(),
            new InMemoryPlayerRepository(),
            new InMemoryTeamRepository(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RegisterTeamCommand('unknown', 'Team A', 'captain-a'));
    }

    #[Test]
    public function it_rejects_registration_for_an_unknown_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RegisterTeamHandler(
            $competitions,
            new InMemoryPlayerRepository(),
            new InMemoryTeamRepository(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RegisterTeamCommand($competition->getId()->value, 'Team A', 'unknown-captain'));
    }

    #[Test]
    public function it_returns_the_registered_team_id(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $players = new InMemoryPlayerRepository();
        $players->save(PlayerBuilder::aPlayer()->withId('captain-a')->build());

        $handler = new RegisterTeamHandler($competitions, $players, new InMemoryTeamRepository());

        $teamId = $handler(new RegisterTeamCommand($competition->getId()->value, 'Team A', 'captain-a'));

        self::assertInstanceOf(TeamId::class, $teamId);
    }
}
