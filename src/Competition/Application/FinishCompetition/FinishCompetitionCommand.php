<?php

declare(strict_types=1);

namespace App\Competition\Application\FinishCompetition;

final readonly class FinishCompetitionCommand
{
    public function __construct(
        public string $competitionId,
        public string $organizerId,
    ) {
    }
}
