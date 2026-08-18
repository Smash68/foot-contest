<?php

declare(strict_types=1);

namespace App\Competition\Application\RequestToJoinTeam;

final readonly class RequestToJoinTeamCommand
{
    public function __construct(
        public string $competitionId,
        public string $teamId,
        public string $playerId,
    ) {
    }
}
