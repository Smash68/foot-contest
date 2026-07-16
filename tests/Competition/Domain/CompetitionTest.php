<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Registration;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompetitionTest extends TestCase
{
    #[Test]
    public function it_exposes_its_id(): void
    {
        $id = new CompetitionId('t1');
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));

        self::assertTrue($competition->getId()->equals($id));
    }

    #[Test]
    public function it_starts_open_for_registration_with_no_teams(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        self::assertTrue($competition->isOpenForRegistration());
        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_counts_a_registered_team(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        $competition->register($this->registration('a', 'Team A'));

        self::assertSame(1, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_a_registration_once_the_maximum_is_reached(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 2));
        $competition->register($this->registration('a', 'Team A'));
        $competition->register($this->registration('b', 'Team B'));

        $this->expectException(\LogicException::class);

        $competition->register($this->registration('c', 'Team C'));
    }

    #[Test]
    public function it_rejects_a_registration_once_closed(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));
        $competition->register($this->registration('b', 'Team B'));
        $competition->closeRegistration();

        $this->expectException(\LogicException::class);

        $competition->register($this->registration('c', 'Team C'));
    }

    #[Test]
    public function it_removes_a_withdrawn_team(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));

        $competition->withdraw(new TeamId('a'));

        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawing_an_unregistered_team(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        $this->expectException(\InvalidArgumentException::class);

        $competition->withdraw(new TeamId('a'));
    }

    #[Test]
    public function it_rejects_withdrawing_once_closed(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));
        $competition->register($this->registration('b', 'Team B'));
        $competition->closeRegistration();

        $this->expectException(\LogicException::class);

        $competition->withdraw(new TeamId('a'));
    }

    #[Test]
    public function it_rejects_registering_the_same_team_twice(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));

        $this->expectException(\LogicException::class);

        $competition->register($this->registration('a', 'Team A'));
    }

    #[Test]
    public function it_rejects_closing_registration_below_the_minimum(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));

        $this->expectException(\LogicException::class);

        $competition->closeRegistration();
    }

    #[Test]
    public function it_rejects_generating_the_bracket_while_open(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));
        $competition->register($this->registration('b', 'Team B'));

        $this->expectException(\LogicException::class);

        $competition->generateBracket(new SingleEliminationBracketGenerator());
    }

    #[Test]
    public function it_generates_the_bracket_from_registered_teams(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));
        $competition->register($this->registration('b', 'Team B'));
        $competition->closeRegistration();

        $competition->generateBracket(new SingleEliminationBracketGenerator());

        self::assertSame(1, $competition->getBracket()->countEncounters());
    }

    #[Test]
    public function it_rejects_generating_the_bracket_twice(): void
    {
        $competition = Competition::create(new CompetitionId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register($this->registration('a', 'Team A'));
        $competition->register($this->registration('b', 'Team B'));
        $competition->closeRegistration();
        $competition->generateBracket(new SingleEliminationBracketGenerator());

        $this->expectException(\LogicException::class);

        $competition->generateBracket(new SingleEliminationBracketGenerator());
    }

    private function registration(string $teamId, string $teamName): Registration
    {
        $team = new Team(new TeamId($teamId), $teamName);
        $captain = new Player(new PlayerId("{$teamId}@example.com"), 'Captain ' . $teamName);

        return new Registration($team, $captain);
    }
}
