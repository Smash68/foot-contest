<?php

declare(strict_types=1);

namespace App\Competition\Application\CorrectEncounterResult;

final readonly class CorrectEncounterResultCommand
{
    public function __construct(
        public string $competitionId,
        public string $encounterId,
        public string $organizerId,
        public ScoreInput $regularTime,
    ) {
    }
}
