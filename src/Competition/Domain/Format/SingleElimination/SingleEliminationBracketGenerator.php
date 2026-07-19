<?php

declare(strict_types=1);

namespace App\Competition\Domain\Format\SingleElimination;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Round;
use App\Competition\Domain\Model\SingleEliminationBracket;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\BracketGenerator;

final class SingleEliminationBracketGenerator implements BracketGenerator
{
    private int $encounterCounter = 0;

    /** @param TeamId[] $teamIds */
    public function generate(array $teamIds): Bracket
    {
        $this->encounterCounter = 0;

        if (count($teamIds) < 2) {
            throw new \InvalidArgumentException('At least 2 teams are required.');
        }

        shuffle($teamIds);

        $participants = $this->buildFirstRoundParticipants($teamIds);
        $rounds = [];

        for ($roundNumber = 1; count($participants) > 1; $roundNumber++) {
            $round = $this->buildRound($roundNumber, $participants);
            $rounds[] = $round;
            $participants = $this->nextRoundParticipants($round);
        }

        return new SingleEliminationBracket($rounds);
    }

    /**
     * @param TeamId[] $teamIds
     *
     * @return Participant[]
     */
    private function buildFirstRoundParticipants(array $teamIds): array
    {
        $numRounds = (int) ceil(log(count($teamIds), 2));
        $numByes = 2 ** $numRounds - count($teamIds);

        $byeParticipants = array_fill(0, $numByes, Participant::bye());
        $teamParticipants = array_map(fn (TeamId $t) => Participant::forTeam($t), $teamIds);

        return array_merge($byeParticipants, $teamParticipants);
    }

    /** @param Participant[] $participants */
    private function buildRound(int $number, array $participants): Round
    {
        $encounters = [];
        foreach (array_chunk($participants, 2) as [$home, $away]) {
            $encounters[] = new Encounter($this->nextEncounterId(), $home, $away);
        }

        return new Round($number, $encounters);
    }

    /** @return Participant[] */
    private function nextRoundParticipants(Round $round): array
    {
        return array_map(
            fn (Encounter $e) => $this->participantAdvancingFrom($e),
            $round->getEncounters(),
        );
    }

    private function participantAdvancingFrom(Encounter $encounter): Participant
    {
        if ($encounter->getHome()->isBye()) {
            return $encounter->getAway();
        }

        if ($encounter->getAway()->isBye()) {
            return $encounter->getHome();
        }

        return Participant::pendingWinnerOf($encounter->id);
    }

    private function nextEncounterId(): EncounterId
    {
        return new EncounterId('encounter-' . (++$this->encounterCounter));
    }
}
