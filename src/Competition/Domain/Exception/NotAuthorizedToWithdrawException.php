<?php

declare(strict_types=1);

namespace App\Competition\Domain\Exception;

final class NotAuthorizedToWithdrawException extends NotAuthorizedException
{
    public function __construct(string $actorId, string $teamId)
    {
        parent::__construct("'{$actorId}' is not authorized to withdraw team '{$teamId}'.");
    }
}
