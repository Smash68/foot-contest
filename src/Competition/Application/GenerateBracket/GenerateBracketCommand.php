<?php

declare(strict_types=1);

namespace App\Competition\Application\GenerateBracket;

final readonly class GenerateBracketCommand
{
    public function __construct(
        public string $competitionId,
    ) {
    }
}
