<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class PlayerId
{
    public function __construct(public string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('PlayerId must be a valid email address.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
