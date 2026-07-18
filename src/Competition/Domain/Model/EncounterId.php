<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class EncounterId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('EncounterId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
