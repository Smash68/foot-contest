<?php

declare(strict_types=1);

namespace App\Competition\Domain\Repository;

use App\Competition\Domain\Model\TeamId;

interface TeamRepository
{
    public function nextIdentity(): TeamId;
}
