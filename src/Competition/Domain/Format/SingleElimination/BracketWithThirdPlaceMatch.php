<?php

declare(strict_types=1);

namespace App\Competition\Domain\Format\SingleElimination;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Round;
use App\Competition\Domain\Model\TeamId;

final class BracketWithThirdPlaceMatch implements Bracket
{
    private ?Encounter $thirdPlaceEncounter = null;

    public function __construct(
        private readonly Bracket $inner,
        private readonly ThirdPlaceFixture $fixture,
    ) {
    }

    public function getRounds(): array
    {
        return $this->inner->getRounds();
    }

    public function countRounds(): int
    {
        return $this->inner->countRounds();
    }

    public function countEncounters(): int
    {
        return $this->inner->countEncounters();
    }

    public function getRound(int $number): Round
    {
        return $this->inner->getRound($number);
    }

    public function isComplete(): bool
    {
        return $this->inner->isComplete();
    }

    public function getChampion(): TeamId
    {
        return $this->inner->getChampion();
    }

    public function getThirdPlaceEncounter(): ?Encounter
    {
        return $this->thirdPlaceEncounter;
    }

    public function recordResult(EncounterId $encounterId, EncounterResult $result): void
    {
        if ($this->thirdPlaceEncounter !== null && $this->thirdPlaceEncounter->id->equals($encounterId)) {
            $this->thirdPlaceEncounter->play($result);

            return;
        }

        $this->inner->recordResult($encounterId, $result);
        $this->buildThirdPlaceEncounterIfReady();
    }

    private function buildThirdPlaceEncounterIfReady(): void
    {
        if ($this->thirdPlaceEncounter !== null) {
            return;
        }

        $semiFinalOne = $this->findEncounter($this->fixture->semiFinalOneId);
        $semiFinalTwo = $this->findEncounter($this->fixture->semiFinalTwoId);

        if (!$semiFinalOne->isCompleted() || !$semiFinalTwo->isCompleted()) {
            return;
        }

        $this->thirdPlaceEncounter = new Encounter(
            $this->fixture->id,
            Participant::forTeam($semiFinalOne->getLoser()),
            Participant::forTeam($semiFinalTwo->getLoser()),
        );
    }

    private function findEncounter(EncounterId $encounterId): Encounter
    {
        foreach ($this->inner->getRounds() as $round) {
            $encounter = $round->findEncounterById($encounterId);
            if ($encounter !== null) {
                return $encounter;
            }
        }

        throw new \LogicException("Encounter '{$encounterId->value}' not found in bracket.");
    }
}
