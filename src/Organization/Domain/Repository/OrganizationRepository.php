<?php

declare(strict_types=1);

namespace App\Organization\Domain\Repository;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;

interface OrganizationRepository
{
    public function nextIdentity(): OrganizationId;

    public function save(Organization $organization): void;

    public function ofId(OrganizationId $id): ?Organization;
}
