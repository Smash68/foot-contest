<?php

declare(strict_types=1);

namespace App\Competition\Domain\Service;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\TeamId;

interface BracketGenerator
{
    /** @param TeamId[] $teamIds */
    public function generate(array $teamIds): Bracket;
}
