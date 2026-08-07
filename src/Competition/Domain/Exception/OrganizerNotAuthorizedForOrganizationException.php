<?php

declare(strict_types=1);

namespace App\Competition\Domain\Exception;

final class OrganizerNotAuthorizedForOrganizationException extends \RuntimeException
{
    public function __construct(string $organizerId, string $organizationId)
    {
        parent::__construct("Organizer '{$organizerId}' is not authorized to act on organization '{$organizationId}'.");
    }
}
