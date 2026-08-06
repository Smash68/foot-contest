<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\InMemory;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use Symfony\Component\Uid\Uuid;

final class InMemoryOrganizationRepository implements OrganizationRepository
{
    /** @var array<string, Organization> */
    private array $organizations = [];

    public function nextIdentity(): OrganizationId
    {
        return new OrganizationId(Uuid::v7()->toRfc4122());
    }

    public function save(Organization $organization): void
    {
        $this->organizations[$organization->getId()->value] = $organization;
    }

    public function ofId(OrganizationId $id): ?Organization
    {
        return $this->organizations[$id->value] ?? null;
    }
}
