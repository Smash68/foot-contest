<?php

declare(strict_types=1);

namespace App\Organization\Application\IsOrganizerOwnerOfOrganization;

use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizationRepository;

final readonly class IsOrganizerOwnerOfOrganizationHandler
{
    public function __construct(private OrganizationRepository $organizations)
    {
    }

    public function __invoke(IsOrganizerOwnerOfOrganizationQuery $query): bool
    {
        $organization = $this->organizations->ofId(new OrganizationId($query->organizationId));

        if ($organization === null) {
            return false;
        }

        return $organization->getOwnerId()->equals(new OrganizerId($query->organizerId));
    }
}
