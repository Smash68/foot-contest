<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

final readonly class RecordEncounterResultRequest
{
    public function __construct(
        public ScoreRequest $regularTime,
        /** Cumulative score at the end of extra time, regular time goals included. */
        public ?ScoreRequest $extraTime = null,
        public ?ScoreRequest $penalties = null,
    ) {
    }
}
