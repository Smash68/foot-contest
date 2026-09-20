<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class RoundView
{
    /** @param EncounterView[] $encounters */
    public function __construct(
        public int $number,
        public array $encounters,
    ) {
    }
}