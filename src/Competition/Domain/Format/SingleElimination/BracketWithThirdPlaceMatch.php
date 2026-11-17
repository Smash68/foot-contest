<?php

declare(strict_types=1);

namespace App\Competition\Domain\Format\SingleElimination;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Round;
use App\Competition\Domain\Model\SingleEliminationResultRules;
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

    public function findEncounterById(EncounterId $encounterId): ?Encounter
    {
        return $this->thirdPlaceEncounterWithId($encounterId) ?? $this->inner->findEncounterById($encounterId);
    }

    public function getThirdPlaceEncounter(): ?Encounter
    {
        return $this->thirdPlaceEncounter;
    }

    public function getFixture(): ThirdPlaceFixture
    {
        return $this->fixture;
    }

    public function recordResult(EncounterId $encounterId, EncounterResult $result): void
    {
        $thirdPlaceEncounter = $this->thirdPlaceEncounterWithId($encounterId);
        if ($thirdPlaceEncounter !== null) {
            SingleEliminationResultRules::assertAcceptable($encounterId, $result);

            $thirdPlaceEncounter->play($result);

            return;
        }

        $this->inner->recordResult($encounterId, $result);
        $this->buildThirdPlaceEncounterIfReady();
    }

    public function correctResult(EncounterId $encounterId, EncounterResult $result): void
    {
        $thirdPlaceEncounter = $this->thirdPlaceEncounterWithId($encounterId);
        if ($thirdPlaceEncounter !== null) {
            SingleEliminationResultRules::assertAcceptable($encounterId, $result);

            $thirdPlaceEncounter->correct($result);

            return;
        }

        if ($this->thirdPlaceEncounter !== null && $this->fixture->isSemiFinal($encounterId)) {
            $this->correctSemiFinal($encounterId, $result, $this->thirdPlaceEncounter);

            return;
        }

        $this->inner->correctResult($encounterId, $result);
    }

    private function correctSemiFinal(EncounterId $encounterId, EncounterResult $result, Encounter $thirdPlaceEncounter): void
    {
        $semiFinal = $this->findEncounter($encounterId);

        SingleEliminationResultRules::assertAcceptable($encounterId, $result);

        if ($thirdPlaceEncounter->isCompleted() && $semiFinal->wouldChangeWinner($result)) {
            throw new \LogicException("Encounter '{$encounterId->value}': the winner cannot change once the third place encounter is played.");
        }

        $previousLoser = $semiFinal->getLoser();

        $this->inner->correctResult($encounterId, $result);

        $thirdPlaceEncounter->replaceTeam($previousLoser, $semiFinal->getLoser());
    }

    private function thirdPlaceEncounterWithId(EncounterId $encounterId): ?Encounter
    {
        if ($this->thirdPlaceEncounter !== null && $this->thirdPlaceEncounter->id->equals($encounterId)) {
            return $this->thirdPlaceEncounter;
        }

        return null;
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
        $encounter = $this->inner->findEncounterById($encounterId);

        if ($encounter === null) {
            throw new \LogicException("Encounter '{$encounterId->value}' not found in bracket.");
        }

        return $encounter;
    }
}
