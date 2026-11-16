<?php

declare(strict_types=1);

namespace App\Competition\Application\RecordEncounterResult;

final readonly class ScoreInput
{
    public function __construct(
        public int $home,
        public int $away,
    ) {
    }
}
