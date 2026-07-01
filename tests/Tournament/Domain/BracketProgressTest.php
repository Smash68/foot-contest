<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\Bracket;
use App\Tournament\Domain\Model\Encounter;
use App\Tournament\Domain\Model\EncounterId;
use App\Tournament\Domain\Model\EncounterResult;
use App\Tournament\Domain\Model\Participant;
use App\Tournament\Domain\Model\Round;
use App\Tournament\Domain\Model\Team;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BracketProgressTest extends TestCase
{
    #[Test]
    public function home_winner_advances_to_next_round(): void
    {
        ['bracket' => $bracket, 'teamA' => $teamA] = $this->makeFourTeamBracket();

        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(2, 0));

        $homeParticipant = $bracket->getRound(2)->getEncounters()[0]->getHome();
        self::assertTrue($homeParticipant->isTeam());
        self::assertSame($teamA->getId(), $homeParticipant->getTeam()->getId());
    }

    #[Test]
    public function away_winner_advances_to_next_round(): void
    {
        ['bracket' => $bracket, 'teamB' => $teamB] = $this->makeFourTeamBracket();

        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(0, 3));

        $homeParticipant = $bracket->getRound(2)->getEncounters()[0]->getHome();
        self::assertTrue($homeParticipant->isTeam());
        self::assertSame($teamB->getId(), $homeParticipant->getTeam()->getId());
    }

    #[Test]
    public function other_pending_participants_remain_untouched(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        $bracket->recordResult(new EncounterId('enc-1'), EncounterResult::regularTime(2, 0));

        // enc-2 not yet played — away participant of the final still pending
        $awayParticipant = $bracket->getRound(2)->getEncounters()[0]->getAway();
        self::assertTrue($awayParticipant->isPending());
    }

    #[Test]
    public function it_throws_when_encounter_not_found(): void
    {
        ['bracket' => $bracket] = $this->makeFourTeamBracket();

        $this->expectException(\InvalidArgumentException::class);

        $bracket->recordResult(new EncounterId('enc-999'), EncounterResult::regularTime(1, 0));
    }

    // --- helpers ---

    /** @return array{bracket: Bracket, teamA: Team, teamB: Team, teamC: Team, teamD: Team} */
    private function makeFourTeamBracket(): array
    {
        $teamA = new Team('a', 'Team A');
        $teamB = new Team('b', 'Team B');
        $teamC = new Team('c', 'Team C');
        $teamD = new Team('d', 'Team D');

        $enc1 = new Encounter(new EncounterId('enc-1'), Participant::forTeam($teamA), Participant::forTeam($teamB));
        $enc2 = new Encounter(new EncounterId('enc-2'), Participant::forTeam($teamC), Participant::forTeam($teamD));
        $enc3 = new Encounter(new EncounterId('enc-3'), Participant::pendingWinnerOf(new EncounterId('enc-1')), Participant::pendingWinnerOf(new EncounterId('enc-2')));

        $bracket = new Bracket([
            new Round(1, [$enc1, $enc2]),
            new Round(2, [$enc3]),
        ]);

        return ['bracket' => $bracket, 'teamA' => $teamA, 'teamB' => $teamB, 'teamC' => $teamC, 'teamD' => $teamD];
    }
}