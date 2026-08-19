<?php

declare(strict_types=1);

namespace App\Competition\Application\ApproveJoinRequest;

final readonly class ApproveJoinRequestCommand
{
    public function __construct(
        public string $competitionId,
        public string $teamId,
        public string $playerId,
        public string $actorId,
    ) {
    }
}
