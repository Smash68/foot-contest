<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineOrganizerRepository implements OrganizerRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): OrganizerId
    {
        return new OrganizerId(Uuid::v7()->toRfc4122());
    }

    public function save(Organizer $organizer): void
    {
        $this->entityManager->persist($organizer);
        $this->entityManager->flush();
    }

    public function ofEmail(string $email): ?Organizer
    {
        return $this->entityManager->getRepository(Organizer::class)->findOneBy(['email' => $email]);
    }
}
