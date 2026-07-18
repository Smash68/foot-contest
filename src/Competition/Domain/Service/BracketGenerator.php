<?php

declare(strict_types=1);

namespace App\Competition\Domain\Service;

use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Team;

interface BracketGenerator
{
    /** @param Team[] $teams */
    public function generate(array $teams): Bracket;
}
