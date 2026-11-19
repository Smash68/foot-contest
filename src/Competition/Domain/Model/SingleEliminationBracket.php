<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class SingleEliminationBracket implements Bracket
{
    /** @param Round[] $rounds */
    public function __construct(
        private array $rounds,
    ) {
    }

    /** @return Round[] */
    public function getRounds(): array
    {
        return $this->rounds;
    }

    public function countRounds(): int
    {
        return count($this->rounds);
    }

    public function countEncounters(): int
    {
        return array_sum(array_map(fn (Round $r) => $r->countEncounters(), $this->rounds));
    }

    public function getRound(int $number): Round
    {
        foreach ($this->rounds as $round) {
            if ($round->getNumber() === $number) {
                return $round;
            }
        }

        throw new \InvalidArgumentException("Round {$number} not found.");
    }

    public function findEncounterById(EncounterId $encounterId): ?Encounter
    {
        foreach ($this->rounds as $round) {
            $encounter = $round->findEncounterById($encounterId);
            if ($encounter !== null) {
                return $encounter;
            }
        }

        return null;
    }

    public function isComplete(): bool
    {
        if (empty($this->rounds)) {
            return false;
        }

        return $this->rounds[array_key_last($this->rounds)]->getEncounters()[0]->isCompleted();
    }

    public function getChampion(): TeamId
    {
        if (!$this->isComplete()) {
            throw new \LogicException('Competition is not complete yet.');
        }

        return $this->rounds[array_key_last($this->rounds)]->getEncounters()[0]->getWinner();
    }

    public function getPodium(): Podium
    {
        return new Podium($this->getChampion());
    }

    public function recordResult(EncounterId $encounterId, EncounterResult $result): void
    {
        $encounter = $this->getEncounter($encounterId);

        SingleEliminationResultRules::assertAcceptable($encounterId, $result);

        $encounter->play($result);

        foreach ($this->rounds as $round) {
            $round->resolveParticipant($encounterId, $encounter->getWinner());
        }
    }

    public function correctResult(EncounterId $encounterId, EncounterResult $result): void
    {
        $encounter = $this->getEncounter($encounterId);

        SingleEliminationResultRules::assertAcceptable($encounterId, $result);

        $previousWinner = $encounter->getWinner();
        $nextRound = $this->roundAfter($encounterId);

        $this->assertWinnerCanChange($encounter, $result, $nextRound);

        $encounter->correct($result);

        $nextRound?->replaceTeam($previousWinner, $encounter->getWinner());
    }

    private function assertWinnerCanChange(Encounter $encounter, EncounterResult $result, ?Round $nextRound): void
    {
        $nextEncounter = $nextRound?->encounterOf($encounter->getWinner());

        if ($nextEncounter !== null && $nextEncounter->isCompleted() && $encounter->wouldChangeWinner($result)) {
            throw new \LogicException("Encounter '{$encounter->id->value}': the winner cannot change once the next encounter is played.");
        }
    }

    private function roundAfter(EncounterId $encounterId): ?Round
    {
        $rounds = array_values($this->rounds);

        foreach ($rounds as $index => $round) {
            if ($round->findEncounterById($encounterId) !== null) {
                return $rounds[$index + 1] ?? null;
            }
        }

        return null;
    }

    private function getEncounter(EncounterId $encounterId): Encounter
    {
        $encounter = $this->findEncounterById($encounterId);

        if ($encounter === null) {
            throw new \InvalidArgumentException("Encounter '{$encounterId->value}' not found in bracket.");
        }

        return $encounter;
    }
}
