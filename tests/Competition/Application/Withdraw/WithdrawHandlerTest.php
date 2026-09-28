<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\Withdraw;

use App\Competition\Application\Withdraw\WithdrawCommand;
use App\Competition\Application\Withdraw\WithdrawHandler;
use App\Competition\Domain\Exception\NotAuthorizedToWithdrawException;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WithdrawHandlerTest extends TestCase
{
    #[Test]
    public function it_withdraws_a_registered_team_when_requested_by_its_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $handler(new WithdrawCommand($competition->getId()->value, 'team-a', 'captain-a'));

        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawal_from_an_unknown_competition(): void
    {
        $handler = new WithdrawHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new WithdrawCommand('unknown', 'team-a', 'captain-a'));
    }

    #[Test]
    public function it_rejects_withdrawal_by_a_player_who_is_not_the_captain(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(NotAuthorizedToWithdrawException::class);

        $handler(new WithdrawCommand($competition->getId()->value, 'team-a', 'someone-else'));
    }

    #[Test]
    public function it_withdraws_a_registered_team_when_requested_by_the_owning_organizer(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', $competition->getOrganizationId());

        $handler = new WithdrawHandler($competitions, $authorization);

        $handler(new WithdrawCommand($competition->getId()->value, 'team-a', 'organizer-1'));

        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawal_by_an_organizer_who_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('the-real-owner', $competition->getOrganizationId());

        $handler = new WithdrawHandler($competitions, $authorization);

        $this->expectException(NotAuthorizedToWithdrawException::class);

        $handler(new WithdrawCommand($competition->getId()->value, 'team-a', 'someone-elses-organizer'));
    }
}
