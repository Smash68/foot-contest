<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class EncounterResultView
{
    public function __construct(
        public ScoreView $regularTime,
        public ?ScoreView $extraTime = null,
        public ?ScoreView $penalties = null,
    ) {
    }
}