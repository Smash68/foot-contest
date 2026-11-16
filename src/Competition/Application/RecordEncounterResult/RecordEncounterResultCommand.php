<?php

declare(strict_types=1);

namespace App\Competition\Application\RecordEncounterResult;

final readonly class RecordEncounterResultCommand
{
    public function __construct(
        public string $competitionId,
        public string $encounterId,
        public string $organizerId,
        public int $regularTimeHome,
        public int $regularTimeAway,
        public ?int $extraTimeHome = null,
        public ?int $extraTimeAway = null,
        public ?int $penaltiesHome = null,
        public ?int $penaltiesAway = null,
    ) {
    }
}
