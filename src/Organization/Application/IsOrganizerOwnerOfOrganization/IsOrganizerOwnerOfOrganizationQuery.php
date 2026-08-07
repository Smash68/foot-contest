<?php

declare(strict_types=1);

namespace App\Organization\Application\IsOrganizerOwnerOfOrganization;

final readonly class IsOrganizerOwnerOfOrganizationQuery
{
    public function __construct(
        public string $organizerId,
        public string $organizationId,
    ) {
    }
}
