<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RequestToJoinTeam;

use App\Competition\Application\RequestToJoinTeam\RequestToJoinTeamCommand;
use App\Competition\Application\RequestToJoinTeam\RequestToJoinTeamHandler;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Builder\PlayerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestToJoinTeamHandlerTest extends TestCase
{
    #[Test]
    public function it_records_a_join_request_for_a_registered_team(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $players = new InMemoryPlayerRepository();
        $players->save(PlayerBuilder::aPlayer()->withId('applicant')->build());

        $handler = new RequestToJoinTeamHandler($competitions, $players);

        $handler(new RequestToJoinTeamCommand($competition->getId()->value, 'team-a', 'applicant'));

        self::assertCount(1, $competition->getTeamPendingRequests(new TeamId('team-a')));
    }

    #[Test]
    public function it_rejects_a_join_request_for_an_unknown_competition(): void
    {
        $handler = new RequestToJoinTeamHandler(new InMemoryCompetitionRepository(), new InMemoryPlayerRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RequestToJoinTeamCommand('unknown', 'team-a', 'applicant'));
    }

    #[Test]
    public function it_rejects_a_join_request_from_an_unknown_player(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RequestToJoinTeamHandler($competitions, new InMemoryPlayerRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RequestToJoinTeamCommand($competition->getId()->value, 'team-a', 'unknown-player'));
    }
}
