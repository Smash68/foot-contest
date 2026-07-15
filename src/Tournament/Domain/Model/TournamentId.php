<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final readonly class TournamentId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('TournamentId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}