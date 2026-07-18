<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncounterTest extends TestCase
{
    #[Test]
    public function it_stores_result_when_completed(): void
    {
        $encounter = $this->makeConcreteEncounter();
        $result = EncounterResult::regularTime(Score::of(2, 1));

        $encounter->play($result);

        self::assertSame($result, $encounter->getResult());
        self::assertTrue($encounter->isCompleted());
    }

    #[Test]
    public function it_exposes_home_winner(): void
    {
        $teamA = new Team(new TeamId('a'), 'Team A');
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::forTeam($teamA),
            Participant::forTeam(new Team(new TeamId('b'), 'Team B')),
        );

        $encounter->play(EncounterResult::regularTime(Score::of(2, 0)));

        self::assertSame($teamA->getId(), $encounter->getWinner()->getId());
    }

    #[Test]
    public function it_exposes_away_winner(): void
    {
        $teamB = new Team(new TeamId('b'), 'Team B');
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::forTeam(new Team(new TeamId('a'), 'Team A')),
            Participant::forTeam($teamB),
        );

        $encounter->play(EncounterResult::regularTime(Score::of(0, 1)));

        self::assertSame($teamB->getId(), $encounter->getWinner()->getId());
    }

    #[Test]
    public function it_throws_when_getting_winner_before_completion(): void
    {
        $this->expectException(\LogicException::class);

        $this->makeConcreteEncounter()->getWinner();
    }

    #[Test]
    public function it_exposes_away_loser(): void
    {
        $teamB = new Team(new TeamId('b'), 'Team B');
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::forTeam(new Team(new TeamId('a'), 'Team A')),
            Participant::forTeam($teamB),
        );

        $encounter->play(EncounterResult::regularTime(Score::of(2, 0)));

        self::assertSame($teamB->getId(), $encounter->getLoser()->getId());
    }

    #[Test]
    public function it_exposes_home_loser(): void
    {
        $teamA = new Team(new TeamId('a'), 'Team A');
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::forTeam($teamA),
            Participant::forTeam(new Team(new TeamId('b'), 'Team B')),
        );

        $encounter->play(EncounterResult::regularTime(Score::of(0, 1)));

        self::assertSame($teamA->getId(), $encounter->getLoser()->getId());
    }

    #[Test]
    public function it_throws_when_getting_loser_before_completion(): void
    {
        $this->expectException(\LogicException::class);

        $this->makeConcreteEncounter()->getLoser();
    }

    #[Test]
    public function it_throws_when_completed_twice(): void
    {
        $encounter = $this->makeConcreteEncounter();
        $encounter->play(EncounterResult::regularTime(Score::of(1, 0)));

        $this->expectException(\LogicException::class);

        $encounter->play(EncounterResult::regularTime(Score::of(2, 0)));
    }

    #[Test]
    public function it_throws_when_completing_with_a_pending_home_participant(): void
    {
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::pendingWinnerOf(new EncounterId('prev-1')),
            Participant::forTeam(new Team(new TeamId('b'), 'Team B')),
        );

        $this->expectException(\LogicException::class);

        $encounter->play(EncounterResult::regularTime(Score::of(1, 0)));
    }

    #[Test]
    public function it_throws_when_completing_with_a_pending_away_participant(): void
    {
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::forTeam(new Team(new TeamId('a'), 'Team A')),
            Participant::pendingWinnerOf(new EncounterId('prev-2')),
        );

        $this->expectException(\LogicException::class);

        $encounter->play(EncounterResult::regularTime(Score::of(1, 0)));
    }

    #[Test]
    public function it_can_resolve_home_participant(): void
    {
        $winner = new Team(new TeamId('w'), 'Winner');
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::pendingWinnerOf(new EncounterId('prev-1')),
            Participant::forTeam(new Team(new TeamId('b'), 'Team B')),
        );

        $encounter->resolveHome(Participant::forTeam($winner));

        self::assertTrue($encounter->getHome()->isTeam());
        self::assertSame($winner->getId(), $encounter->getHome()->getTeam()->getId());
    }

    #[Test]
    public function it_can_resolve_away_participant(): void
    {
        $winner = new Team(new TeamId('w'), 'Winner');
        $encounter = new Encounter(
            new EncounterId('e1'),
            Participant::forTeam(new Team(new TeamId('a'), 'Team A')),
            Participant::pendingWinnerOf(new EncounterId('prev-2')),
        );

        $encounter->resolveAway(Participant::forTeam($winner));

        self::assertTrue($encounter->getAway()->isTeam());
        self::assertSame($winner->getId(), $encounter->getAway()->getTeam()->getId());
    }

    // --- helpers ---

    private function makeConcreteEncounter(): Encounter
    {
        return new Encounter(
            new EncounterId('e1'),
            Participant::forTeam(new Team(new TeamId('a'), 'Team A')),
            Participant::forTeam(new Team(new TeamId('b'), 'Team B')),
        );
    }
}
