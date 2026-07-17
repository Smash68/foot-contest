<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class CompetitionId implements \Stringable
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('CompetitionId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}