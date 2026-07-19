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

    public function recordResult(EncounterId $encounterId, EncounterResult $result): void
    {
        foreach ($this->rounds as $roundIndex => $round) {
            $encounter = $round->findEncounterById($encounterId);
            if ($encounter === null) {
                continue;
            }
            $encounter->play($result);
            ($this->rounds[$roundIndex + 1] ?? null)?->resolveParticipant($encounterId, $encounter->getWinner());

            return;
        }

        throw new \InvalidArgumentException("Encounter '{$encounterId->value}' not found in bracket.");
    }
}
