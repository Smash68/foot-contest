<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Service;

use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;

final class InMemoryOrganizerOrganizationAuthorization implements OrganizerOrganizationAuthorization
{
    /** @var array<string, string> organizationId => organizerId */
    private array $owners = [];

    public function grantOwnership(string $organizerId, OrganizationId $organizationId): void
    {
        $this->owners[$organizationId->value] = $organizerId;
    }

    public function authorizes(string $organizerId, OrganizationId $organizationId): bool
    {
        return ($this->owners[$organizationId->value] ?? null) === $organizerId;
    }
}
