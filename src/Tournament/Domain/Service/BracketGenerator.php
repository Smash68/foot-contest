<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Service;

use App\Tournament\Domain\Model\Bracket;
use App\Tournament\Domain\Model\Team;

interface BracketGenerator
{
    /** @param Team[] $teams */
    public function generate(array $teams): Bracket;
}