<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RejectJoinRequest;

use App\Competition\Application\RejectJoinRequest\RejectJoinRequestCommand;
use App\Competition\Application\RejectJoinRequest\RejectJoinRequestHandler;
use App\Competition\Domain\Exception\NotAuthorizedToManageJoinRequestException;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RejectJoinRequestHandlerTest extends TestCase
{
    #[Test]
    public function it_rejects_a_pending_join_request_when_requested_by_the_captain(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competition->requestToJoinTeam(new TeamId('t1'), new PlayerId('applicant@example.com'));
        $competitions->save($competition);

        $handler = new RejectJoinRequestHandler($competitions);

        $handler(new RejectJoinRequestCommand('c1', 't1', 'applicant@example.com', 'captain@example.com'));

        self::assertCount(0, $competition->getTeamPendingRequests(new TeamId('t1')));
    }

    #[Test]
    public function it_rejects_rejection_for_an_unknown_competition(): void
    {
        $handler = new RejectJoinRequestHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RejectJoinRequestCommand('unknown', 't1', 'applicant@example.com', 'captain@example.com'));
    }

    #[Test]
    public function it_rejects_rejection_by_a_player_who_is_not_the_captain(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competition->requestToJoinTeam(new TeamId('t1'), new PlayerId('applicant@example.com'));
        $competitions->save($competition);

        $handler = new RejectJoinRequestHandler($competitions);

        $this->expectException(NotAuthorizedToManageJoinRequestException::class);

        $handler(new RejectJoinRequestCommand('c1', 't1', 'applicant@example.com', 'someone-else@example.com'));
    }
}
