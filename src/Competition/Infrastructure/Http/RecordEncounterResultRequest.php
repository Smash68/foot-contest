<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

final readonly class RecordEncounterResultRequest
{
    public function __construct(
        public ScoreRequest $regularTime,
    ) {
    }
}
