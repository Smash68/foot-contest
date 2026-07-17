<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineCompetitionRepository implements CompetitionRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function nextIdentity(): CompetitionId
    {
        return new CompetitionId(Uuid::v7()->toRfc4122());
    }

    public function save(Competition $competition): void
    {
        $this->entityManager->persist($competition);
        $this->entityManager->flush();
    }

    public function ofId(CompetitionId $id): ?Competition
    {
        return $this->entityManager->find(Competition::class, $id);
    }
}