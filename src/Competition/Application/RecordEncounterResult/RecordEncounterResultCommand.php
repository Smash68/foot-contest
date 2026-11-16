<?php

declare(strict_types=1);

namespace App\Competition\Application\RecordEncounterResult;

final readonly class RecordEncounterResultCommand
{
    public function __construct(
        public string $competitionId,
        public string $encounterId,
        public string $organizerId,
        public ScoreInput $regularTime,
        public ?ScoreInput $extraTime = null,
        public ?ScoreInput $penalties = null,
    ) {
    }
}
