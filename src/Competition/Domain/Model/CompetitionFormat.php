<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

enum CompetitionFormat: string
{
    case SingleElimination = 'single_elimination';

    public static function fromValue(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException("Invalid competition format '{$value}'.");
    }
}
