<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class Round
{
    /** @param Encounter[] $encounters */
    public function __construct(
        private readonly int $number,
        private readonly array $encounters,
    ) {
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    /** @return Encounter[] */
    public function getEncounters(): array
    {
        return $this->encounters;
    }

    public function countEncounters(): int
    {
        return count($this->encounters);
    }

    public function findEncounterById(EncounterId $encounterId): ?Encounter
    {
        foreach ($this->encounters as $encounter) {
            if ($encounter->id->equals($encounterId)) {
                return $encounter;
            }
        }

        return null;
    }

    public function resolveParticipant(EncounterId $sourceId, Team $winner): void
    {
        foreach ($this->encounters as $encounter) {
            if ($encounter->getHome()->isPending() && $encounter->getHome()->getPendingWinnerOfEncounterId()->equals($sourceId)) {
                $encounter->resolveHome(Participant::forTeam($winner));

                return;
            }

            if ($encounter->getAway()->isPending() && $encounter->getAway()->getPendingWinnerOfEncounterId()->equals($sourceId)) {
                $encounter->resolveAway(Participant::forTeam($winner));

                return;
            }
        }
    }
}
