<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket;

final readonly class GetBracketQuery
{
    public function __construct(public string $competitionId)
    {
    }
}