<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter;

final readonly class GetEncounterQuery
{
    public function __construct(
        public string $competitionId,
        public string $encounterId,
    ) {
    }
}
