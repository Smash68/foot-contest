<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final class Bracket
{
    /** @param Round[] $rounds */
    public function __construct(
        private array $rounds,
    ) {}

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
        return array_sum(array_map(fn(Round $r) => $r->countEncounters(), $this->rounds));
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