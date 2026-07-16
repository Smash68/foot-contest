<?php

declare(strict_types=1);

namespace App\Competition\Domain\Repository;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;

interface CompetitionRepository
{
    public function save(Competition $competition): void;

    public function ofId(CompetitionId $id): ?Competition;
}