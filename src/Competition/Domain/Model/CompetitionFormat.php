<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

enum CompetitionFormat: string
{
    case SingleElimination = 'single_elimination';
}
