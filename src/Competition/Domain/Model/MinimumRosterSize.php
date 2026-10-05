<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class MinimumRosterSize
{
    private const int MINIMUM_CAPACITY = 1;

    private function __construct(
        public int $value,
    ) {
    }

    public static function of(int $value): self
    {
        if ($value < self::MINIMUM_CAPACITY) {
            throw new \InvalidArgumentException('The minimum roster size must be at least '.self::MINIMUM_CAPACITY.' player.');
        }

        return new self($value);
    }
}
