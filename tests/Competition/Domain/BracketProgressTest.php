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
use App\Tests\Support\Assertion\EncounterAssert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BracketProgressTest extends TestCase
{
    #[Test]
    public function home_winner_advances_to_next_round(): void
    {
        ['bracket' => $bracket, 'teamA' => $teamA] = $this->makeFourTeamBracket();

        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(2, 0)));

        $encounter = $bracket->getRound(2)->getEncounters()[0];
        EncounterAssert::assertThat($encounter)->homeIsForTeam($teamA);
    }

    #[Test]
    public function away_winner_advances_to_next_round(): void
    {
        ['bracket' => $bracket, 'teamB' => $teamB] = $this->makeFourTeamBracket();

        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(0, 3)));

        $encounter = $bracket->getRound(2)->getEncounters()[0];
        EncounterAssert::assertThat($encounter)->homeIsForTeam($teamB);
    }

    #[Test]
    public function other_pending_participants_remain_untouched(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(2, 0)));

        // enc-2 not yet played — away participant of the final still pending
        $encounter = $bracket->getRound(2)->getEncounters()[0];
        EncounterAssert::assertThat($encounter)->awayIsPending();
    }

    #[Test]
    public function it_throws_when_encounter_not_found(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        $this->expectException(\InvalidArgumentException::class);

        $bracket->recordResult(new EncounterId('enc-999'), EncounterResult::regularTime(Score::of(1, 0)));
    }

    #[Test]
    public function it_rejects_a_draw_without_playing_the_encounter(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        try {
            $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(1, 1)));
            self::fail('A draw should have been rejected.');
        } catch (\InvalidArgumentException) {
        }

        self::assertFalse($bracket->getRound(1)->getEncounters()[0]->isCompleted());
    }

    #[Test]
    public function it_rejects_penalties_without_extra_time_without_playing_the_encounter(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        try {
            $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::afterPenalties(Score::of(1, 1), null, Score::of(4, 3)));
            self::fail('Penalties without extra time should have been rejected.');
        } catch (\InvalidArgumentException) {
        }

        self::assertFalse($bracket->getRound(1)->getEncounters()[0]->isCompleted());
    }

    #[Test]
    public function it_replaces_the_result_of_a_corrected_encounter(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(2, 0)));
        $corrected = EncounterResult::regularTime(Score::of(3, 0));

        $bracket->correctResult(new EncounterId('enc-1'), $corrected);

        self::assertSame($corrected, $bracket->getRound(1)->getEncounters()[0]->getResult());
    }

    #[Test]
    public function the_new_winner_of_a_corrected_encounter_advances_to_next_round(): void
    {
        ['bracket' => $bracket, 'teamB' => $teamB] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(2, 0)));

        $bracket->correctResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(0, 2)));

        $encounter = $bracket->getRound(2)->getEncounters()[0];
        EncounterAssert::assertThat($encounter)->homeIsForTeam($teamB);
    }

    #[Test]
    public function correcting_an_encounter_leaves_the_other_qualifier_untouched(): void
    {
        ['bracket' => $bracket, 'teamA' => $teamA, 'teamD' => $teamD] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(2, 0)));

        $bracket->correctResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(0, 2)));

        $encounter = $bracket->getRound(2)->getEncounters()[0];
        EncounterAssert::assertThat($encounter)->homeIsForTeam($teamA)->awayIsForTeam($teamD);
    }

    #[Test]
    public function a_corrected_encounter_keeps_its_participants(): void
    {
        ['bracket' => $bracket, 'teamA' => $teamA, 'teamB' => $teamB] = $this->makeFourTeamBracket();
        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(2, 0)));

        $bracket->correctResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(0, 2)));

        $encounter = $bracket->getRound(1)->getEncounters()[0];
        EncounterAssert::assertThat($encounter)->homeIsForTeam($teamA)->awayIsForTeam($teamB);
    }

    #[Test]
    public function it_rejects_changing_the_winner_once_the_next_encounter_is_played_without_correcting_it(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();
        $result = EncounterResult::regularTime(Score::of(2, 0));
        $bracket->recordResult(new EncounterId('enc-1'), $result);
        $bracket->recordResult(new EncounterId('enc-2'), EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult(new EncounterId('enc-3'), EncounterResult::regularTime(Score::of(1, 0)));

        try {
            $bracket->correctResult(new EncounterId('enc-1'), EncounterResult::regularTime(Score::of(0, 2)));
            self::fail('Changing the winner once the next encounter is played should have been rejected.');
        } catch (\LogicException) {
        }

        self::assertSame($result, $bracket->getRound(1)->getEncounters()[0]->getResult());
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
        $enc3 = new Encounter(new EncounterId('enc-3'), Participant::pendingWinnerOf(new EncounterId('enc-1')), Participant::pendingWinnerOf(new EncounterId('enc-2')));

        $bracket = new SingleEliminationBracket([
            new Round(1, [$enc1, $enc2]),
            new Round(2, [$enc3]),
        ]);

        return ['bracket' => $bracket, 'teamA' => $teamA, 'teamB' => $teamB, 'teamC' => $teamC, 'teamD' => $teamD];
    }
}
