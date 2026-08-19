<?php

declare(strict_types=1);

namespace App\Competition\Domain\Exception;

final class NotAuthorizedToManageJoinRequestException extends NotAuthorizedException
{
    public function __construct(string $actorId, string $teamId)
    {
        parent::__construct("'{$actorId}' is not authorized to manage join requests for team '{$teamId}'.");
    }
}
