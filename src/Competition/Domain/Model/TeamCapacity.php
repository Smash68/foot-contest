<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class TeamCapacity
{
    private function __construct(
        public int $min,
        public int $max,
    ) {}

    public static function of(int $min, int $max): self
    {
        if ($min < 2) {
            throw new \InvalidArgumentException('min must be at least 2.');
        }

        if ($min > $max) {
            throw new \InvalidArgumentException('min cannot be greater than max.');
        }

        return new self($min, $max);
    }
}