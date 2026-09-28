<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RejectJoinRequest;

use App\Competition\Application\RejectJoinRequest\RejectJoinRequestCommand;
use App\Competition\Application\RejectJoinRequest\RejectJoinRequestHandler;
use App\Competition\Domain\Exception\NotAuthorizedToManageJoinRequestException;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RejectJoinRequestHandlerTest extends TestCase
{
    #[Test]
    public function it_rejects_a_pending_join_request_when_requested_by_the_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withPendingJoinRequest('team-a', 'applicant')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RejectJoinRequestHandler($competitions);

        $handler(new RejectJoinRequestCommand($competition->getId()->value, 'team-a', 'applicant', 'captain-a'));

        self::assertCount(0, $competition->getTeamPendingRequests(new TeamId('team-a')));
    }

    #[Test]
    public function it_rejects_rejection_for_an_unknown_competition(): void
    {
        $handler = new RejectJoinRequestHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RejectJoinRequestCommand('unknown', 'team-a', 'applicant', 'captain-a'));
    }

    #[Test]
    public function it_rejects_rejection_by_a_player_who_is_not_the_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withPendingJoinRequest('team-a', 'applicant')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RejectJoinRequestHandler($competitions);

        $this->expectException(NotAuthorizedToManageJoinRequestException::class);

        $handler(new RejectJoinRequestCommand($competition->getId()->value, 'team-a', 'applicant', 'someone-else'));
    }
}
