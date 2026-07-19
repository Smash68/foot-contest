<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain\Format\SingleElimination;

use App\Competition\Domain\Format\SingleElimination\BracketWithThirdPlaceMatch;
use App\Competition\Domain\Format\SingleElimination\ThirdPlaceFixture;
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

final class BracketWithThirdPlaceMatchTest extends TestCase
{
    #[Test]
    public function third_place_encounter_is_not_available_before_any_semi_final_is_played(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracketWithThirdPlaceMatch();

        self::assertNull($bracket->getThirdPlaceEncounter());
    }

    #[Test]
    public function third_place_encounter_is_not_available_when_only_one_semi_final_is_played(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracketWithThirdPlaceMatch();

        $bracket->recordResult(new EncounterId('semi-1'), EncounterResult::regularTime(Score::of(2, 0)));

        self::assertNull($bracket->getThirdPlaceEncounter());
    }

    #[Test]
    public function third_place_encounter_pits_the_two_semi_final_losers_once_both_are_played(): void
    {
        ['bracket' => $bracket, 'teamB' => $teamB, 'teamC' => $teamC] = $this->makeFourTeamBracketWithThirdPlaceMatch();

        $bracket->recordResult(new EncounterId('semi-1'), EncounterResult::regularTime(Score::of(2, 0))); // A beats B
        $bracket->recordResult(new EncounterId('semi-2'), EncounterResult::regularTime(Score::of(0, 1))); // D beats C

        $thirdPlaceEncounter = $bracket->getThirdPlaceEncounter();

        self::assertNotNull($thirdPlaceEncounter);
        self::assertSame($teamB, $thirdPlaceEncounter->getHome()->getTeamId());
        self::assertSame($teamC, $thirdPlaceEncounter->getAway()->getTeamId());
    }

    #[Test]
    public function third_place_encounter_can_be_played_through_record_result(): void
    {
        ['bracket' => $bracket, 'teamB' => $teamB] = $this->makeFourTeamBracketWithThirdPlaceMatch();
        $bracket->recordResult(new EncounterId('semi-1'), EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult(new EncounterId('semi-2'), EncounterResult::regularTime(Score::of(0, 1)));
        $thirdPlaceId = $bracket->getThirdPlaceEncounter()->id;

        $bracket->recordResult($thirdPlaceId, EncounterResult::regularTime(Score::of(3, 1)));

        self::assertSame($teamB, $bracket->getThirdPlaceEncounter()->getWinner());
    }

    #[Test]
    public function it_still_resolves_the_final_normally(): void
    {
        ['bracket' => $bracket, 'teamA' => $teamA] = $this->makeFourTeamBracketWithThirdPlaceMatch();
        $bracket->recordResult(new EncounterId('semi-1'), EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult(new EncounterId('semi-2'), EncounterResult::regularTime(Score::of(0, 1)));

        $bracket->recordResult(new EncounterId('final'), EncounterResult::regularTime(Score::of(1, 0)));

        self::assertTrue($bracket->isComplete());
        self::assertSame($teamA, $bracket->getChampion());
    }

    #[Test]
    public function playing_the_third_place_match_does_not_affect_tournament_completion(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracketWithThirdPlaceMatch();
        $bracket->recordResult(new EncounterId('semi-1'), EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult(new EncounterId('semi-2'), EncounterResult::regularTime(Score::of(0, 1)));
        $thirdPlaceId = $bracket->getThirdPlaceEncounter()->id;

        $bracket->recordResult($thirdPlaceId, EncounterResult::regularTime(Score::of(3, 1)));

        self::assertFalse($bracket->isComplete()); // final not played yet
    }

    // --- helpers ---

    /** @return array{bracket: BracketWithThirdPlaceMatch, teamA: TeamId, teamB: TeamId, teamC: TeamId, teamD: TeamId} */
    private function makeFourTeamBracketWithThirdPlaceMatch(): array
    {
        $teamA = new TeamId('a');
        $teamB = new TeamId('b');
        $teamC = new TeamId('c');
        $teamD = new TeamId('d');

        $semiFinal1 = new Encounter(new EncounterId('semi-1'), Participant::forTeam($teamA), Participant::forTeam($teamB));
        $semiFinal2 = new Encounter(new EncounterId('semi-2'), Participant::forTeam($teamC), Participant::forTeam($teamD));
        $final = new Encounter(
            new EncounterId('final'),
            Participant::pendingWinnerOf(new EncounterId('semi-1')),
            Participant::pendingWinnerOf(new EncounterId('semi-2')),
        );

        $innerBracket = new SingleEliminationBracket([
            new Round(1, [$semiFinal1, $semiFinal2]),
            new Round(2, [$final]),
        ]);

        $fixture = new ThirdPlaceFixture(
            new EncounterId('third-place'),
            new EncounterId('semi-1'),
            new EncounterId('semi-2'),
        );

        $bracket = new BracketWithThirdPlaceMatch($innerBracket, $fixture);

        return ['bracket' => $bracket, 'teamA' => $teamA, 'teamB' => $teamB, 'teamC' => $teamC, 'teamD' => $teamD];
    }
}
