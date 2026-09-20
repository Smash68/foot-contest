<?php

declare(strict_types=1);

namespace App\Competition\Application\RemovePlayerFromTeam;

final readonly class RemovePlayerFromTeamCommand
{
    public function __construct(
        public string $competitionId,
        public string $teamId,
        public string $playerId,
        public string $actorId,
    ) {
    }
}
