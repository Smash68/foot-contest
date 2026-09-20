<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RemovePlayerFromTeam;

use App\Competition\Application\RemovePlayerFromTeam\RemovePlayerFromTeamCommand;
use App\Competition\Application\RemovePlayerFromTeam\RemovePlayerFromTeamHandler;
use App\Competition\Domain\Exception\NotAuthorizedToRemoveTeamMemberException;
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
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemovePlayerFromTeamHandlerTest extends TestCase
{
    #[Test]
    public function it_removes_a_player_who_requests_their_own_departure(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competition->requestToJoinTeam(new TeamId('t1'), new PlayerId('member@example.com'));
        $competition->approveJoinRequest(new TeamId('t1'), new PlayerId('member@example.com'));
        $competitions->save($competition);

        $handler = new RemovePlayerFromTeamHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $handler(new RemovePlayerFromTeamCommand('c1', 't1', 'member@example.com', 'member@example.com'));

        self::assertCount(1, $competition->getTeamRoster(new TeamId('t1')));
    }

    #[Test]
    public function it_rejects_removal_for_an_unknown_competition(): void
    {
        $handler = new RemovePlayerFromTeamHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RemovePlayerFromTeamCommand('unknown', 't1', 'member@example.com', 'member@example.com'));
    }

    #[Test]
    public function it_rejects_removal_by_an_actor_who_is_neither_the_player_the_captain_nor_an_authorized_organizer(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competition->requestToJoinTeam(new TeamId('t1'), new PlayerId('member@example.com'));
        $competition->approveJoinRequest(new TeamId('t1'), new PlayerId('member@example.com'));
        $competitions->save($competition);

        $handler = new RemovePlayerFromTeamHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(NotAuthorizedToRemoveTeamMemberException::class);

        $handler(new RemovePlayerFromTeamCommand('c1', 't1', 'member@example.com', 'someone-else@example.com'));
    }
}
