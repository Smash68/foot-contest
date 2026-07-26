<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RegisterTeam;

use App\Competition\Application\RegisterTeam\RegisterTeamCommand;
use App\Competition\Application\RegisterTeam\RegisterTeamHandler;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryTeamRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegisterTeamHandlerTest extends TestCase
{
    #[Test]
    public function it_registers_a_team_to_an_open_competition(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competitions->save($competition);

        $players = new InMemoryPlayerRepository();
        $captain = new Player(new PlayerId('captain@example.com'), 'Captain');
        $players->save($captain);

        $handler = new RegisterTeamHandler($competitions, $players, new InMemoryTeamRepository());

        $handler(new RegisterTeamCommand('c1', 'Team A', 'captain@example.com'));

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

        $handler(new RegisterTeamCommand('unknown', 'Team A', 'captain@example.com'));
    }

    #[Test]
    public function it_rejects_registration_for_an_unknown_captain(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save(Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4)));

        $handler = new RegisterTeamHandler(
            $competitions,
            new InMemoryPlayerRepository(),
            new InMemoryTeamRepository(),
        );

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RegisterTeamCommand('c1', 'Team A', 'unknown@example.com'));
    }

    #[Test]
    public function it_returns_the_registered_team_id(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save(Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4)));

        $players = new InMemoryPlayerRepository();
        $players->save(new Player(new PlayerId('captain@example.com'), 'Captain'));

        $handler = new RegisterTeamHandler($competitions, $players, new InMemoryTeamRepository());

        $teamId = $handler(new RegisterTeamCommand('c1', 'Team A', 'captain@example.com'));

        self::assertInstanceOf(TeamId::class, $teamId);
    }
}
