<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RequestToJoinTeam;

use App\Competition\Application\RequestToJoinTeam\RequestToJoinTeamCommand;
use App\Competition\Application\RequestToJoinTeam\RequestToJoinTeamHandler;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestToJoinTeamHandlerTest extends TestCase
{
    #[Test]
    public function it_records_a_join_request_for_a_registered_team(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $players = new InMemoryPlayerRepository();
        $applicantId = $players->nextIdentity();
        $players->save(Player::register($applicantId, 'Applicant', 'applicant@example.com', 'hashed-password'));

        $handler = new RequestToJoinTeamHandler($competitions, $players);

        $handler(new RequestToJoinTeamCommand('c1', 't1', $applicantId->value));

        self::assertCount(1, $competition->getTeamPendingRequests(new TeamId('t1')));
    }

    #[Test]
    public function it_rejects_a_join_request_for_an_unknown_competition(): void
    {
        $handler = new RequestToJoinTeamHandler(new InMemoryCompetitionRepository(), new InMemoryPlayerRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RequestToJoinTeamCommand('unknown', 't1', 'applicant@example.com'));
    }

    #[Test]
    public function it_rejects_a_join_request_from_an_unknown_player(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $players = new InMemoryPlayerRepository();
        $handler = new RequestToJoinTeamHandler($competitions, $players);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RequestToJoinTeamCommand('c1', 't1', $players->nextIdentity()->value));
    }
}
