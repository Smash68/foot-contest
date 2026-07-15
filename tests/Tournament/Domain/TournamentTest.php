<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\Player;
use App\Tournament\Domain\Model\PlayerId;
use App\Tournament\Domain\Model\Registration;
use App\Tournament\Domain\Model\Team;
use App\Tournament\Domain\Model\TeamCapacity;
use App\Tournament\Domain\Model\TeamId;
use App\Tournament\Domain\Model\Tournament;
use App\Tournament\Domain\Model\TournamentId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TournamentTest extends TestCase
{
    #[Test]
    public function it_starts_open_for_registration_with_no_teams(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        self::assertTrue($tournament->isOpenForRegistration());
        self::assertSame(0, $tournament->countRegistrations());
    }

    #[Test]
    public function it_counts_a_registered_team(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        $tournament->register($this->registration('a', 'Team A'));

        self::assertSame(1, $tournament->countRegistrations());
    }

    #[Test]
    public function it_rejects_a_registration_once_the_maximum_is_reached(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 2));
        $tournament->register($this->registration('a', 'Team A'));
        $tournament->register($this->registration('b', 'Team B'));

        $this->expectException(\LogicException::class);

        $tournament->register($this->registration('c', 'Team C'));
    }

    #[Test]
    public function it_rejects_a_registration_once_closed(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $tournament->register($this->registration('a', 'Team A'));
        $tournament->register($this->registration('b', 'Team B'));
        $tournament->closeRegistration();

        $this->expectException(\LogicException::class);

        $tournament->register($this->registration('c', 'Team C'));
    }

    #[Test]
    public function it_removes_a_withdrawn_team(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $tournament->register($this->registration('a', 'Team A'));

        $tournament->withdraw(new TeamId('a'));

        self::assertSame(0, $tournament->countRegistrations());
    }

    #[Test]
    public function it_rejects_withdrawing_an_unregistered_team(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        $this->expectException(\InvalidArgumentException::class);

        $tournament->withdraw(new TeamId('a'));
    }

    #[Test]
    public function it_rejects_withdrawing_once_closed(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $tournament->register($this->registration('a', 'Team A'));
        $tournament->closeRegistration();

        $this->expectException(\LogicException::class);

        $tournament->withdraw(new TeamId('a'));
    }

    #[Test]
    public function it_rejects_registering_the_same_team_twice(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));
        $tournament->register($this->registration('a', 'Team A'));

        $this->expectException(\LogicException::class);

        $tournament->register($this->registration('a', 'Team A'));
    }

    private function registration(string $teamId, string $teamName): Registration
    {
        $team = new Team(new TeamId($teamId), $teamName);
        $captain = new Player(new PlayerId("{$teamId}@example.com"), 'Captain ' . $teamName);

        return new Registration($team, $captain);
    }
}
