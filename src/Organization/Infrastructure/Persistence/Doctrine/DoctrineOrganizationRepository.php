<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineOrganizationRepository implements OrganizationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): OrganizationId
    {
        return new OrganizationId(Uuid::v7()->toRfc4122());
    }

    public function save(Organization $organization): void
    {
        $this->entityManager->persist($organization);
        $this->entityManager->flush();
    }

    public function ofId(OrganizationId $id): ?Organization
    {
        return $this->entityManager->find(Organization::class, $id);
    }
}
