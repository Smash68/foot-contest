<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CloseRegistration;

use App\Competition\Application\CloseRegistration\CloseRegistrationCommand;
use App\Competition\Application\CloseRegistration\CloseRegistrationHandler;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CloseRegistrationHandlerTest extends TestCase
{
    #[Test]
    public function it_closes_registration_for_an_eligible_competition(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competitions->save($competition);

        $handler = new CloseRegistrationHandler($competitions);

        $handler(new CloseRegistrationCommand('c1'));

        self::assertFalse($competition->isOpenForRegistration());
    }

    #[Test]
    public function it_rejects_closing_an_unknown_competition(): void
    {
        $handler = new CloseRegistrationHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CloseRegistrationCommand('unknown'));
    }
}
