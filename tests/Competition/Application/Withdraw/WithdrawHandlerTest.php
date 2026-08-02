<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\Withdraw;

use App\Competition\Application\Withdraw\WithdrawCommand;
use App\Competition\Application\Withdraw\WithdrawHandler;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WithdrawHandlerTest extends TestCase
{
    #[Test]
    public function it_withdraws_a_registered_team(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain@example.com')));
        $competitions->save($competition);

        $handler = new WithdrawHandler($competitions);

        $handler(new WithdrawCommand('c1', 't1'));

        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawal_from_an_unknown_competition(): void
    {
        $handler = new WithdrawHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new WithdrawCommand('unknown', 't1'));
    }
}
