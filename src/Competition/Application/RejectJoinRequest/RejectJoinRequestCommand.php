<?php

declare(strict_types=1);

namespace App\Competition\Application\RejectJoinRequest;

final readonly class RejectJoinRequestCommand
{
    public function __construct(
        public string $competitionId,
        public string $teamId,
        public string $playerId,
        public string $actorId,
    ) {
    }
}
