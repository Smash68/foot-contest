<?php

declare(strict_types=1);

namespace App\Competition\Domain\Exception;

final class NotAuthorizedToRemoveTeamMemberException extends NotAuthorizedException
{
    public function __construct(string $actorId, string $playerId, string $teamId)
    {
        parent::__construct("'{$actorId}' is not authorized to remove player '{$playerId}' from team '{$teamId}'.");
    }
}
