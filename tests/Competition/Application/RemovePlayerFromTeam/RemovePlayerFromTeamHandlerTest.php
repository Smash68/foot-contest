<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RemovePlayerFromTeam;

use App\Competition\Application\RemovePlayerFromTeam\RemovePlayerFromTeamCommand;
use App\Competition\Application\RemovePlayerFromTeam\RemovePlayerFromTeamHandler;
use App\Competition\Domain\Exception\NotAuthorizedToRemoveTeamMemberException;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemovePlayerFromTeamHandlerTest extends TestCase
{
    #[Test]
    public function it_removes_a_player_who_requests_their_own_departure(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', 'member')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RemovePlayerFromTeamHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $handler(new RemovePlayerFromTeamCommand($competition->getId()->value, 'team-a', 'member', 'member'));

        self::assertCount(1, $competition->getTeamRoster(new TeamId('team-a')));
    }

    #[Test]
    public function it_rejects_removal_for_an_unknown_competition(): void
    {
        $handler = new RemovePlayerFromTeamHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RemovePlayerFromTeamCommand('unknown', 'team-a', 'member', 'member'));
    }

    #[Test]
    public function it_rejects_removal_by_an_actor_who_is_neither_the_player_the_captain_nor_an_authorized_organizer(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', 'member')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RemovePlayerFromTeamHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(NotAuthorizedToRemoveTeamMemberException::class);

        $handler(new RemovePlayerFromTeamCommand($competition->getId()->value, 'team-a', 'member', 'someone-else'));
    }
}
