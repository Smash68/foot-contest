<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\ApproveJoinRequest;

use App\Competition\Application\ApproveJoinRequest\ApproveJoinRequestCommand;
use App\Competition\Application\ApproveJoinRequest\ApproveJoinRequestHandler;
use App\Competition\Domain\Exception\NotAuthorizedToManageJoinRequestException;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApproveJoinRequestHandlerTest extends TestCase
{
    #[Test]
    public function it_approves_a_pending_join_request_when_requested_by_the_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withPendingJoinRequest('team-a', 'applicant')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new ApproveJoinRequestHandler($competitions);

        $handler(new ApproveJoinRequestCommand($competition->getId()->value, 'team-a', 'applicant', 'captain-a'));

        self::assertCount(2, $competition->getTeamRoster(new TeamId('team-a')));
    }

    #[Test]
    public function it_rejects_approval_for_an_unknown_competition(): void
    {
        $handler = new ApproveJoinRequestHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new ApproveJoinRequestCommand('unknown', 'team-a', 'applicant', 'captain-a'));
    }

    #[Test]
    public function it_rejects_approval_by_a_player_who_is_not_the_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withPendingJoinRequest('team-a', 'applicant')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new ApproveJoinRequestHandler($competitions);

        $this->expectException(NotAuthorizedToManageJoinRequestException::class);

        $handler(new ApproveJoinRequestCommand($competition->getId()->value, 'team-a', 'applicant', 'someone-else'));
    }
}
