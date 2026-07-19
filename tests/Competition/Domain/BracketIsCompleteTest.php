<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Round;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Model\SingleEliminationBracket;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BracketIsCompleteTest extends TestCase
{
    #[Test]
    public function it_is_not_complete_when_bracket_is_fresh(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        self::assertFalse($bracket->isComplete());
    }

    #[Test]
    public function it_is_not_complete_when_only_round_one_is_played(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(1, 0)));
        $bracket->recordResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(1, 0)));

        self::assertFalse($bracket->isComplete());
    }

    #[Test]
    public function it_is_complete_when_the_final_is_played(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(1, 0)));
        $bracket->recordResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(1, 0)));
        $bracket->recordResult(new EncounterId('enc-3'), EncounterResult::regularTime(Score::of(2, 1)));

        self::assertTrue($bracket->isComplete());
    }

    #[Test]
    public function it_is_complete_with_byes_when_the_final_is_played(): void
    {
        // 3 équipes : bye vs A (enc-1, bye encounter), B vs C (enc-2), finale A vs winner(enc-2) (enc-3)
        $bracket = $this->makeThreeTeamBracket();
        $bracket->recordResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(1, 0)));
        $bracket->recordResult(new EncounterId('enc-3'), EncounterResult::regularTime(Score::of(2, 1)));

        self::assertTrue($bracket->isComplete());
    }

    #[Test]
    public function it_exposes_the_champion(): void
    {
        ['bracket' => $bracket, 'teamA' => $teamA] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(1, 0))); // A gagne
        $bracket->recordResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(0, 1))); // D gagne
        $bracket->recordResult(new EncounterId('enc-3'), EncounterResult::regularTime(Score::of(2, 0))); // A gagne la finale

        self::assertSame($teamA, $bracket->getChampion());
    }

    #[Test]
    public function it_throws_when_getting_champion_before_completion(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        $this->expectException(\LogicException::class);

        $bracket->getChampion();
    }

    // --- helpers ---

    /** @return array{bracket: Bracket, teamA: TeamId, teamB: TeamId, teamC: TeamId, teamD: TeamId} */
    private function makeFourTeamBracket(): array
    {
        $teamA = new TeamId('a');
        $teamB = new TeamId('b');
        $teamC = new TeamId('c');
        $teamD = new TeamId('d');

        $enc1 = new Encounter(new EncounterId('enc-1'), Participant::forTeam($teamA), Participant::forTeam($teamB));
        $enc2 = new Encounter(new EncounterId('enc-2'), Participant::forTeam($teamC), Participant::forTeam($teamD));
        $enc3 = new Encounter(
            new EncounterId('enc-3'),
            Participant::pendingWinnerOf(new EncounterId('enc-1')),
            Participant::pendingWinnerOf(new EncounterId('enc-2')),
        );

        $bracket = new SingleEliminationBracket([
            new Round(1, [$enc1, $enc2]),
            new Round(2, [$enc3]),
        ]);

        return ['bracket' => $bracket, 'teamA' => $teamA, 'teamB' => $teamB, 'teamC' => $teamC, 'teamD' => $teamD];
    }

    private function makeThreeTeamBracket(): Bracket
    {
        $teamA = new TeamId('a');
        $teamB = new TeamId('b');
        $teamC = new TeamId('c');

        $enc1 = new Encounter(new EncounterId('enc-1'), Participant::bye(), Participant::forTeam($teamA));
        $enc2 = new Encounter(new EncounterId('enc-2'), Participant::forTeam($teamB), Participant::forTeam($teamC));
        $enc3 = new Encounter(
            new EncounterId('enc-3'),
            Participant::forTeam($teamA),
            Participant::pendingWinnerOf(new EncounterId('enc-2')),
        );

        return new SingleEliminationBracket([
            new Round(1, [$enc1, $enc2]),
            new Round(2, [$enc3]),
        ]);
    }
}
