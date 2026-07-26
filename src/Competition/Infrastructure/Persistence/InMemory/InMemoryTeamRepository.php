<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\InMemory;

use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\TeamRepository;
use Symfony\Component\Uid\Uuid;

final class InMemoryTeamRepository implements TeamRepository
{
    public function nextIdentity(): TeamId
    {
        return new TeamId(Uuid::v7()->toRfc4122());
    }
}
