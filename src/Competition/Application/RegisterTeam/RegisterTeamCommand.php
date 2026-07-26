<?php

declare(strict_types=1);

namespace App\Competition\Application\RegisterTeam;

final readonly class RegisterTeamCommand
{
    public function __construct(
        public string $competitionId,
        public string $teamName,
        public string $captainId,
    ) {
    }
}
