<?php

declare(strict_types=1);

namespace App\Competition\Domain\Service;

use App\Competition\Domain\Model\OrganizationId;

interface OrganizerOrganizationAuthorization
{
    public function authorizes(string $organizerId, OrganizationId $organizationId): bool;
}
