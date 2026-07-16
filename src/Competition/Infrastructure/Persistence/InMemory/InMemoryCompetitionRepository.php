<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\InMemory;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;
use Symfony\Component\Uid\Uuid;

final class InMemoryCompetitionRepository implements CompetitionRepository
{
    /** @var array<string, Competition> */
    private array $competitions = [];

    public function nextIdentity(): CompetitionId
    {
        return new CompetitionId(Uuid::v7()->toRfc4122());
    }

    public function save(Competition $competition): void
    {
        $this->competitions[$competition->getId()->value] = $competition;
    }

    public function ofId(CompetitionId $id): ?Competition
    {
        return $this->competitions[$id->value] ?? null;
    }
}