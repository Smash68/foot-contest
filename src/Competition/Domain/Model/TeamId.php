<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class TeamId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('TeamId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
