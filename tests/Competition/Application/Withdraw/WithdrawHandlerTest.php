<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\Withdraw;

use App\Competition\Application\Withdraw\WithdrawCommand;
use App\Competition\Application\Withdraw\WithdrawHandler;
use App\Competition\Domain\Exception\NotAuthorizedToWithdrawException;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WithdrawHandlerTest extends TestCase
{
    #[Test]
    public function it_withdraws_a_registered_team_when_requested_by_its_captain(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions, $this->authorizationStub(true));

        $handler(new WithdrawCommand('c1', 't1', 'captain@example.com'));

        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawal_from_an_unknown_competition(): void
    {
        $handler = new WithdrawHandler(new InMemoryCompetitionRepository(), $this->authorizationStub(true));

        $this->expectException(\InvalidArgumentException::class);

        $handler(new WithdrawCommand('unknown', 't1', 'captain@example.com'));
    }

    #[Test]
    public function it_rejects_withdrawal_by_a_player_who_is_not_the_captain(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions, $this->authorizationStub(false));

        $this->expectException(NotAuthorizedToWithdrawException::class);

        $handler(new WithdrawCommand('c1', 't1', 'someone-else@example.com'));
    }

    #[Test]
    public function it_withdraws_a_registered_team_when_requested_by_the_owning_organizer(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions, $this->authorizationStub(true));

        $handler(new WithdrawCommand('c1', 't1', 'organizer-1'));

        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawal_by_an_organizer_who_does_not_own_the_organization(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions, $this->authorizationStub(false));

        $this->expectException(NotAuthorizedToWithdrawException::class);

        $handler(new WithdrawCommand('c1', 't1', 'someone-elses-organizer'));
    }

    private function authorizationStub(bool $authorized): OrganizerOrganizationAuthorization
    {
        $authorization = $this->createStub(OrganizerOrganizationAuthorization::class);
        $authorization->method('authorizes')->willReturn($authorized);

        return $authorization;
    }
}
